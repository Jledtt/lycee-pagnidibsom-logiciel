<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Rules\PlausibleStudentBirthDate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser as PdfTextParser;
use ZipArchive;

class StudentImportService
{
    public function __construct(private readonly MatriculeGeneratorService $matriculeGenerator) {}

    public function templateHeaders(): array
    {
        return [
            'Nom',
            'Prénom',
            'Sexe',
            'Date de naissance',
            'Lieu de naissance',
            'Classe souhaitée',
            'École d’origine',
            'Classe fréquentée',
            'Classe redoublée',
            'Secteur',
            'Quartier',
            'Téléphone du domicile',
            'Nationalité',
            'Ethnie',
            'Religion',
            'Nom du père',
            'Prénom du père',
            'Téléphone du père',
            'Profession du père',
            'Service du père',
            'Nom de la mère',
            'Prénom de la mère',
            'Téléphone de la mère',
            'Profession de la mère',
            'Service de la mère',
            'Nom du contact d’urgence',
            'Téléphone du contact d’urgence',
            'WhatsApp de l’école',
            'Aptitude sportive',
        ];
    }

    public function templateRows(): array
    {
        return [[
            'Ouedraogo',
            'Awa',
            'Fille',
            '15/09/2012',
            'Ouagadougou',
            '5e A',
            'École primaire exemple',
            'CM2',
            'Aucune',
            '04',
            'Pagnidibsom',
            '70000000',
            'Burkinabè',
            'Mossi',
            'Chrétienne',
            'Ouedraogo',
            'Adama',
            '71000000',
            'Commerçant',
            'Marché',
            'Sawadogo',
            'Aminata',
            '76000000',
            'Menagere',
            '',
            'Ouedraogo Adama',
            '71000000',
            '71000000',
            'Oui',
        ]];
    }

    public function preview(UploadedFile $file, ?AcademicYear $academicYear): array
    {
        $rawRows = $this->readRows($file);
        $headers = array_shift($rawRows) ?? [];
        $mappedHeaders = $this->mapHeaders($headers);
        $classMap = $this->classMap($academicYear);
        $seen = [];

        $rows = collect($rawRows)
            ->filter(fn (array $row) => collect($row)->contains(fn ($value) => filled(trim((string) $value))))
            ->values()
            ->map(function (array $row, int $index) use ($mappedHeaders, $classMap, &$seen) {
                return $this->previewRow($row, $index + 2, $mappedHeaders, $classMap, $seen);
            })
            ->values();

        return [
            'headers' => $headers,
            'rows' => $rows->all(),
            'summary' => [
                'total' => $rows->count(),
                'valid' => $rows->where('status', 'valid')->count(),
                'invalid' => $rows->where('status', 'invalid')->count(),
                'duplicates' => $rows->where('status', 'duplicate')->count(),
            ],
        ];
    }

    public function import(array $preview, ?AcademicYear $academicYear, ?int $createdBy): array
    {
        $rows = collect($preview['rows'] ?? [])->where('status', 'valid')->values();

        return DB::transaction(function () use ($rows, $academicYear, $createdBy) {
            $created = 0;
            $skipped = 0;

            foreach ($rows as $row) {
                $data = $row['data'];

                if ($this->existingDuplicate($data)) {
                    $skipped++;

                    continue;
                }

                $student = Student::query()->create($this->studentPayload($data, $academicYear));
                $this->attachGuardian($student, $data, 'father', 'father');
                $this->attachGuardian($student, $data, 'mother', 'mother');
                $this->createEnrollmentIfPossible($student, $data, $academicYear, $createdBy);

                $created++;
            }

            return [
                'created' => $created,
                'skipped' => $skipped,
            ];
        });
    }

