<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\TeacherAvailability;
use App\Models\TimetablePeriod;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Parser as PdfTextParser;
use ZipArchive;

class TeacherAvailabilityImportService
{
    public function __construct(
        private readonly TeacherAvailabilityService $availabilities,
        private readonly TimetableTemplateService $templates,
    ) {}

    public function templateHeaders(): array
    {
        return ['Professeur', 'Jour', 'Debut', 'Fin', 'Statut', 'Note'];
    }

    public function templateRows(AcademicYear $academicYear): array
    {
        $firstPeriod = TimetablePeriod::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('is_active', true)
            ->where('is_break', false)
            ->orderBy('sort_order')
            ->first();
        $lastPeriod = TimetablePeriod::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('is_active', true)
            ->where('is_break', false)
            ->orderByDesc('sort_order')
            ->first();

        return User::query()
            ->role('enseignant')
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(fn (User $teacher): array => [
                $teacher->name,
                '',
                $firstPeriod?->starts_at ? substr((string) $firstPeriod->starts_at, 0, 5) : '',
                $lastPeriod?->ends_at ? substr((string) $lastPeriod->ends_at, 0, 5) : '',
                '',
                '',
            ])
            ->all();
    }

    public function preview(UploadedFile $file, AcademicYear $academicYear): array
    {
        $rawRows = $this->readRows($file);
        if (count($rawRows) > 1001) {
            throw ValidationException::withMessages([
                'availability_file' => 'Le fichier depasse 1 000 lignes. Separe-le en plusieurs imports.',
            ]);
        }
        $headers = array_shift($rawRows) ?? [];
        $mappedHeaders = $this->mapHeaders($headers);
        $missingHeaders = collect(['teacher', 'day', 'starts_at', 'ends_at', 'status'])
            ->reject(fn (string $field): bool => in_array($field, $mappedHeaders, true))
            ->values();
        $teachers = $this->teachersByKey();
        $periods = $this->coursePeriods($academicYear);
        $seenSlots = [];

        $rows = collect($rawRows)
            ->filter(fn (array $row): bool => collect($row)->contains(fn ($value): bool => filled(trim((string) $value))))
            ->values()
            ->map(function (array $row, int $index) use ($mappedHeaders, $missingHeaders, $teachers, $periods, &$seenSlots): array {
                return $this->previewRow(
                    $row,
                    $index + 2,
                    $mappedHeaders,
                    $missingHeaders,
                    $teachers,
                    $periods,
                    $seenSlots,
                );
            });

        return [
            'academic_year_id' => $academicYear->id,
            'filename' => $file->getClientOriginalName(),
            'headers' => $headers,
            'rows' => $rows->all(),
            'summary' => [
                'total' => $rows->count(),
                'valid' => $rows->where('status', 'valid')->count(),
                'invalid' => $rows->where('status', 'invalid')->count(),
                'teachers' => $rows->where('status', 'valid')->pluck('data.teacher_id')->unique()->count(),
            ],
        ];
    }

    public function import(array $preview, AcademicYear $academicYear, User $actor): array
    {
        $periods = $this->coursePeriods($academicYear);
        $days = array_keys($this->templates->days());
        $validRows = collect($preview['rows'] ?? [])->where('status', 'valid');
        $imported = 0;

        DB::transaction(function () use ($validRows, $periods, $days, $academicYear, $actor, &$imported): void {
            foreach ($validRows->groupBy('data.teacher_id') as $teacherId => $rows) {
                $teacher = User::query()->findOrFail($teacherId);
                $slots = [];

                foreach ($periods as $period) {
                    foreach ($days as $day) {
                        $slots[$period->id][$day] = TeacherAvailability::STATUS_UNAVAILABLE;
                    }
                }

                $notes = [];
                foreach ($rows as $row) {
                    foreach ($row['data']['period_ids'] as $periodId) {
                        $slots[$periodId][$row['data']['day']] = $row['data']['availability_status'];
                    }
                    if (filled($row['data']['note'])) {
                        $notes[] = $row['data']['note'];
                    }
                }

                $this->availabilities->replaceImported(
                    $academicYear,
                    $teacher,
                    $actor,
                    $slots,
                    collect($notes)->unique()->implode(' | '),
                );
                $imported++;
            }
        });

        return ['teachers' => $imported, 'rows' => $validRows->count()];
    }

