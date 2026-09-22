<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Smalot\PdfParser\Parser as PdfTextParser;

class ClassSubjectPdfImportService
{
    public function __construct(
        private readonly PagnidibsomClassSubjectSetupService $officialSetup,
    ) {}

    public function preview(UploadedFile $file, ?AcademicYear $academicYear): array
    {
        if (! $academicYear) {
            throw new InvalidArgumentException('Active d’abord une année scolaire avant d’importer les matières.');
        }

        $classes = SchoolClass::query()
            ->with(['level', 'academicTrack', 'classSubjects.subject'])
            ->where('academic_year_id', $academicYear->id)
            ->where('status', 'active')
            ->get();

        if ($classes->isEmpty()) {
            throw new InvalidArgumentException('Aucune classe active n’est disponible pour cette année scolaire.');
        }

        $parsedGroups = $this->parsePdf($file->getRealPath(), $classes);

        if ($parsedGroups === []) {
            throw new InvalidArgumentException(
                'Aucune liste de matières lisible n’a été trouvée. Utilise un PDF avec du texte sélectionnable, et non une image scannée.',
            );
        }

        $warnings = [];
        $rows = [];
        $seen = [];

        foreach ($parsedGroups as $group) {
            $schoolClass = $this->findClass($group['class_label'], $classes);

            if (! $schoolClass) {
                $warnings[] = 'La classe « '.$group['class_label'].' » n’existe pas parmi les classes actives.';
            }

            foreach ($group['subjects'] as $rawSubject) {
                $subjectData = $this->subjectData($rawSubject);

                if (! $subjectData) {
                    continue;
                }

                $deduplicationKey = ($schoolClass?->id ?? $this->normalizeClassKey($group['class_label']))
                    .'|'.($subjectData['code'] ?? $this->normalizeText($subjectData['name']));

                if (isset($seen[$deduplicationKey])) {
                    continue;
                }

                $seen[$deduplicationKey] = true;
                $subject = $this->findSubject($subjectData['name'], $subjectData['code']);
                $assignment = $schoolClass && $subject
                    ? $schoolClass->classSubjects->firstWhere('subject_id', $subject->id)
                    : null;
                $action = $this->previewAction($subject, $assignment);

                $rows[] = [
                    'school_class_id' => $schoolClass?->id,
                    'class_label' => $group['class_label'],
                    'class_name' => $schoolClass?->name ?? $group['class_label'],
                    'subject_name' => $subjectData['name'],
                    'subject_code' => $subjectData['code'],
                    'coefficient' => $schoolClass
                        ? $this->coefficientFor($schoolClass, $subjectData['code'], $assignment)
                        : 1,
                    'action' => $action,
                    'action_label' => $this->actionLabel($action),
                    'valid' => (bool) $schoolClass,
                    'error' => $schoolClass ? null : 'Classe active introuvable.',
                ];
            }
        }

        if ($rows === []) {
            throw new InvalidArgumentException('Le PDF ne contient aucune matière exploitable.');
        }

        $validRows = collect($rows)->where('valid', true);

        return [
            'source_name' => $file->getClientOriginalName(),
            'academic_year_id' => $academicYear->id,
            'academic_year_name' => $academicYear->name,
            'rows' => $rows,
            'groups' => $this->groupRows($rows),
            'warnings' => array_values(array_unique($warnings)),
            'summary' => [
                'classes' => $validRows->pluck('school_class_id')->unique()->count(),
                'total' => count($rows),
                'valid' => $validRows->count(),
                'invalid' => collect($rows)->where('valid', false)->count(),
                'new_subjects' => $validRows->where('action', 'create_subject')->count(),
                'new_assignments' => $validRows->whereIn('action', ['create_subject', 'create_assignment'])->count(),
                'reactivated' => $validRows->whereIn('action', ['reactivate_subject', 'reactivate_assignment'])->count(),
                'unchanged' => $validRows->where('action', 'unchanged')->count(),
            ],
        ];
    }