    private function previewRow(array $row, int $lineNumber, array $mappedHeaders, array $classMap, array &$seen): array
    {
        $raw = [];

        foreach ($row as $index => $value) {
            $field = $mappedHeaders[$index] ?? null;

            if ($field) {
                $raw[$field] = trim((string) $value);
            }
        }

        $data = $this->normalizeData($raw, $classMap);
        $errors = $this->validateData($data);
        $warnings = [];
        $duplicateKey = $this->duplicateKey($data);

        if (($data['desired_class'] ?? null) && blank($data['school_class_id'])) {
            $warnings[] = 'Classe non trouvée : l’élève sera créé sans inscription automatique.';
        }

        if (isset($seen[$duplicateKey])) {
            $errors[] = 'Doublon dans le fichier avec la ligne '.$seen[$duplicateKey].'.';
        } elseif ($duplicateKey !== '') {
            $seen[$duplicateKey] = $lineNumber;
        }

        $isExistingDuplicate = $this->existingDuplicate($data);

        if ($isExistingDuplicate) {
            $warnings[] = 'Élève déjà présent dans la base, ligne ignorée.';
        }

        $status = filled($errors)
            ? 'invalid'
            : ($isExistingDuplicate ? 'duplicate' : 'valid');

        return [
            'line' => $lineNumber,
            'display_name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
            'class_label' => $data['desired_class'] ?? '',
            'status' => $status,
            'errors' => $errors,
            'warnings' => $warnings,
            'data' => $data,
        ];
    }

    private function normalizeData(array $raw, array $classMap): array
    {
        $desiredClass = $this->clean($raw['desired_class'] ?? null);

        return [
            'first_name' => $this->clean($raw['first_name'] ?? null),
            'last_name' => $this->clean($raw['last_name'] ?? null),
            'gender' => $this->normalizeGender($raw['gender'] ?? null),
            'birth_date' => $this->normalizeDate($raw['birth_date'] ?? null),
            'birth_place' => $this->clean($raw['birth_place'] ?? null),
            'desired_class' => $desiredClass,
            'school_class_id' => $desiredClass ? ($classMap[$this->normalizeKey($desiredClass)] ?? null) : null,
            'origin_school' => $this->clean($raw['origin_school'] ?? null),
            'previous_class' => $this->clean($raw['previous_class'] ?? null),
            'repeated_class' => $this->clean($raw['repeated_class'] ?? null),
            'sector' => $this->clean($raw['sector'] ?? null),
            'district' => $this->clean($raw['district'] ?? null),
            'home_phone' => $this->clean($raw['home_phone'] ?? null),
            'nationality' => $this->clean($raw['nationality'] ?? null),
            'ethnicity' => $this->clean($raw['ethnicity'] ?? null),
            'religion' => $this->clean($raw['religion'] ?? null),
            'father_last_name' => $this->clean($raw['father_last_name'] ?? null),
            'father_first_name' => $this->clean($raw['father_first_name'] ?? null),
            'father_phone_primary' => $this->clean($raw['father_phone_primary'] ?? null),
            'father_profession' => $this->clean($raw['father_profession'] ?? null),
            'father_service' => $this->clean($raw['father_service'] ?? null),
            'mother_last_name' => $this->clean($raw['mother_last_name'] ?? null),
            'mother_first_name' => $this->clean($raw['mother_first_name'] ?? null),
            'mother_phone_primary' => $this->clean($raw['mother_phone_primary'] ?? null),
            'mother_profession' => $this->clean($raw['mother_profession'] ?? null),
            'mother_service' => $this->clean($raw['mother_service'] ?? null),
            'emergency_contact_name' => $this->clean($raw['emergency_contact_name'] ?? null),
            'emergency_contact_phone' => $this->clean($raw['emergency_contact_phone'] ?? null),
            'school_info_whatsapp' => $this->clean($raw['school_info_whatsapp'] ?? null),
            'sport_aptitude' => $this->normalizeBoolean($raw['sport_aptitude'] ?? null),
        ];
    }

    private function validateData(array $data): array
    {
        $errors = [];

        if (blank($data['last_name'])) {
            $errors[] = 'Nom obligatoire.';
        }

        if (blank($data['first_name'])) {
            $errors[] = 'Prénom obligatoire.';
        }

        if (($data['birth_date'] ?? null) === false) {
            $errors[] = 'Date de naissance invalide. Format conseillé : jj/mm/aaaa.';
        } elseif (filled($data['birth_date'] ?? null) && ! PlausibleStudentBirthDate::isPlausible($data['birth_date'])) {
            $errors[] = PlausibleStudentBirthDate::MESSAGE;
        }

        return $errors;
    }