    private function previewRow(
        array $row,
        int $lineNumber,
        array $mappedHeaders,
        Collection $missingHeaders,
        Collection $teachers,
        Collection $periods,
        array &$seenSlots,
    ): array {
        $values = [];
        foreach ($mappedHeaders as $index => $field) {
            if ($field) {
                $values[$field] = trim((string) ($row[$index] ?? ''));
            }
        }

        $errors = $missingHeaders
            ->map(fn (string $field): string => 'Colonne obligatoire absente : '.$this->fieldLabel($field).'.')
            ->all();
        $teacher = $teachers->get($this->normalizeKey($values['teacher'] ?? ''));
        $day = $this->normalizeDay($values['day'] ?? '');
        $startsAt = $this->normalizeTime($values['starts_at'] ?? '');
        $endsAt = $this->normalizeTime($values['ends_at'] ?? '');
        $availabilityStatus = $this->normalizeStatus($values['status'] ?? '');

        if (! $teacher) {
            $errors[] = 'Professeur introuvable ou inactif.';
        }
        if (! $day) {
            $errors[] = 'Jour non reconnu.';
        }
        if (! $startsAt || ! $endsAt || $startsAt >= $endsAt) {
            $errors[] = 'Les heures de debut et de fin sont invalides.';
        }
        if (! $availabilityStatus) {
            $errors[] = 'Statut attendu : disponible, prefere ou indisponible.';
        }

        $matchedPeriods = ($startsAt && $endsAt)
            ? $periods->filter(fn (TimetablePeriod $period): bool => substr((string) $period->starts_at, 0, 5) >= $startsAt
                && substr((string) $period->ends_at, 0, 5) <= $endsAt)
            : collect();

        if ($startsAt && $endsAt && $matchedPeriods->isEmpty()) {
            $errors[] = 'Aucun creneau configure ne se trouve dans cette plage horaire.';
        }

        if ($teacher && $day && $availabilityStatus) {
            foreach ($matchedPeriods as $period) {
                $slotKey = $teacher->id.'|'.$day.'|'.$period->id;
                if (isset($seenSlots[$slotKey]) && $seenSlots[$slotKey] !== $availabilityStatus) {
                    $errors[] = 'Cette plage contredit une autre ligne du fichier.';
                    break;
                }
                $seenSlots[$slotKey] = $availabilityStatus;
            }
        }

        return [
            'line' => $lineNumber,
            'status' => $errors === [] ? 'valid' : 'invalid',
            'errors' => array_values(array_unique($errors)),
            'display' => [
                'teacher' => $values['teacher'] ?? '',
                'day' => $values['day'] ?? '',
                'range' => trim(($values['starts_at'] ?? '').' - '.($values['ends_at'] ?? ''), ' -'),
                'status' => $values['status'] ?? '',
            ],
            'data' => [
                'teacher_id' => $teacher?->id,
                'day' => $day,
                'period_ids' => $matchedPeriods->pluck('id')->all(),
                'availability_status' => $availabilityStatus,
                'note' => $values['note'] ?? null,
            ],
        ];
    }

    private function readRows(UploadedFile $file): array
    {
        return match (Str::lower($file->getClientOriginalExtension())) {
            'xlsx' => $this->readXlsxRows($file->getRealPath()),
            'pdf' => $this->readPdfRows($file->getRealPath()),
            'docx' => $this->readDocxRows($file->getRealPath()),
            default => $this->readCsvRows($file->getRealPath()),
        };
    }

    private function readCsvRows(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $delimiter = $this->detectDelimiter($lines[0] ?? '');

        return collect($lines)->map(function (string $line) use ($delimiter): array {
            $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;

            return str_getcsv($line, $delimiter);
        })->all();
    }