    public function import(array $preview, ?AcademicYear $academicYear): array
    {
        if (! $academicYear || (int) ($preview['academic_year_id'] ?? 0) !== $academicYear->id) {
            throw new InvalidArgumentException(
                'L’année scolaire active a changé depuis l’aperçu. Analyse de nouveau le PDF avant de confirmer.',
            );
        }

        $rows = collect($preview['rows'] ?? [])->where('valid', true)->values();

        if ($rows->isEmpty()) {
            throw new InvalidArgumentException('Aucune matière valide ne peut être importée.');
        }

        return DB::transaction(function () use ($academicYear, $rows): array {
            $result = [
                'subjects_created' => 0,
                'subjects_reactivated' => 0,
                'assignments_created' => 0,
                'assignments_reactivated' => 0,
                'unchanged' => 0,
            ];

            $classes = SchoolClass::query()
                ->where('academic_year_id', $academicYear->id)
                ->where('status', 'active')
                ->whereIn('id', $rows->pluck('school_class_id')->filter()->unique())
                ->get()
                ->keyBy('id');

            foreach ($rows as $row) {
                $schoolClass = $classes->get((int) $row['school_class_id']);

                if (! $schoolClass) {
                    throw new InvalidArgumentException(
                        'Une classe de l’aperçu n’est plus active. Analyse de nouveau le PDF.',
                    );
                }

                $subject = $this->findSubject($row['subject_name'], $row['subject_code'] ?? null);

                if (! $subject) {
                    $subject = Subject::query()->create([
                        'name' => $row['subject_name'],
                        'code' => $row['subject_code'] ?? null,
                        'status' => 'active',
                    ]);
                    $result['subjects_created']++;
                } elseif ($subject->status !== 'active') {
                    $subject->update(['status' => 'active']);
                    $result['subjects_reactivated']++;
                }

                $assignment = ClassSubject::query()->firstOrNew([
                    'school_class_id' => $schoolClass->id,
                    'subject_id' => $subject->id,
                ]);

                if (! $assignment->exists) {
                    $assignment->coefficient = $row['coefficient'] ?? 1;
                    $assignment->is_active = true;
                    $assignment->save();
                    $result['assignments_created']++;

                    continue;
                }

                if (! $assignment->is_active) {
                    $assignment->update(['is_active' => true]);
                    $result['assignments_reactivated']++;

                    continue;
                }

                $result['unchanged']++;
            }

            return $result;
        });
    }

    private function parsePdf(string $path, Collection $classes): array
    {
        try {
            $document = (new PdfTextParser)->parseFile($path);
        } catch (\Throwable) {
            return [];
        }

        $groups = [];

        foreach ($document->getPages() as $page) {
            $pageGroups = $this->parseColumnPage($page->getDataTm());

            if ($pageGroups === []) {
                $pageGroups = $this->parseSectionText($page->getText(), $classes);
            }

            foreach ($pageGroups as $group) {
                $key = $this->normalizeClassKey($group['class_label']);

                if (! isset($groups[$key])) {
                    $groups[$key] = [
                        'class_label' => $this->cleanLabel($group['class_label']),
                        'subjects' => [],
                    ];
                }

                $groups[$key]['subjects'] = array_values(array_unique([
                    ...$groups[$key]['subjects'],
                    ...$group['subjects'],
                ]));
            }
        }

        return array_values(array_filter(
            $groups,
            fn (array $group): bool => $group['subjects'] !== [],
        ));
    }

    private function parseColumnPage(array $data): array
    {
        $tokens = collect($data)
            ->filter(fn ($item): bool => is_array($item)
                && isset($item[0][4], $item[0][5], $item[1]))
            ->map(fn (array $item): array => [
                'x' => (float) $item[0][4],
                'y' => (float) $item[0][5],
                'text' => (string) $item[1],
            ])
            ->values();

        $clusters = [];

        foreach ($tokens->filter(fn (array $token): bool => trim($token['text']) === '-') as $hyphen) {
            $clusterIndex = collect($clusters)->search(
                fn (array $cluster): bool => abs($cluster['x'] - $hyphen['x']) < 2.5,
            );

            if ($clusterIndex === false) {
                $clusters[] = ['x' => $hyphen['x'], 'bullets' => [$hyphen]];
            } else {
                $clusters[$clusterIndex]['bullets'][] = $hyphen;
            }
        }

        $clusters = collect($clusters)
            ->filter(fn (array $cluster): bool => collect($cluster['bullets'])->pluck('y')->unique()->count() >= 2)
            ->sortBy('x')
            ->values();

        if ($clusters->count() < 2) {
            return [];
        }

        $groups = [];

        foreach ($clusters as $index => $cluster) {
            $left = $index === 0
                ? $cluster['x'] - 28
                : ($clusters[$index - 1]['x'] + $cluster['x']) / 2;
            $right = $index === $clusters->count() - 1
                ? $cluster['x'] + 120
                : ($cluster['x'] + $clusters[$index + 1]['x']) / 2;
            $bulletYs = collect($cluster['bullets'])->pluck('y')->sortDesc()->values();
            $topBulletY = (float) $bulletYs->first();
            $header = $tokens
                ->filter(fn (array $token): bool => $token['x'] >= $left
                    && $token['x'] < $right
                    && $token['y'] > $topBulletY + 5
                    && $token['y'] < $topBulletY + 72)
                ->sortBy('x')
                ->pluck('text')
                ->implode('');
            $header = $this->cleanLabel($header);

            if ($header === '' || strlen($this->normalizeClassKey($header)) < 2) {
                continue;
            }

            $subjects = [];

            foreach ($bulletYs as $bulletIndex => $bulletY) {
                $nextBulletY = $bulletYs[$bulletIndex + 1] ?? ($bulletY - 48);
                $subjectTokens = $tokens
                    ->filter(fn (array $token): bool => $token['x'] > $cluster['x'] + 3
                        && $token['x'] < $right
                        && $token['y'] <= $bulletY + 3
                        && $token['y'] > $nextBulletY + 3)
                    ->sort(function (array $leftToken, array $rightToken): int {
                        if (abs($leftToken['y'] - $rightToken['y']) > 1.5) {
                            return $leftToken['y'] > $rightToken['y'] ? -1 : 1;
                        }

                        return $leftToken['x'] <=> $rightToken['x'];
                    })
                    ->values();

                $lines = [];

                foreach ($subjectTokens as $token) {
                    $lineKey = collect(array_keys($lines))->first(
                        fn ($lineY): bool => abs((float) $lineY - $token['y']) <= 1.5,
                    );
                    $lineKey ??= (string) $token['y'];
                    $lines[$lineKey] = ($lines[$lineKey] ?? '').$token['text'];
                }

                $subject = '';

                foreach ($lines as $line) {
                    $line = trim($line);
                    $subject .= $subject !== '' && ! str_ends_with($subject, '-') ? ' '.$line : $line;
                }

                $subject = $this->cleanSubjectLabel($subject);

                if ($subject !== '') {
                    $subjects[] = $subject;
                }
            }

            if ($subjects !== []) {
                $groups[] = [
                    'class_label' => $header,
                    'subjects' => array_values(array_unique($subjects)),
                ];
            }
        }

        return $groups;
    }