    private function studentPayload(array $data, ?AcademicYear $academicYear): array
    {
        return [
            'matricule' => $this->matriculeGenerator->generate($academicYear),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'gender' => $data['gender'],
            'birth_date' => $data['birth_date'] ?: null,
            'birth_place' => $data['birth_place'] ?: null,
            'desired_class' => $data['desired_class'] ?: null,
            'origin_school' => $data['origin_school'] ?: null,
            'previous_class' => $data['previous_class'] ?: null,
            'repeated_class' => $data['repeated_class'] ?: null,
            'sector' => $data['sector'] ?: null,
            'district' => $data['district'] ?: null,
            'home_phone' => $data['home_phone'] ?: null,
            'nationality' => $data['nationality'] ?: null,
            'ethnicity' => $data['ethnicity'] ?: null,
            'religion' => $data['religion'] ?: null,
            'emergency_contact_name' => $data['emergency_contact_name'] ?: null,
            'emergency_contact_phone' => $data['emergency_contact_phone'] ?: null,
            'school_info_whatsapp' => $data['school_info_whatsapp'] ?: null,
            'sport_aptitude' => $data['sport_aptitude'],
            'status' => 'active',
        ];
    }

    private function attachGuardian(Student $student, array $data, string $prefix, string $relationship): void
    {
        $phone = $data[$prefix.'_phone_primary'] ?? null;
        $lastName = $data[$prefix.'_last_name'] ?? null;

        if (blank($phone) || blank($lastName)) {
            return;
        }

        $guardian = Guardian::query()->firstOrCreate(
            ['phone_primary' => $phone],
            [
                'first_name' => $data[$prefix.'_first_name'] ?? '',
                'last_name' => $lastName,
                'profession' => $data[$prefix.'_profession'] ?? null,
                'service' => $data[$prefix.'_service'] ?? null,
                'status' => 'active',
            ],
        );

        $guardian->update([
            'first_name' => $data[$prefix.'_first_name'] ?? $guardian->first_name,
            'last_name' => $lastName,
            'profession' => $data[$prefix.'_profession'] ?? $guardian->profession,
            'service' => $data[$prefix.'_service'] ?? $guardian->service,
        ]);

        $student->guardians()->syncWithoutDetaching([
            $guardian->id => [
                'relationship' => $relationship,
                'is_primary' => $relationship === 'father',
                'can_receive_sms' => true,
                'can_pickup_child' => false,
            ],
        ]);
    }

    private function createEnrollmentIfPossible(Student $student, array $data, ?AcademicYear $academicYear, ?int $createdBy): void
    {
        if (! $academicYear || blank($data['school_class_id'])) {
            return;
        }

        Enrollment::query()->firstOrCreate(
            [
                'academic_year_id' => $academicYear->id,
                'student_id' => $student->id,
            ],
            [
                'school_class_id' => $data['school_class_id'],
                'enrollment_date' => now()->toDateString(),
                'type' => 'new',
                'status' => 'active',
                'previous_school' => $data['origin_school'] ?: null,
                'created_by' => $createdBy,
            ],
        );
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
        $aliases = $this->headerAliases();

        return collect($headers)
            ->map(fn ($header) => $aliases[$this->normalizeKey((string) $header)] ?? null)
            ->all();
    }

