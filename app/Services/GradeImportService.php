<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser as PdfTextParser;
use ZipArchive;

class GradeImportService
{
    public function templateHeaders(): array
    {
        return [
            'Matricule',
            'Nom et prénom',
            'Note',
            'Statut',
            'Commentaire',
        ];
    }

    public function templateRows(Assessment $assessment): array
    {
        $students = $this->studentsForAssessment($assessment);
        $gradesByStudent = Grade::query()
            ->where('assessment_id', $assessment->id)
            ->get()
            ->keyBy('student_id');

        return $students
            ->map(function (Student $student) use ($gradesByStudent) {
                $grade = $gradesByStudent->get($student->id);

                $status = $grade?->resolvedStatus() ?? Grade::STATUS_GRADED;

                return [
                    $student->matricule,
                    $student->full_name,
                    ($grade && $grade->isCounted()) ? $grade->score : null,
                    $this->gradeStatusLabel($status),
                    $grade?->comment,
                ];
            })
            ->values()
            ->all();
    }

    public function preview(UploadedFile $file, Assessment $assessment): array
    {
        $rawRows = $this->readRows($file);
        $headers = array_shift($rawRows) ?? [];
        $mappedHeaders = $this->mapHeaders($headers);
        $studentsByMatricule = $this->studentsForAssessment($assessment)->keyBy(fn (Student $student) => $this->normalizeKey($student->matricule));
        $studentsByName = $this->studentsForAssessment($assessment)->keyBy(fn (Student $student) => $this->normalizeKey($student->full_name));
        $seenStudents = [];

        $rows = collect($rawRows)
            ->filter(fn (array $row) => collect($row)->contains(fn ($value) => filled(trim((string) $value))))
            ->values()
            ->map(function (array $row, int $index) use ($mappedHeaders, $studentsByMatricule, $studentsByName, $assessment, &$seenStudents) {
                return $this->previewRow($row, $index + 2, $mappedHeaders, $studentsByMatricule, $studentsByName, $assessment, $seenStudents);
            })
            ->values();

        return [
            'assessment_id' => $assessment->id,
            'headers' => $headers,
            'rows' => $rows->all(),
            'summary' => [
                'total' => $rows->count(),
                'valid' => $rows->where('status', 'valid')->count(),
                'invalid' => $rows->where('status', 'invalid')->count(),
                'updates' => $rows->where('will_update', true)->count(),
            ],
        ];
    }