    private function parseSectionText(string $text, Collection $classes): array
    {
        $knownKeys = $classes
            ->flatMap(fn (SchoolClass $class): array => $this->classAliases($class))
            ->flip();
        $groups = [];
        $currentKey = null;

        foreach (preg_split('/\R/u', str_replace("\xC2\xA0", ' ', $text)) ?: [] as $line) {
            $line = $this->cleanLabel($line);

            if ($line === '') {
                continue;
            }

            $lineKey = $this->normalizeClassKey($line);

            if ($knownKeys->has($lineKey) || $this->looksLikeClassName($lineKey)) {
                $currentKey = $lineKey;
                $groups[$currentKey] ??= ['class_label' => $line, 'subjects' => []];

                continue;
            }

            if (! $currentKey || ! preg_match('/^(?:-|•)\s*(.+)$/u', $line, $matches)) {
                continue;
            }

            $subject = $this->cleanSubjectLabel($matches[1]);

            if ($subject !== '') {
                $groups[$currentKey]['subjects'][] = $subject;
            }
        }

        return array_values($groups);
    }

    private function findClass(string $label, Collection $classes): ?SchoolClass
    {
        $key = $this->normalizeClassKey($label);

        return $classes->first(
            fn (SchoolClass $class): bool => in_array($key, $this->classAliases($class), true),
        );
    }

