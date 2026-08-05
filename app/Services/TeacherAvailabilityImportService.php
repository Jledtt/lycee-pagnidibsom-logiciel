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
        $extension = Str::lower($file->getClientOriginalExtension());
        $warnings = [];
        $headerIndex = $this->findHeaderIndex($rawRows);

        if ($headerIndex === null && in_array($extension, ['pdf', 'docx'], true)) {
            $rawRows = $this->readNarrativeRows($file);
            if (count($rawRows) > 1001) {
                throw ValidationException::withMessages([
                    'availability_file' => 'Le document contient plus de 1 000 lignes détectées. Sépare-le en plusieurs imports.',
                ]);
            }
            $headerIndex = $this->findHeaderIndex($rawRows);
            $warnings[] = 'Le document libre a été interprété automatiquement. Vérifie chaque ligne avant l’import.';
        }

        if ($headerIndex === null) {
            $warnings[] = match ($extension) {
                'pdf' => 'Aucun tableau lisible n’a été détecté. Si le PDF est scanné, convertis-le en PDF avec texte ou utilise le modèle CSV.',
                'docx' => 'Aucun tableau exploitable n’a été détecté. Utilise un tableau Word avec les colonnes du modèle.',
                default => 'Les colonnes obligatoires n’ont pas été reconnues. Télécharge le modèle CSV et conserve ses en-têtes.',
            };
            $headers = [];
            $rawRows = [];
        } else {
            $headers = $rawRows[$headerIndex];
            $rawRows = array_slice($rawRows, $headerIndex + 1);
        }

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

        $preview = [
            'academic_year_id' => $academicYear->id,
            'filename' => $file->getClientOriginalName(),
            'source_type' => $extension,
            'assisted' => in_array($extension, ['pdf', 'docx'], true),
            'warnings' => array_values(array_unique($warnings)),
            'expires_at' => now()->addMinutes(30)->timestamp,
            'headers' => $headers,
            'rows' => $rows->all(),
        ];

        return $this->withSummary($preview);
    }

    public function revise(array $preview, array $submittedRows, AcademicYear $academicYear): array
    {
        $periods = $this->coursePeriods($academicYear);
        $teachers = $this->activeTeachers()->keyBy('id');
        $seenSlots = [];

        $rows = collect($submittedRows)
            ->take(1000)
            ->values()
            ->map(function (array $row, int $index) use ($periods, $teachers, &$seenSlots): array {
                $teacher = $teachers->get((int) ($row['teacher_id'] ?? 0));
                $values = [
                    'teacher' => $teacher?->name ?? '',
                    'day' => (string) ($row['day'] ?? ''),
                    'starts_at' => (string) ($row['starts_at'] ?? ''),
                    'ends_at' => (string) ($row['ends_at'] ?? ''),
                    'status' => (string) ($row['availability_status'] ?? ''),
                    'note' => trim((string) ($row['note'] ?? '')),
                ];

                return $this->evaluateRow(
                    $values,
                    (int) ($row['line'] ?? $index + 1),
                    $teacher,
                    $periods,
                    $seenSlots,
                    filter_var($row['selected'] ?? false, FILTER_VALIDATE_BOOL),
                    'reviewed',
                    (string) ($row['raw'] ?? ''),
                );
            });

        $preview['rows'] = $rows->all();
        $preview['reviewed_at'] = now()->timestamp;

        return $this->withSummary($preview);
    }

    public function revalidate(array $preview, AcademicYear $academicYear): array
    {
        $rows = collect($preview['rows'] ?? [])->map(fn (array $row): array => [
            'line' => $row['line'] ?? null,
            'selected' => $row['selected'] ?? true,
            'teacher_id' => $row['input']['teacher_id'] ?? null,
            'day' => $row['input']['day'] ?? null,
            'starts_at' => $row['input']['starts_at'] ?? null,
            'ends_at' => $row['input']['ends_at'] ?? null,
            'availability_status' => $row['input']['availability_status'] ?? null,
            'note' => $row['input']['note'] ?? null,
            'raw' => $row['raw'] ?? null,
        ])->all();

        return $this->revise($preview, $rows, $academicYear);
    }

    public function reviewTeachers(): Collection
    {
        return $this->activeTeachers()->sortBy('name')->values();
    }

    public function import(array $preview, AcademicYear $academicYear, User $actor): array
    {
        $periods = $this->coursePeriods($academicYear);
        $days = array_keys($this->templates->days());
        $validRows = collect($preview['rows'] ?? [])
            ->where('selected', true)
            ->where('status', 'valid');
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

        $teacher = $teachers->get($this->normalizeKey($values['teacher'] ?? ''));
        $evaluated = $this->evaluateRow(
            $values,
            $lineNumber,
            $teacher,
            $periods,
            $seenSlots,
            true,
            'detected',
            implode(' | ', array_filter(array_map('strval', $row))),
        );
        $evaluated['errors'] = array_values(array_unique([
            ...$missingHeaders
                ->map(fn (string $field): string => 'Colonne obligatoire absente : '.$this->fieldLabel($field).'.')
                ->all(),
            ...$evaluated['errors'],
        ]));
        $evaluated['status'] = $evaluated['errors'] === [] ? 'valid' : 'invalid';

        return $evaluated;
    }

    private function evaluateRow(
        array $values,
        int $lineNumber,
        ?User $teacher,
        Collection $periods,
        array &$seenSlots,
        bool $selected,
        string $confidence,
        string $raw,
    ): array {
        $errors = [];
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
            'selected' => $selected,
            'status' => $errors === [] ? 'valid' : 'invalid',
            'confidence' => $confidence,
            'raw' => Str::limit($raw, 1000, ''),
            'errors' => array_values(array_unique($errors)),
            'display' => [
                'teacher' => $teacher?->name ?? ($values['teacher'] ?? ''),
                'day' => $day ? ($this->templates->days()[$day] ?? $day) : ($values['day'] ?? ''),
                'range' => trim(($values['starts_at'] ?? '').' - '.($values['ends_at'] ?? ''), ' -'),
                'status' => $availabilityStatus
                    ? (TeacherAvailability::labels()[$availabilityStatus] ?? $availabilityStatus)
                    : ($values['status'] ?? ''),
            ],
            'input' => [
                'teacher_id' => $teacher?->id,
                'day' => $day,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'availability_status' => $availabilityStatus,
                'note' => $values['note'] ?? null,
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

    private function withSummary(array $preview): array
    {
        $rows = collect($preview['rows'] ?? []);
        $selected = $rows->where('selected', true);

        $preview['summary'] = [
            'total' => $rows->count(),
            'selected' => $selected->count(),
            'valid' => $selected->where('status', 'valid')->count(),
            'invalid' => $selected->where('status', 'invalid')->count(),
            'ignored' => $rows->where('selected', false)->count(),
            'teachers' => $selected->where('status', 'valid')->pluck('data.teacher_id')->filter()->unique()->count(),
        ];

        return $preview;
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

    private function findHeaderIndex(array $rows): ?int
    {
        foreach (array_slice($rows, 0, 20, true) as $index => $row) {
            $mapped = array_filter($this->mapHeaders($row));
            if (count(array_intersect(['teacher', 'day', 'starts_at', 'ends_at', 'status'], $mapped)) >= 4) {
                return (int) $index;
            }
        }

        return null;
    }

    private function readNarrativeRows(UploadedFile $file): array
    {
        $lines = match (Str::lower($file->getClientOriginalExtension())) {
            'pdf' => $this->readPdfTextLines($file->getRealPath()),
            'docx' => $this->readDocxTextLines($file->getRealPath()),
            default => [],
        };
        $rows = [$this->templateHeaders()];
        $currentTeacher = '';

        foreach ($lines as $line) {
            if (preg_match('/\b(?:professeur|enseignant|nom)\s*[:\-]\s*(.+)$/ui', $line, $teacherMatch) === 1) {
                $currentTeacher = trim($teacherMatch[1], " \t\n\r\0\x0B|;,-");
            }

            $candidate = $this->narrativeCandidate($line, $currentTeacher);
            if ($candidate !== null) {
                $rows[] = $candidate;
            }
        }

        return count($rows) > 1 ? $rows : [];
    }

    private function narrativeCandidate(string $line, string $currentTeacher): ?array
    {
        if (preg_match('/\b(lundi|mardi|mercredi|jeudi|vendredi|samedi)\b/ui', $line, $dayMatch, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }
        if (preg_match(
            '/\b([01]?\d|2[0-3])(?:\s*(?:h|:|\.)\s*([0-5]?\d))?\s*(?:à|a|au|\-|–|—)\s*([01]?\d|2[0-3])(?:\s*(?:h|:|\.)\s*([0-5]?\d))?\b/ui',
            $line,
            $timeMatch,
        ) !== 1) {
            return null;
        }

        $day = $dayMatch[1][0];
        $prefix = trim(substr($line, 0, $dayMatch[1][1]), " \t\n\r\0\x0B|;,-");
        $prefix = preg_replace('/^(?:professeur|enseignant|nom)\s*[:\-]\s*/ui', '', $prefix) ?? $prefix;
        $teacher = filled($prefix) ? trim($prefix) : $currentTeacher;
        $startsAt = sprintf('%02d:%02d', (int) $timeMatch[1], (int) ($timeMatch[2] ?: 0));
        $endsAt = sprintf('%02d:%02d', (int) $timeMatch[3], (int) ($timeMatch[4] ?: 0));
        $normalizedLine = $this->normalizeKey($line);
        $status = match (true) {
            str_contains($normalizedLine, 'indisponible') => 'Indisponible',
            str_contains($normalizedLine, 'prefere') || str_contains($normalizedLine, 'preferentiel') => 'Préféré',
            str_contains($normalizedLine, 'disponible') => 'Disponible',
            default => '',
        };

        return [$teacher, $day, $startsAt, $endsAt, $status, ''];
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

        $sheet = simplexml_load_string($sheetXml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if ($sheet === false) {
            return [];
        }
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

        $document = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if ($document === false) {
            return [];
        }
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

    private function readDocxTextLines(string $path): array
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

        $document = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if ($document === false) {
            return [];
        }
        $document->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        return collect($document->xpath('//w:p') ?: [])
            ->map(fn ($paragraph): string => trim(implode(' ', array_map('strval', $paragraph->xpath('.//w:t') ?: []))))
            ->filter()
            ->values()
            ->all();
    }

    private function readPdfRows(string $path): array
    {
        return collect($this->readPdfTextLines($path))
            ->map(fn (string $line): array => $this->splitTextLine($line))
            ->filter(fn (array $row): bool => count($row) > 1)
            ->values()
            ->all();
    }

    private function readPdfTextLines(string $path): array
    {
        try {
            $text = (new PdfTextParser)->parseFile($path)->getText();
        } catch (\Throwable) {
            return [];
        }

        return collect(preg_split('/\R/u', str_replace("\xC2\xA0", ' ', $text)) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
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
        $this->activeTeachers()->each(function (User $teacher) use ($map): void {
            foreach ([$teacher->name, $teacher->username, $teacher->email] as $identifier) {
                if (filled($identifier)) {
                    $map->put($this->normalizeKey((string) $identifier), $teacher);
                }
            }
        });

        return $map;
    }

    private function activeTeachers(): Collection
    {
        return User::query()
            ->role('enseignant')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
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
        $document = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if ($document === false) {
            return [];
        }
        foreach ($document->si as $item) {
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