    private function readXlsxRows(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetXml = $this->safeZipEntry($zip, 'xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) {
            return [];
        }

        $sheet = simplexml_load_string($sheetXml);
        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                $columnIndex = $this->columnIndex((string) $cell['r']);
                $type = (string) $cell['t'];
                $value = (string) ($cell->v ?? '');
                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                }
                $values[$columnIndex] = $value;
            }
            if ($values !== []) {
                ksort($values);
                $rows[] = array_values($values);
            }
        }

        return $rows;
    }

    private function readDocxRows(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return [];
        }
        $xml = $this->safeZipEntry($zip, 'word/document.xml');
        $zip->close();
        if ($xml === false) {
            return [];
        }

        $document = simplexml_load_string($xml);
        $document->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $rows = [];
        foreach ($document->xpath('//w:tr') ?: [] as $row) {
            $cells = [];
            foreach ($row->xpath('./w:tc') ?: [] as $cell) {
                $cells[] = trim(implode(' ', array_map('strval', $cell->xpath('.//w:t') ?: [])));
            }
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        return $rows;
    }

    private function readPdfRows(string $path): array
    {
        try {
            $text = (new PdfTextParser)->parseFile($path)->getText();
        } catch (\Throwable) {
            return [];
        }

        $lines = preg_split('/\R/u', str_replace("\xC2\xA0", ' ', $text)) ?: [];

        return collect($lines)
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->map(fn (string $line): array => $this->splitTextLine($line))
            ->filter(fn (array $row): bool => count($row) > 1)
            ->values()
            ->all();
    }

    private function splitTextLine(string $line): array
    {
        foreach ([';', "\t"] as $delimiter) {
            if (str_contains($line, $delimiter)) {
                return str_getcsv($line, $delimiter);
            }
        }
        if (preg_match('/\s{2,}/', $line)) {
            return preg_split('/\s{2,}/', $line) ?: [];
        }

        return [$line];
    }

    private function mapHeaders(array $headers): array
    {
        $aliases = [
            'professeur' => 'teacher', 'enseignant' => 'teacher', 'nom' => 'teacher',
            'jour' => 'day', 'journee' => 'day',
            'debut' => 'starts_at', 'heure_debut' => 'starts_at',
            'fin' => 'ends_at', 'heure_fin' => 'ends_at',
            'statut' => 'status', 'disponibilite' => 'status',
            'note' => 'note', 'observation' => 'note', 'commentaire' => 'note',
        ];

        return collect($headers)
            ->map(fn ($header) => $aliases[$this->normalizeKey((string) $header)] ?? null)
            ->all();
    }

    private function teachersByKey(): Collection
    {
        $map = collect();
        User::query()->role('enseignant')->where('status', 'active')->get()->each(function (User $teacher) use ($map): void {
            foreach ([$teacher->name, $teacher->username, $teacher->email] as $identifier) {
                if (filled($identifier)) {
                    $map->put($this->normalizeKey((string) $identifier), $teacher);
                }
            }
        });

        return $map;
    }

    private function coursePeriods(AcademicYear $academicYear): Collection
    {
        $this->templates->ensurePeriods($academicYear);

        return TimetablePeriod::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('is_active', true)
            ->where('is_break', false)
            ->orderBy('sort_order')
            ->get();
    }

    private function normalizeDay(string $value): ?string
    {
        return [
            'lundi' => 'monday', 'monday' => 'monday',
            'mardi' => 'tuesday', 'tuesday' => 'tuesday',
            'mercredi' => 'wednesday', 'wednesday' => 'wednesday',
            'jeudi' => 'thursday', 'thursday' => 'thursday',
            'vendredi' => 'friday', 'friday' => 'friday',
            'samedi' => 'saturday', 'saturday' => 'saturday',
        ][$this->normalizeKey($value)] ?? null;
    }

    private function normalizeStatus(string $value): ?string
    {
        return [
            'disponible' => TeacherAvailability::STATUS_AVAILABLE,
            'available' => TeacherAvailability::STATUS_AVAILABLE,
            'oui' => TeacherAvailability::STATUS_AVAILABLE,
            'prefere' => TeacherAvailability::STATUS_PREFERRED,
            'preferentiel' => TeacherAvailability::STATUS_PREFERRED,
            'preferred' => TeacherAvailability::STATUS_PREFERRED,
            'indisponible' => TeacherAvailability::STATUS_UNAVAILABLE,
            'unavailable' => TeacherAvailability::STATUS_UNAVAILABLE,
            'non' => TeacherAvailability::STATUS_UNAVAILABLE,
        ][$this->normalizeKey($value)] ?? null;
    }

    private function normalizeTime(string $value): ?string
    {
        $value = trim(Str::lower($value));
        $value = preg_replace('/\s+/', '', str_replace(['h', '.'], ':', $value)) ?? $value;
        if (preg_match('/^(\d{1,2})(?::(\d{1,2}))?$/', $value, $matches) !== 1) {
            return null;
        }
        $hour = (int) $matches[1];
        $minute = isset($matches[2]) ? (int) $matches[2] : 0;
        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    private function normalizeKey(string $value): string
    {
        return (string) Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_');
    }

    private function detectDelimiter(string $line): string
    {
        $counts = collect([';' => substr_count($line, ';'), ',' => substr_count($line, ','), "\t" => substr_count($line, "\t")]);

        return (string) $counts->sortDesc()->keys()->first();
    }

    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $this->safeZipEntry($zip, 'xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $strings = [];
        foreach (simplexml_load_string($xml)->si as $item) {
            $strings[] = isset($item->t)
                ? (string) $item->t
                : collect($item->r ?? [])->map(fn ($run): string => (string) $run->t)->implode('');
        }

        return $strings;
    }

    private function safeZipEntry(ZipArchive $zip, string $name): string|false
    {
        $metadata = $zip->statName($name);
        if (! is_array($metadata) || (int) ($metadata['size'] ?? 0) > 10 * 1024 * 1024) {
            return false;
        }

        return $zip->getFromName($name);
    }

    private function columnIndex(string $reference): int
    {
        preg_match('/^[A-Z]+/', strtoupper($reference), $matches);
        $index = 0;
        foreach (str_split($matches[0] ?? 'A') as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    private function fieldLabel(string $field): string
    {
        return [
            'teacher' => 'Professeur', 'day' => 'Jour', 'starts_at' => 'Debut',
            'ends_at' => 'Fin', 'status' => 'Statut',
        ][$field] ?? $field;
    }
}