    private function headerAliases(): array
    {
        return [
            'nom' => 'last_name',
            'last_name' => 'last_name',
            'prenom' => 'first_name',
            'prenoms' => 'first_name',
            'first_name' => 'first_name',
            'sexe' => 'gender',
            'genre' => 'gender',
            'date_naissance' => 'birth_date',
            'date_de_naissance' => 'birth_date',
            'lieu_naissance' => 'birth_place',
            'lieu_de_naissance' => 'birth_place',
            'classe_souhaitee' => 'desired_class',
            'classe' => 'desired_class',
            'ecole_origine' => 'origin_school',
            'ecole_d_origine' => 'origin_school',
            'classe_frequentee' => 'previous_class',
            'classe_precedente' => 'previous_class',
            'classe_redoublee' => 'repeated_class',
            'secteur' => 'sector',
            'quartier' => 'district',
            'telephone_domicile' => 'home_phone',
            'telephone_du_domicile' => 'home_phone',
            'tel_dom' => 'home_phone',
            'nationalite' => 'nationality',
            'ethnie' => 'ethnicity',
            'religion' => 'religion',
            'pere_nom' => 'father_last_name',
            'nom_pere' => 'father_last_name',
            'nom_du_pere' => 'father_last_name',
            'pere_prenom' => 'father_first_name',
            'prenom_pere' => 'father_first_name',
            'prenom_du_pere' => 'father_first_name',
            'pere_telephone' => 'father_phone_primary',
            'telephone_pere' => 'father_phone_primary',
            'telephone_du_pere' => 'father_phone_primary',
            'pere_profession' => 'father_profession',
            'profession_pere' => 'father_profession',
            'profession_du_pere' => 'father_profession',
            'pere_service' => 'father_service',
            'service_pere' => 'father_service',
            'service_du_pere' => 'father_service',
            'mere_nom' => 'mother_last_name',
            'nom_mere' => 'mother_last_name',
            'nom_de_la_mere' => 'mother_last_name',
            'mere_prenom' => 'mother_first_name',
            'prenom_mere' => 'mother_first_name',
            'prenom_de_la_mere' => 'mother_first_name',
            'mere_telephone' => 'mother_phone_primary',
            'telephone_mere' => 'mother_phone_primary',
            'telephone_de_la_mere' => 'mother_phone_primary',
            'mere_profession' => 'mother_profession',
            'profession_mere' => 'mother_profession',
            'profession_de_la_mere' => 'mother_profession',
            'mere_service' => 'mother_service',
            'service_mere' => 'mother_service',
            'service_de_la_mere' => 'mother_service',
            'contact_urgence_nom' => 'emergency_contact_name',
            'personne_a_prevenir' => 'emergency_contact_name',
            'nom_du_contact_d_urgence' => 'emergency_contact_name',
            'contact_urgence_telephone' => 'emergency_contact_phone',
            'telephone_urgence' => 'emergency_contact_phone',
            'telephone_du_contact_d_urgence' => 'emergency_contact_phone',
            'whatsapp_ecole' => 'school_info_whatsapp',
            'no_whatsapp' => 'school_info_whatsapp',
            'whatsapp_de_l_ecole' => 'school_info_whatsapp',
            'aptitude_sport' => 'sport_aptitude',
            'aptitude_sportive' => 'sport_aptitude',
            'sport' => 'sport_aptitude',
        ];
    }

    private function classMap(?AcademicYear $academicYear): array
    {
        return SchoolClass::query()
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('status', 'active')
            ->get()
            ->mapWithKeys(fn (SchoolClass $class) => [$this->normalizeKey($class->name) => $class->id])
            ->all();
    }

    private function existingDuplicate(array $data): bool
    {
        if (blank($data['first_name'] ?? null) || blank($data['last_name'] ?? null)) {
            return false;
        }

        return Student::query()
            ->whereRaw('lower(first_name) = ?', [Str::lower($data['first_name'])])
            ->whereRaw('lower(last_name) = ?', [Str::lower($data['last_name'])])
            ->when($data['birth_date'] ?? null, fn ($query, string $date) => $query->whereDate('birth_date', $date))
            ->exists();
    }

    private function duplicateKey(array $data): string
    {
        if (blank($data['first_name'] ?? null) || blank($data['last_name'] ?? null)) {
            return '';
        }

        return $this->normalizeKey($data['first_name'].' '.$data['last_name'].' '.($data['birth_date'] ?? ''));
    }

    private function normalizeGender(?string $value): ?string
    {
        $value = $this->normalizeKey((string) $value);

        return match ($value) {
            'f', 'fille', 'female', 'feminin', 'femme' => 'female',
            'm', 'garcon', 'garcons', 'male', 'masculin', 'homme' => 'male',
            default => null,
        };
    }

    private function normalizeBoolean(?string $value): ?bool
    {
        if (blank($value)) {
            return null;
        }

        return match ($this->normalizeKey($value)) {
            'oui', 'o', 'yes', 'true', '1' => true,
            'non', 'n', 'no', 'false', '0' => false,
            default => null,
        };
    }

    private function normalizeDate(?string $value): string|false|null
    {
        if (blank($value)) {
            return null;
        }

        $value = trim($value);

        if (is_numeric($value) && (float) $value > 25000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->toDateString();
            } catch (\Throwable) {
            }
        }

        return false;
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