    private function classAliases(SchoolClass $class): array
    {
        $aliases = [$class->name, $class->code];

        if ($class->level) {
            $aliases[] = $class->level->name;

            if ($class->academicTrack) {
                $aliases[] = trim($class->level->name.' '.$class->academicTrack->code);
                $aliases[] = trim($class->level->name.' '.$class->academicTrack->name);
            }
        }

        return collect($aliases)
            ->filter()
            ->map(fn (string $alias): string => $this->normalizeClassKey($alias))
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeClassKey(string $value): string
    {
        $key = $this->normalizeText($value);
        $key = preg_replace('/^(?:classe)/', '', $key) ?? $key;
        $key = preg_replace('/^([3-6])eme/', '$1e', $key) ?? $key;
        $key = preg_replace('/^2(?:de|eme|nde)/', '2nde', $key) ?? $key;
        $key = preg_replace('/^1(?:re|ere|eme)/', '1ere', $key) ?? $key;

        return $key;
    }

    private function looksLikeClassName(string $key): bool
    {
        return (bool) preg_match('/^(?:[3-6]e|2nde|1ere|terminale)[a-z0-9]*$/', $key);
    }

    private function subjectData(string $label): ?array
    {
        $name = $this->cleanSubjectLabel($label);

        if ($name === '') {
            return null;
        }

        $key = $this->normalizeText($name);
        $catalog = [
            'francais' => ['name' => 'Français', 'code' => 'FR'],
            'fr' => ['name' => 'Français', 'code' => 'FR'],
            'mathematiques' => ['name' => 'Mathématiques', 'code' => 'MATH'],
            'mathematique' => ['name' => 'Mathématiques', 'code' => 'MATH'],
            'maths' => ['name' => 'Mathématiques', 'code' => 'MATH'],
            'math' => ['name' => 'Mathématiques', 'code' => 'MATH'],
            'anglais' => ['name' => 'Anglais', 'code' => 'ANG'],
            'ang' => ['name' => 'Anglais', 'code' => 'ANG'],
            'svt' => ['name' => 'SVT', 'code' => 'SVT'],
            'histoiregeographie' => ['name' => 'Histoire-Géographie', 'code' => 'HG'],
            'histgeo' => ['name' => 'Histoire-Géographie', 'code' => 'HG'],
            'hg' => ['name' => 'Histoire-Géographie', 'code' => 'HG'],
            'eps' => ['name' => 'EPS', 'code' => 'EPS'],
            'educationciviqueetmorale' => ['name' => 'Éducation civique et morale', 'code' => 'ECM'],
            'educationciviquemorale' => ['name' => 'Éducation civique et morale', 'code' => 'ECM'],
            'ecm' => ['name' => 'Éducation civique et morale', 'code' => 'ECM'],
            'physiquechimie' => ['name' => 'Physique-Chimie', 'code' => 'PC'],
            'sciencesphysiques' => ['name' => 'Physique-Chimie', 'code' => 'PC'],
            'pc' => ['name' => 'Physique-Chimie', 'code' => 'PC'],
            'allemand' => ['name' => 'Allemand', 'code' => 'ALL'],
            'all' => ['name' => 'Allemand', 'code' => 'ALL'],
            'philosophie' => ['name' => 'Philosophie', 'code' => 'PHILO'],
            'philo' => ['name' => 'Philosophie', 'code' => 'PHILO'],
            'technologiesdelinformationetdelacommunication' => [
                'name' => 'Technologies de l’information et de la communication',
                'code' => 'TIC',
            ],
            'tic' => ['name' => 'Technologies de l’information et de la communication', 'code' => 'TIC'],
        ];

        return $catalog[$key] ?? ['name' => $name, 'code' => null];
    }

    private function findSubject(string $name, ?string $code): ?Subject
    {
        return Subject::query()
            ->when($code, fn ($query) => $query->where('code', $code))
            ->when(! $code, fn ($query) => $query->where('name', $name))
            ->first()
            ?? Subject::query()->where('name', $name)->first();
    }

    private function coefficientFor(
        SchoolClass $schoolClass,
        ?string $code,
        ?ClassSubject $assignment,
    ): float|int {
        if ($assignment) {
            return (float) $assignment->coefficient;
        }

        $suggestion = collect($this->officialSetup->suggestedSubjectsForClass($schoolClass))
            ->firstWhere('code', $code);

        return $suggestion['coefficient'] ?? 1;
    }

    private function previewAction(?Subject $subject, ?ClassSubject $assignment): string
    {
        if (! $subject) {
            return 'create_subject';
        }

        if ($subject->status !== 'active') {
            return $assignment ? 'reactivate_subject' : 'create_assignment';
        }

        if (! $assignment) {
            return 'create_assignment';
        }

        return $assignment->is_active ? 'unchanged' : 'reactivate_assignment';
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            'create_subject' => 'Créer la matière et l’affecter',
            'create_assignment' => 'Affecter à la classe',
            'reactivate_subject' => 'Réactiver la matière',
            'reactivate_assignment' => 'Réactiver l’affectation',
            default => 'Déjà enregistrée',
        };
    }

    private function groupRows(array $rows): array
    {
        return collect($rows)
            ->groupBy(fn (array $row): string => ($row['school_class_id'] ?? 'unknown').'-'.$row['class_name'])
            ->map(function (Collection $classRows): array {
                $first = $classRows->first();

                return [
                    'school_class_id' => $first['school_class_id'],
                    'class_name' => $first['class_name'],
                    'valid' => $classRows->every(fn (array $row): bool => $row['valid']),
                    'subjects' => $classRows->pluck('subject_name')->values()->all(),
                    'changes' => $classRows
                        ->where('action', '!=', 'unchanged')
                        ->pluck('action_label')
                        ->unique()
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function cleanLabel(string $value): string
    {
        return Str::of(str_replace("\xC2\xA0", ' ', $value))->squish()->toString();
    }

    private function cleanSubjectLabel(string $value): string
    {
        $value = preg_replace('/^(?:-|•)\s*/u', '', $this->cleanLabel($value)) ?? $value;

        return trim($value, " \t\n\r\0\x0B;,:|");
    }

    private function normalizeText(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->toString();
    }
}