    public function import(array $preview, Assessment $assessment, ?int $enteredBy): array
    {
        $rows = collect($preview['rows'] ?? [])->where('status', 'valid')->values();
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, $assessment, $enteredBy, &$created, &$updated) {
            foreach ($rows as $row) {
                $data = $row['data'];
                $status = $data['status'] ?? Grade::STATUS_GRADED;
                $isAbsent = $status === Grade::STATUS_ABSENT;

                $grade = Grade::query()->updateOrCreate(
                    [
                        'assessment_id' => $assessment->id,
                        'student_id' => $data['student_id'],
                    ],
                    [
                        'score' => $status === Grade::STATUS_GRADED ? $data['score'] : null,
                        'is_absent' => $isAbsent,
                        'status' => $status,
                        'comment' => $data['comment'],
                        'entered_by' => $enteredBy,
                    ],
                );

                $grade->wasRecentlyCreated ? $created++ : $updated++;
            }
        });

        return [
            'created' => $created,
            'updated' => $updated,
        ];
    }

    private function previewRow(array $row, int $lineNumber, array $mappedHeaders, $studentsByMatricule, $studentsByName, Assessment $assessment, array &$seenStudents): array
    {
        $raw = [];

        foreach ($row as $index => $value) {
            $field = $mappedHeaders[$index] ?? null;

            if ($field) {
                $raw[$field] = trim((string) $value);
            }
        }

        $student = $this->findStudent($raw, $studentsByMatricule, $studentsByName);
        $status = $this->normalizeGradeStatus($raw['status'] ?? $raw['is_absent'] ?? null);
        $isAbsent = $status === Grade::STATUS_ABSENT;
        $score = $this->normalizeScore($raw['score'] ?? null);
        $errors = [];
        $warnings = [];

        if (! $student) {
            $errors[] = 'Élève introuvable dans cette classe. Vérifie le matricule.';
        }

        if ($student && isset($seenStudents[$student->id])) {
            $errors[] = 'Doublon dans le fichier avec la ligne '.$seenStudents[$student->id].'.';
        } elseif ($student) {
            $seenStudents[$student->id] = $lineNumber;
        }

        if ($score === false) {
            $errors[] = 'Note invalide.';
        }

        if ($status === Grade::STATUS_GRADED && $score === null) {
            $errors[] = 'Note obligatoire lorsque le statut est "Note saisie".';
        }

        if (is_numeric($score) && ((float) $score < 0 || (float) $score > (float) $assessment->max_score)) {
            $errors[] = 'Note hors barème. Maximum autorisé : '.number_format((float) $assessment->max_score, 0, ',', ' ').'.';
        }

        if ($status !== Grade::STATUS_GRADED) {
            $score = null;
        }

        $existingGrade = $student
            ? Grade::query()->where('assessment_id', $assessment->id)->where('student_id', $student->id)->exists()
            : false;

        if ($existingGrade) {
            $warnings[] = 'Une note existe déjà, elle sera mise à jour.';
        }

        return [
            'line' => $lineNumber,
            'student_label' => $student?->full_name ?? ($raw['student_name'] ?? ''),
            'matricule' => $student?->matricule ?? ($raw['matricule'] ?? ''),
            'status' => filled($errors) ? 'invalid' : 'valid',
            'will_update' => $existingGrade && blank($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'data' => [
                'student_id' => $student?->id,
                'score' => $score === false ? null : $score,
                'is_absent' => $isAbsent,
                'status' => $status,
                'status_label' => $this->gradeStatusLabel($status),
                'comment' => $this->clean($raw['comment'] ?? null),
            ],
        ];
    }

    private function findStudent(array $raw, $studentsByMatricule, $studentsByName): ?Student
    {
        $matricule = $this->normalizeKey($raw['matricule'] ?? '');

        if ($matricule && $studentsByMatricule->has($matricule)) {
            return $studentsByMatricule->get($matricule);
        }

        $name = $this->normalizeKey($raw['student_name'] ?? '');

        return $name ? $studentsByName->get($name) : null;
    }

    private function studentsForAssessment(Assessment $assessment)
    {
        return Enrollment::query()
            ->with('student')
            ->where('academic_year_id', $assessment->academic_year_id)
            ->where('school_class_id', $assessment->school_class_id)
            ->where('enrollments.status', 'active')
            ->whereHas('student', fn ($query) => $query->where('students.status', 'active'))
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->orderBy('students.last_name')
            ->orderBy('students.first_name')
            ->select('enrollments.*')
            ->get()
            ->pluck('student')
            ->filter()
            ->values();
    }

    private function readRows(UploadedFile $file): array
    {
        $extension = Str::lower($file->getClientOriginalExtension());

        if ($extension === 'xlsx') {
            return $this->readXlsxRows($file->getRealPath());
        }

        if ($extension === 'pdf') {
            return $this->readPdfRows($file->getRealPath());
        }

        return $this->readCsvRows($file->getRealPath());
    }

    private function readCsvRows(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $delimiter = $this->detectDelimiter($lines[0] ?? '');

        return collect($lines)
            ->map(function (string $line) use ($delimiter) {
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;

                return str_getcsv($line, $delimiter);
            })
            ->all();
    }

    private function readXlsxRows(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            return [];
        }

        $sheet = simplexml_load_string($sheetXml);
        $rows = [];

        foreach ($sheet->sheetData->row as $row) {
            $values = [];

            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                $columnIndex = $this->columnIndex($reference);
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

    private function readPdfRows(string $path): array
    {
        try {
            $text = (new PdfTextParser)->parseFile($path)->getText();
        } catch (\Throwable) {
            return [];
        }

        return $this->parseDelimitedTextRows($text);
    }

    private function parseDelimitedTextRows(string $text): array
    {
        $lines = preg_split('/\R/u', str_replace("\xC2\xA0", ' ', $text)) ?: [];

        return collect($lines)
            ->map(fn (string $line) => trim(preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line))
            ->filter()
            ->map(fn (string $line) => $this->splitTextLine($line))
            ->filter(fn (array $row) => count($row) > 1)
            ->values()
            ->all();
    }

    private function splitTextLine(string $line): array
    {
        if (str_contains($line, ';')) {
            return str_getcsv($line, ';');
        }

        if (str_contains($line, "\t")) {
            return str_getcsv($line, "\t");
        }

        if (preg_match('/\s{2,}/', $line)) {
            return preg_split('/\s{2,}/', $line) ?: [];
        }

        if (substr_count($line, ',') >= 3) {
            return str_getcsv($line, ',');
        }

        return [$line];
    }

    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $sharedStrings = [];
        $document = simplexml_load_string($xml);

        foreach ($document->si as $item) {
            if (isset($item->t)) {
                $sharedStrings[] = (string) $item->t;

                continue;
            }

            $sharedStrings[] = collect($item->r ?? [])
                ->map(fn ($run) => (string) $run->t)
                ->implode('');
        }

        return $sharedStrings;
    }

    private function mapHeaders(array $headers): array
    {
        $aliases = [
            'matricule' => 'matricule',
            'numero_matricule' => 'matricule',
            'nom_et_prenom' => 'student_name',
            'nom_prenom' => 'student_name',
            'eleve' => 'student_name',
            'nom' => 'student_name',
            'prenoms' => 'student_name',
            'prenom' => 'student_name',
            'note' => 'score',
            'score' => 'score',
            'absent' => 'is_absent',
            'absence' => 'is_absent',
            'statut' => 'status',
            'status' => 'status',
            'dispense' => 'status',
            'dispensee' => 'status',
            'malade' => 'status',
            'commentaire' => 'comment',
            'observation' => 'comment',
        ];

        return collect($headers)
            ->map(fn ($header) => $aliases[$this->normalizeKey((string) $header)] ?? null)
            ->all();
    }

    private function normalizeScore(?string $value): float|false|null
    {
        if (blank($value)) {
            return null;
        }

        $value = str_replace(',', '.', trim($value));

        return is_numeric($value) ? (float) $value : false;
    }

    private function normalizeBoolean(?string $value): ?bool
    {
        if (blank($value)) {
            return null;
        }

        return match ($this->normalizeKey($value)) {
            'oui', 'o', 'yes', 'true', '1', 'absent' => true,
            'non', 'n', 'no', 'false', '0', 'present' => false,
            default => null,
        };
    }

    private function normalizeGradeStatus(?string $value): string
    {
        if (blank($value)) {
            return Grade::STATUS_GRADED;
        }

        return match ($this->normalizeKey($value)) {
            'oui', 'o', 'yes', 'true', '1', 'absent', 'absence', 'a' => Grade::STATUS_ABSENT,
            'dispense', 'dispensee', 'dispense_e', 'd' => Grade::STATUS_DISPENSED,
            'malade', 'maladie', 'm' => Grade::STATUS_SICK,
            'non', 'n', 'no', 'false', '0', 'present', 'presente', 'note', 'note_saisie', 'graded' => Grade::STATUS_GRADED,
            Grade::STATUS_ABSENT => Grade::STATUS_ABSENT,
            Grade::STATUS_DISPENSED => Grade::STATUS_DISPENSED,
            Grade::STATUS_SICK => Grade::STATUS_SICK,
            default => Grade::STATUS_GRADED,
        };
    }

    private function gradeStatusLabel(string $status): string
    {
        return Grade::statusLabels()[$status] ?? $status;
    }

    private function detectDelimiter(string $line): string
    {
        $delimiters = [';' => substr_count($line, ';'), ',' => substr_count($line, ','), "\t" => substr_count($line, "\t")];

        arsort($delimiters);

        return array_key_first($delimiters) ?: ';';
    }

    private function columnIndex(string $reference): int
    {
        preg_match('/[A-Z]+/', $reference, $matches);
        $letters = $matches[0] ?? 'A';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private function normalizeKey(string $value): string
    {
        return (string) Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
