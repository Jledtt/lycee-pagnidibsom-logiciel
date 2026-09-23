<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\TeacherAvailability;
use App\Models\TeacherAvailabilitySchedule;
use App\Models\Timetable;
use App\Models\TimetableEntry;
use App\Models\TimetableGenerationRun;
use App\Models\TimetablePeriod;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class TimetableGenerationService
{
    public function __construct(
        private readonly TimetableTemplateService $templates,
    ) {}

    /**
     * @param  array<int, int|string>  $schoolClassIds
     */
    public function readiness(AcademicYear $academicYear, array $schoolClassIds = []): array
    {
        $prepared = $this->prepare($academicYear, $schoolClassIds);

        return [
            'blockers' => $prepared['diagnostics']['blockers'],
            'warnings' => $prepared['diagnostics']['warnings'],
            'counts' => $prepared['diagnostics']['counts'],
        ];
    }

    /**
     * @param  array<int, int|string>  $schoolClassIds
     */
    public function generate(AcademicYear $academicYear, User $actor, array $schoolClassIds = []): TimetableGenerationRun
    {
        $prepared = $this->prepare($academicYear, $schoolClassIds);
        $input = $prepared['input'];
        $diagnostics = $prepared['diagnostics'];
        $fingerprint = $this->fingerprint($input);
        $input['fingerprint'] = $fingerprint;
        $input['variation_seed'] = random_int(1, 2_000_000_000);
        $input['excluded_solutions'] = $diagnostics['blockers'] === []
            ? $this->previousSolutions($academicYear, $fingerprint)
            : [];

        $run = TimetableGenerationRun::query()->create([
            'academic_year_id' => $academicYear->id,
            'status' => TimetableGenerationRun::STATUS_DRAFT,
            'input_snapshot' => $input,
            'diagnostics' => $diagnostics,
            'requested_by' => $actor->id,
        ]);

        if ($diagnostics['blockers'] !== []) {
            $run->update([
                'status' => TimetableGenerationRun::STATUS_FAILED,
                'solver_status' => 'NOT_READY',
            ]);

            return $run->fresh();
        }

        try {
            $result = $this->solve($input);
            $solverStatus = (string) ($result['status'] ?? 'ERROR');
            if ($solverStatus === 'INFEASIBLE' && $input['excluded_solutions'] !== []) {
                $baselineInput = $input;
                $baselineInput['excluded_solutions'] = [];
                $baselineResult = $this->solve($baselineInput);

                if (in_array((string) ($baselineResult['status'] ?? ''), ['OPTIMAL', 'FEASIBLE'], true)) {
                    $solverStatus = 'NO_ALTERNATIVE';
                    $diagnostics['blockers'][] = 'Aucune nouvelle proposition différente n’est possible avec les contraintes actuelles. Modifie une disponibilité, un cours verrouillé ou un volume horaire.';
                }
            }
            if ($solverStatus === 'INFEASIBLE') {
                $diagnostics['blockers'][] = 'Aucune combinaison ne respecte à la fois les disponibilités des professeurs, les blocs de cours et la synchronisation des cours communs pour cette sélection de classes. Vérifie en priorité les professeurs qui interviennent dans plusieurs classes sélectionnées, en particulier lorsqu’un volume horaire impose un bloc unique (ex. 3 heures d’affilée) : ils ont souvent besoin de créneaux consécutifs supplémentaires.';
            }
            $solutionErrors = in_array($solverStatus, ['OPTIMAL', 'FEASIBLE'], true)
                ? $this->solutionErrors($input, $result)
                : [];

            if ($solutionErrors !== []) {
                $solverStatus = 'INVALID_SOLUTION';
                $diagnostics['blockers'] = [
                    ...$diagnostics['blockers'],
                    ...$solutionErrors,
                ];
            }

            $run->update([
                'status' => in_array($solverStatus, ['OPTIMAL', 'FEASIBLE'], true)
                    ? TimetableGenerationRun::STATUS_DRAFT
                    : TimetableGenerationRun::STATUS_FAILED,
                'solver_status' => $solverStatus,
                'result' => $result,
                'diagnostics' => $diagnostics,
            ]);
        } catch (\Throwable $error) {
            report($error);
            $run->update([
                'status' => TimetableGenerationRun::STATUS_FAILED,
                'solver_status' => 'ERROR',
                'diagnostics' => [
                    ...$diagnostics,
                    'blockers' => [...$diagnostics['blockers'], 'Le moteur de planification est momentanement indisponible.'],
                ],
            ]);
        }

        return $run->fresh();
    }

    public function apply(TimetableGenerationRun $run, User $actor): void
    {
        if (! $run->canBeApplied()) {
            throw ValidationException::withMessages([
                'generation' => 'Cette proposition ne peut pas être appliquée.',
            ]);
        }

        $prepared = $this->prepare(
            $run->academicYear,
            array_map('intval', $run->input_snapshot['target_class_ids'] ?? []),
        );
        $freshInput = $prepared['input'];
        if ($prepared['diagnostics']['blockers'] !== []
            || $this->fingerprint($freshInput) !== ($run->input_snapshot['fingerprint'] ?? null)) {
            throw ValidationException::withMessages([
                'generation' => 'Les classes, les volumes horaires ou les disponibilités ont changé. Génère une nouvelle proposition.',
            ]);
        }

        if ($this->solutionErrors($run->input_snapshot, $run->result ?? []) !== []) {
            throw ValidationException::withMessages([
                'generation' => 'La proposition contient un conflit technique. Génère une nouvelle proposition.',
            ]);
        }

        $assignments = ClassSubject::query()
            ->with(['subject', 'teacher'])
            ->whereIn('id', collect($run->result['assignments'])->pluck('class_subject_id')->unique())
            ->get()
            ->keyBy('id');
        $solutionBySlot = collect($run->result['assignments'])
            ->keyBy(fn (array $entry): string => $entry['class_id'].'|'.$entry['day'].'|'.$entry['period_id']);
        $synchronizationGroups = collect($run->input_snapshot['assignments'] ?? [])
            ->pluck('synchronization_group', 'id');
        $lockedBySlot = TimetableEntry::query()
            ->where('is_locked', true)
            ->whereHas('timetable', fn ($query) => $query
                ->where('academic_year_id', $run->academic_year_id)
                ->whereIn('school_class_id', $run->input_snapshot['target_class_ids'] ?? []))
            ->with('timetable:id,school_class_id')
            ->get()
            ->keyBy(fn (TimetableEntry $entry): string => $entry->timetable->school_class_id.'|'.$entry->day_of_week.'|'.$entry->timetable_period_id);
        $periods = TimetablePeriod::query()
            ->where('academic_year_id', $run->academic_year_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
        $days = $this->templates->days();
        $targetClassIds = $run->input_snapshot['target_class_ids'] ?? [];

        try {
            DB::transaction(function () use ($run, $actor, $assignments, $solutionBySlot, $synchronizationGroups, $lockedBySlot, $periods, $days, $targetClassIds): void {
                $currentTimetables = Timetable::query()
                    ->where('academic_year_id', $run->academic_year_id)
                    ->whereIn('school_class_id', $targetClassIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('school_class_id');
                $teacherIds = $assignments->pluck('teacher_id')->filter()->unique();
                $occupiedTeacherSlots = collect();

                if ($teacherIds->isNotEmpty()) {
                    $occupiedTeacherSlots = TimetableEntry::query()
                        ->with('timetable:id,school_class_id,academic_year_id')
                        ->whereIn('teacher_id', $teacherIds)
                        ->whereNotNull('timetable_period_id')
                        ->whereHas('timetable', fn ($query) => $query->where('academic_year_id', $run->academic_year_id))
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get()
                        ->reject(fn (TimetableEntry $entry): bool => in_array(
                            $entry->timetable?->school_class_id,
                            $targetClassIds,
                            true,
                        ))
                        ->keyBy(fn (TimetableEntry $entry): string => $entry->teacher_id.'|'.$entry->day_of_week.'|'.$entry->timetable_period_id);
                }

                foreach ($run->result['assignments'] ?? [] as $plannedEntry) {
                    $teacherId = (int) ($plannedEntry['teacher_id'] ?? 0);

                    if (! $teacherId) {
                        continue;
                    }

                    $key = $teacherId.'|'.($plannedEntry['day'] ?? '').'|'.($plannedEntry['period_id'] ?? '');

                    if ($occupiedTeacherSlots->has($key)) {
                        $teacherName = $assignments
                            ->firstWhere('teacher_id', $teacherId)?->teacher?->name ?? 'Un professeur';

                        throw ValidationException::withMessages([
                            'generation' => $teacherName.' est déjà occupé sur un créneau proposé. Génère une nouvelle proposition.',
                        ]);
                    }
                }

                foreach ($targetClassIds as $classId) {
                    $existing = $currentTimetables->get($classId);

                    if ($existing?->status === 'active') {
                        throw ValidationException::withMessages([
                            'generation' => 'Un emploi du temps est devenu actif depuis la génération. Aucune grille n’a été remplacée.',
                        ]);
                    }

                    $classAssignments = $assignments->where('school_class_id', $classId);
                    $timetable = Timetable::query()->updateOrCreate(
                        ['academic_year_id' => $run->academic_year_id, 'school_class_id' => $classId],
                        [
                            'title' => 'Emploi du temps généré automatiquement',
                            'principal_teacher' => $classAssignments->pluck('teacher.name')->filter()->unique()->implode('; '),
                            'notes' => 'Proposition automatique appliquée le '.now()->format('d/m/Y H:i').'. À vérifier avant activation.',
                            'status' => 'draft',
                            'created_by' => $existing?->created_by ?? $actor->id,
                        ],
                    );

                    $entries = [];
                    foreach ($periods as $period) {
                        foreach ($days as $day => $dayLabel) {
                            $solution = $solutionBySlot->get($classId.'|'.$day.'|'.$period->id);
                            $assignment = $solution ? $assignments->get($solution['class_subject_id']) : null;
                            $lockedEntry = $lockedBySlot->get($classId.'|'.$day.'|'.$period->id);
                            $entries[] = [
                                'generation_run_id' => $run->id,
                                'timetable_period_id' => $period->id,
                                'sort_order' => $period->sort_order,
                                'period_label' => $period->label,
                                'starts_at' => $period->starts_at,
                                'ends_at' => $period->ends_at,
                                'day_of_week' => $day,
                                'class_subject_id' => $assignment?->id,
                                'subject_id' => $assignment?->subject_id,
                                'teacher_id' => $assignment?->teacher_id,
                                'subject_name' => $period->is_break ? $period->label : $assignment?->subject?->name,
                                'teacher_name' => $assignment?->teacher?->name,
                                'room' => $lockedEntry?->room,
                                'is_break' => $period->is_break,
                                'is_locked' => (bool) ($solution['is_fixed'] ?? false),
                                'source' => $assignment ? 'automatic' : 'manual',
                                'synchronization_group' => $assignment
                                    ? $synchronizationGroups->get($assignment->id)
                                    : null,
                            ];
                        }
                    }

                    $timetable->entries()->delete();
                    $timetable->entries()->createMany($entries);
                }

                $run->update([
                    'status' => TimetableGenerationRun::STATUS_APPLIED,
                    'applied_by' => $actor->id,
                    'applied_at' => now(),
                ]);
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'timetable_entries_teacher_slot_conflict')) {
                throw ValidationException::withMessages([
                    'generation' => 'Un professeur vient d’être affecté à l’un des créneaux proposés. Génère une nouvelle proposition.',
                ]);
            }

            throw $exception;
        }
    }

    public function previewGrid(TimetableGenerationRun $run): array
    {
        $assignments = ClassSubject::query()
            ->with(['schoolClass', 'subject', 'teacher'])
            ->whereIn('id', collect($run->result['assignments'] ?? [])->pluck('class_subject_id')->unique())
            ->get()
            ->keyBy('id');
        $periods = TimetablePeriod::query()
            ->where('academic_year_id', $run->academic_year_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
        $solution = collect($run->result['assignments'] ?? [])
            ->keyBy(fn (array $entry): string => $entry['class_id'].'|'.$entry['day'].'|'.$entry['period_id']);

        return SchoolClass::query()
            ->whereIn('id', $run->input_snapshot['target_class_ids'] ?? [])
            ->orderBy('name')
            ->get()
            ->map(function (SchoolClass $class) use ($periods, $solution, $assignments): array {
                return [
                    'class' => $class,
                    'rows' => $periods->map(function (TimetablePeriod $period) use ($class, $solution, $assignments): array {
                        $days = [];
                        foreach (array_keys($this->templates->days()) as $day) {
                            $entry = $solution->get($class->id.'|'.$day.'|'.$period->id);
                            $days[$day] = $entry ? $assignments->get($entry['class_subject_id']) : null;
                        }

                        return ['period' => $period, 'days' => $days];
                    })->all(),
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, int|string>  $schoolClassIds
     */
    private function prepare(AcademicYear $academicYear, array $schoolClassIds = []): array
    {
        $schoolClassIds = collect($schoolClassIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $this->templates->ensurePeriods($academicYear);
        $periods = TimetablePeriod::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('is_active', true)
            ->where('is_break', false)
            ->orderBy('sort_order')
            ->get();
        $days = $this->templates->days();
        $periodOrderById = $periods->pluck('sort_order', 'id');
        $classes = SchoolClass::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('status', 'active')
            ->when($schoolClassIds !== [], fn ($query) => $query->whereIn('id', $schoolClassIds))
            ->orderBy('name')
            ->get();
        $activeTimetables = Timetable::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('status', 'active')
            ->pluck('school_class_id');
        $targetClasses = $classes->whereNotIn('id', $activeTimetables)->values();
        $allAssignments = ClassSubject::query()
            ->with(['schoolClass.level', 'subject', 'teacher'])
            ->whereIn('school_class_id', $targetClasses->pluck('id'))
            ->where('is_active', true)
            ->orderBy('school_class_id')
            ->orderBy('id')
            ->get();
        $assignmentClassIds = $allAssignments->pluck('school_class_id')->unique();
        $teacherIds = $allAssignments->pluck('teacher_id')->filter()->unique()->values();
        $schedules = TeacherAvailabilitySchedule::query()
            ->with('availabilities')
            ->where('academic_year_id', $academicYear->id)
            ->whereIn('teacher_id', $teacherIds)
            ->where('status', TeacherAvailabilitySchedule::STATUS_VALIDATED)
            ->get()
            ->keyBy('teacher_id');
        $occupiedSlots = TimetableEntry::query()
            ->whereIn('teacher_id', $teacherIds)
            ->whereNotNull('timetable_period_id')
            ->whereHas('timetable', fn ($query) => $query
                ->where('academic_year_id', $academicYear->id)
                ->whereNotIn('school_class_id', $targetClasses->pluck('id')))
            ->get()
            ->groupBy('teacher_id')
            ->map(fn (Collection $entries): array => $entries
                ->map(fn (TimetableEntry $entry): string => $this->slotKey($entry->day_of_week, $entry->timetable_period_id))
                ->all());
        $lockedEntries = TimetableEntry::query()
            ->where('is_locked', true)
            ->whereHas('timetable', fn ($query) => $query
                ->where('academic_year_id', $academicYear->id)
                ->whereIn('school_class_id', $targetClasses->pluck('id'))
                ->where('status', '!=', 'active'))
            ->get()
            ->groupBy('class_subject_id');
        $invalidLockedEntries = $lockedEntries
            ->toBase()
            ->except($allAssignments->pluck('id')->all())
            ->flatten(1);

        $blockers = [];
        $warnings = [];
        if ($periods->isEmpty()) {
            $blockers[] = 'Aucun créneau de cours actif n’est configuré.';
        }
        if ($targetClasses->isEmpty()) {
            $blockers[] = 'Toutes les classes actives ont déjà un emploi du temps actif.';
        }
        if ($activeTimetables->isNotEmpty()) {
            $activeCount = $activeTimetables->count();
            $warnings[] = $activeCount === 1
                ? 'Une classe possède déjà une grille active, qui sera conservée sans modification.'
                : $activeCount.' classes possèdent déjà une grille active, qui sera conservée sans modification.';
        }
        foreach ($targetClasses->whereNotIn('id', $assignmentClassIds) as $classWithoutSubjects) {
            $blockers[] = $classWithoutSubjects->name.' : aucune matière active n’est configurée.';
        }
        if ($invalidLockedEntries->isNotEmpty()) {
            $blockers[] = 'Des cours verrouillés ne correspondent plus à une affectation active. Corrige-les avant la génération.';
        }

        $synchronizationGroups = collect();
        $sharedCourseAssignments = $allAssignments
            ->filter(fn (ClassSubject $assignment): bool => $this->sharedCourseGroup($assignment) !== null)
            ->groupBy(fn (ClassSubject $assignment): string => (string) $this->sharedCourseGroup($assignment));
        foreach ($sharedCourseAssignments as $group => $groupAssignments) {
            $label = $this->sharedCourseLabel($group);
            if ($groupAssignments->count() < 2) {
                $warnings[] = $label.' : synchronisation non appliquée pour cette sélection, car l’autre classe concernée n’est pas incluse dans cet essai. Sélectionne les deux classes ensemble pour synchroniser ce cours commun.';

                continue;
            }

            $teacherIdsForGroup = $groupAssignments->pluck('teacher_id')->filter()->unique();
            if ($teacherIdsForGroup->count() !== 1 || $groupAssignments->contains(fn (ClassSubject $assignment): bool => ! $assignment->teacher_id)) {
                $blockers[] = $label.' : les classes doivent être affectées au même professeur pour fusionner ce cours.';

                continue;
            }

            $weeklyHours = $groupAssignments
                ->map(fn (ClassSubject $assignment): float => (float) $assignment->weekly_hours)
                ->unique();
            if ($weeklyHours->count() !== 1) {
                $blockers[] = $label.' : les classes doivent avoir le même volume horaire pour fusionner ce cours.';

                continue;
            }

            foreach ($groupAssignments as $assignment) {
                $synchronizationGroups->put($assignment->id, $group);
            }
        }

        $assignments = [];
        foreach ($allAssignments as $assignment) {
            $label = ($assignment->schoolClass?->name ?? 'Classe').' - '.($assignment->subject?->name ?? 'Matière');
            if (! $assignment->teacher_id) {
                $blockers[] = $label.' : aucun professeur affecté.';

                continue;
            }
            $hours = (float) $assignment->weekly_hours;
            if ($hours <= 0) {
                $blockers[] = $label.' : volume horaire hebdomadaire manquant.';

                continue;
            }
            if (abs($hours - round($hours)) > 0.001) {
                $blockers[] = $label.' : le volume horaire doit correspondre à un nombre entier de créneaux.';

                continue;
            }
            $schedule = $schedules->get($assignment->teacher_id);
            if (! $schedule) {
                $blockers[] = $label.' : disponibilités du professeur non validées.';

                continue;
            }

            $blockedByActive = collect($occupiedSlots->get($assignment->teacher_id, []));
            $allowed = collect($schedule->availabilities
                ->filter(fn (TeacherAvailability $slot): bool => $slot->status !== TeacherAvailability::STATUS_UNAVAILABLE)
                ->map(fn (TeacherAvailability $slot): string => $this->slotKey($slot->day_of_week, $slot->timetable_period_id))
                ->reject(fn (string $key): bool => $blockedByActive->contains($key))
                ->values()
                ->all());
            $preferred = collect($schedule->availabilities
                ->where('status', TeacherAvailability::STATUS_PREFERRED)
                ->map(fn (TeacherAvailability $slot): string => $this->slotKey($slot->day_of_week, $slot->timetable_period_id))
                ->values()
                ->all())
                ->intersect($allowed)
                ->values();
            $fixed = collect($lockedEntries->get($assignment->id, []))
                ->map(fn (TimetableEntry $entry): string => $this->slotKey($entry->day_of_week, $entry->timetable_period_id))
                ->values();
            $required = (int) round($hours);

            if ($fixed->count() > $required) {
                $blockers[] = $label.' : plus de cours verrouillés que le volume horaire demandé.';

                continue;
            }
            if ($allowed->count() < $required || $fixed->diff($allowed)->isNotEmpty()) {
                $blockers[] = $label.' : pas assez de créneaux disponibles pour '.$required.' heure(s).';

                continue;
            }

            $maxSlotsPerDay = $required <= 1 ? 1 : min($required, $required % 2 === 0 ? 2 : 3);
            $blockIssue = $this->blockFeasibilityIssue($allowed, $fixed, $required, $maxSlotsPerDay, $days, $periodOrderById);
            if ($blockIssue !== null) {
                $blockers[] = $label.' : '.$blockIssue;

                continue;
            }

            $assignments[] = [
                'id' => $assignment->id,
                'class_id' => $assignment->school_class_id,
                'teacher_id' => $assignment->teacher_id,
                'synchronization_group' => $synchronizationGroups->get($assignment->id),
                'required_slots' => $required,
                'max_slots_per_day' => $maxSlotsPerDay,
                'allowed_slot_keys' => $allowed->all(),
                'preferred_slot_keys' => $preferred->all(),
                'fixed_slot_keys' => $fixed->all(),
            ];
        }

        if ($allAssignments->isEmpty() && $targetClasses->isNotEmpty()) {
            $blockers[] = 'Aucune matière active n’est affectée aux classes à planifier.';
        }

        foreach (collect($assignments)->groupBy('class_id') as $classId => $classAssignments) {
            $capacity = $periods->count() * count($days);
            if ($classAssignments->sum('required_slots') > $capacity) {
                $className = $targetClasses->firstWhere('id', $classId)?->name ?? 'Une classe';
                $blockers[] = $className.' : le volume horaire dépasse la capacité de la semaine.';
            }
        }
        foreach (collect($assignments)->groupBy('teacher_id') as $teacherId => $teacherAssignments) {
            $allowedCapacity = $teacherAssignments
                ->flatMap(fn (array $assignment): array => $assignment['allowed_slot_keys'])
                ->unique()
                ->count();
            $requestedCapacity = $teacherAssignments
                ->groupBy(fn (array $assignment): string => filled($assignment['synchronization_group'] ?? null)
                    ? 'shared:'.$assignment['synchronization_group']
                    : 'assignment:'.$assignment['id'])
                ->sum(fn (Collection $group): int => (int) $group->first()['required_slots']);
            if ($requestedCapacity > $allowedCapacity) {
                $teacherName = User::query()->whereKey($teacherId)->value('name') ?? 'Un professeur';
                $blockers[] = $teacherName.' : les disponibilités ne couvrent pas son volume horaire total.';
            }
        }

        $slots = [];
        foreach ($days as $day => $label) {
            foreach ($periods as $period) {
                $slots[] = [
                    'key' => $this->slotKey($day, $period->id),
                    'day' => $day,
                    'period_id' => $period->id,
                    'period_order' => $period->sort_order,
                    'is_morning' => substr((string) $period->starts_at, 0, 5) < '12:00',
                ];
            }
        }

        return [
            'input' => [
                'academic_year_id' => $academicYear->id,
                'days' => array_keys($days),
                'slots' => $slots,
                'class_ids' => $targetClasses->pluck('id')->all(),
                'target_class_ids' => $targetClasses->pluck('id')->all(),
                'teacher_ids' => collect($assignments)->pluck('teacher_id')->unique()->values()->all(),
                'assignments' => $assignments,
                'time_limit_seconds' => (int) config('services.timetable_solver.time_limit_seconds', 12),
                'workers' => 4,
            ],
            'diagnostics' => [
                'blockers' => array_values(array_unique($blockers)),
                'warnings' => array_values(array_unique($warnings)),
                'counts' => [
                    'classes' => $targetClasses->count(),
                    'assignments' => $allAssignments->count(),
                    'teachers' => $teacherIds->count(),
                    'periods' => $periods->count(),
                    'requested_slots' => round($allAssignments->sum(
                        fn (ClassSubject $assignment): float => max(0, (float) $assignment->weekly_hours),
                    ), 2),
                ],
            ],
        ];
    }

    private function solve(array $input): array
    {
        $python = (string) config('services.timetable_solver.python');
        $script = (string) config('services.timetable_solver.script');
        $process = new Process([$python, $script]);
        $process->setInput(json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $process->setTimeout(((int) $input['time_limit_seconds']) + 10);
        $process->mustRun();

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function solutionErrors(array $input, array $result): array
    {
        $assignments = collect($input['assignments'] ?? [])
            ->filter(fn ($assignment): bool => is_array($assignment) && filled($assignment['id'] ?? null))
            ->keyBy(fn (array $assignment): int => (int) $assignment['id']);
        $slots = collect($input['slots'] ?? [])
            ->filter(fn ($slot): bool => is_array($slot) && filled($slot['key'] ?? null))
            ->keyBy(fn (array $slot): string => (string) $slot['key']);
        $entries = collect($result['assignments'] ?? []);
        $errors = [];
        $placed = [];
        $daily = [];
        $periodOrders = [];
        $assignmentSlots = [];
        $classSlots = [];
        $teacherSlots = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                $errors[] = 'Le moteur a renvoyé une ligne de cours illisible.';

                continue;
            }

            $assignmentId = (int) ($entry['class_subject_id'] ?? 0);
            $slotKey = (string) ($entry['slot_key'] ?? '');
            $assignment = $assignments->get($assignmentId);
            $slot = $slots->get($slotKey);

            if (! $assignment || ! $slot) {
                $errors[] = 'Le moteur a renvoyé un cours ou un créneau inconnu.';

                continue;
            }

            $classId = (int) ($entry['class_id'] ?? 0);
            $teacherId = (int) ($entry['teacher_id'] ?? 0);
            $day = (string) ($entry['day'] ?? '');
            $periodId = (int) ($entry['period_id'] ?? 0);

            if ($classId !== (int) $assignment['class_id']
                || $teacherId !== (int) $assignment['teacher_id']
                || $day !== (string) $slot['day']
                || $periodId !== (int) $slot['period_id']) {
                $errors[] = 'Le moteur a renvoyé un cours rattaché à une mauvaise classe, à un mauvais professeur ou à un mauvais créneau.';
            }

            if (! in_array($slotKey, $assignment['allowed_slot_keys'] ?? [], true)) {
                $errors[] = 'Le moteur a placé un cours pendant une indisponibilité.';
            }

            $assignmentSlotKey = $assignmentId.'|'.$slotKey;
            $classSlotKey = $classId.'|'.$slotKey;
            $teacherSlotKey = $teacherId.'|'.$slotKey;

            if (isset($assignmentSlots[$assignmentSlotKey])) {
                $errors[] = 'Le moteur a dupliqué un cours sur le même créneau.';
            }
            if (isset($classSlots[$classSlotKey])) {
                $errors[] = 'Le moteur a créé un conflit de classe sur un même créneau.';
            }
            $synchronizationGroup = $assignment['synchronization_group'] ?? null;
            $teacherOccupancy = $synchronizationGroup ?: 'assignment:'.$assignmentId;
            if (isset($teacherSlots[$teacherSlotKey]) && $teacherSlots[$teacherSlotKey] !== $teacherOccupancy) {
                $errors[] = 'Le moteur a créé un conflit de professeur sur un même créneau.';
            }

            $assignmentSlots[$assignmentSlotKey] = true;
            $classSlots[$classSlotKey] = true;
            $teacherSlots[$teacherSlotKey] = $teacherOccupancy;
            $placed[$assignmentId][] = $slotKey;
            $daily[$assignmentId][$day] = ($daily[$assignmentId][$day] ?? 0) + 1;
            $periodOrders[$assignmentId][$day][] = (int) $slot['period_order'];

            $mustBeFixed = in_array($slotKey, $assignment['fixed_slot_keys'] ?? [], true);
            if ((bool) ($entry['is_fixed'] ?? false) !== $mustBeFixed) {
                $errors[] = 'Le moteur n’a pas conservé correctement un cours verrouillé.';
            }
        }

        foreach ($assignments as $assignmentId => $assignment) {
            $assignmentPlaced = $placed[$assignmentId] ?? [];
            $requiredSlots = (int) ($assignment['required_slots'] ?? 0);
            $maxSlotsPerDay = max(1, (int) ($assignment['max_slots_per_day'] ?? 2));
            if (count($assignmentPlaced) !== $requiredSlots) {
                $errors[] = 'Le moteur n’a pas respecté tous les volumes horaires demandés.';
            }
            if (array_diff($assignment['fixed_slot_keys'] ?? [], $assignmentPlaced) !== []) {
                $errors[] = 'Le moteur a déplacé un cours verrouillé.';
            }
            foreach ($daily[$assignmentId] ?? [] as $count) {
                if ($count > (int) ($assignment['max_slots_per_day'] ?? 2)) {
                    $errors[] = 'Le moteur a dépassé la limite quotidienne d’une matière.';
                }
                if ($requiredSlots > 1 && $count < 2) {
                    $errors[] = 'Le moteur a isolé une heure de cours au lieu de former un bloc d’au moins deux heures.';
                }
            }
            if (count($periodOrders[$assignmentId] ?? []) > (int) ceil($requiredSlots / $maxSlotsPerDay)) {
                $errors[] = 'Le moteur a dispersé les heures d’une même matière sur trop de jours.';
            }
            foreach ($periodOrders[$assignmentId] ?? [] as $orders) {
                sort($orders);
                for ($index = 1; $index < count($orders); $index++) {
                    if ($orders[$index] !== $orders[$index - 1] + 1) {
                        $errors[] = 'Le moteur a séparé les heures d’une même matière au lieu de les regrouper.';

                        break;
                    }
                }
            }
        }

        $assignments
            ->filter(fn (array $assignment): bool => filled($assignment['synchronization_group'] ?? null))
            ->groupBy('synchronization_group')
            ->each(function (Collection $group) use (&$errors, $placed): void {
                $expectedSlots = null;
                foreach ($group as $assignment) {
                    $slots = $placed[(int) $assignment['id']] ?? [];
                    sort($slots);
                    if ($expectedSlots === null) {
                        $expectedSlots = $slots;

                        continue;
                    }
                    if ($slots !== $expectedSlots) {
                        $errors[] = 'Le moteur n’a pas placé un cours commun au même horaire dans toutes les classes.';

                        break;
                    }
                }
            });

        $currentSolution = $this->normalizedSolution($entries);
        foreach ($input['excluded_solutions'] ?? [] as $excludedSolution) {
            if ($currentSolution !== [] && $currentSolution === $this->normalizedSolution($excludedSolution)) {
                $errors[] = 'Le moteur a reproduit une proposition déjà présentée.';

                break;
            }
        }

        return array_values(array_unique($errors));
    }

    /** @return array<int, array<int, array{class_subject_id: int, slot_key: string}>> */
    private function previousSolutions(AcademicYear $academicYear, string $fingerprint): array
    {
        $expectedSlots = 0;
        $solutions = TimetableGenerationRun::query()
            ->where('academic_year_id', $academicYear->id)
            ->whereIn('status', [TimetableGenerationRun::STATUS_DRAFT, TimetableGenerationRun::STATUS_APPLIED])
            ->whereIn('solver_status', ['OPTIMAL', 'FEASIBLE'])
            ->latest('id')
            ->limit(50)
            ->get()
            ->filter(function (TimetableGenerationRun $run) use ($fingerprint, &$expectedSlots): bool {
                if (($run->input_snapshot['fingerprint'] ?? null) !== $fingerprint) {
                    return false;
                }

                $expectedSlots = collect($run->input_snapshot['assignments'] ?? [])
                    ->sum(fn (array $assignment): int => (int) ($assignment['required_slots'] ?? 0));

                return $expectedSlots > 0;
            })
            ->map(fn (TimetableGenerationRun $run): array => $this->normalizedSolution($run->result['assignments'] ?? []))
            ->filter(fn (array $solution): bool => count($solution) === $expectedSlots)
            ->unique(fn (array $solution): string => json_encode($solution, JSON_THROW_ON_ERROR))
            ->take(20)
            ->values()
            ->all();

        return $solutions;
    }

    /** @return array<int, array{class_subject_id: int, slot_key: string}> */
    private function normalizedSolution(iterable $entries): array
    {
        return collect($entries)
            ->filter(fn ($entry): bool => is_array($entry)
                && filled($entry['class_subject_id'] ?? null)
                && filled($entry['slot_key'] ?? null))
            ->map(fn (array $entry): array => [
                'class_subject_id' => (int) $entry['class_subject_id'],
                'slot_key' => (string) $entry['slot_key'],
            ])
            ->sortBy(fn (array $entry): string => str_pad((string) $entry['class_subject_id'], 20, '0', STR_PAD_LEFT).'|'.$entry['slot_key'])
            ->values()
            ->all();
    }

    /**
     * Détermine si les créneaux disponibles permettent réellement de former les blocs requis
     * (chaque jour utilisé doit offrir au moins `$minDaily` créneaux consécutifs, sur au plus
     * `ceil($required / $maxSlotsPerDay)` jours), avant même d'appeler le solveur.
     *
     * @param  Collection<int, string>  $allowed
     * @param  Collection<int, string>  $fixed
     * @param  array<string, string>  $days
     * @param  Collection<int, int>  $periodOrderById
     */
    private function blockFeasibilityIssue(
        Collection $allowed,
        Collection $fixed,
        int $required,
        int $maxSlotsPerDay,
        array $days,
        Collection $periodOrderById,
    ): ?string {
        $minDaily = $required <= 1 ? 1 : 2;
        $minDays = (int) ceil($required / $maxSlotsPerDay);

        $capsByDay = [];
        foreach (array_keys($days) as $day) {
            $orders = $allowed
                ->filter(fn (string $key): bool => Str::startsWith($key, $day.'|'))
                ->map(fn (string $key): int => (int) $periodOrderById->get((int) Str::afterLast($key, '|'), 0))
                ->filter()
                ->sort()
                ->values();

            $longestRun = 0;
            $currentRun = 0;
            $previousOrder = null;
            foreach ($orders as $order) {
                $currentRun = ($previousOrder !== null && $order === $previousOrder + 1) ? $currentRun + 1 : 1;
                $longestRun = max($longestRun, $currentRun);
                $previousOrder = $order;
            }

            if ($longestRun >= $minDaily) {
                $capsByDay[$day] = min($longestRun, $maxSlotsPerDay);
            }
        }

        foreach ($fixed as $fixedKey) {
            if (! isset($capsByDay[Str::before($fixedKey, '|')])) {
                return 'un cours verrouillé est isolé ce jour-là et ne peut pas former un bloc d’au moins deux heures.';
            }
        }

        $caps = collect($capsByDay)->sortDesc()->values();
        $usableDays = $caps->take($minDays);

        if ($usableDays->count() < $minDays) {
            return sprintf(
                'il faut %d jour(s) avec au moins %dh consécutives disponibles pour ce professeur, mais seulement %d jour(s) le permettent. Complète ses disponibilités.',
                $minDays,
                $minDaily,
                $usableDays->count(),
            );
        }

        if ($required < $minDaily * $minDays || $required > $usableDays->sum()) {
            return sprintf(
                'les disponibilités du professeur ne permettent pas de former un bloc de %dh sans heure isolée. Ajoute des créneaux consécutifs (avant ou après une pause).',
                $required,
            );
        }

        return null;
    }

    private function sharedCourseGroup(ClassSubject $assignment): ?string
    {
        $level = Str::lower(Str::ascii((string) $assignment->schoolClass?->level?->name));
        $subjectCode = Str::upper(trim((string) $assignment->subject?->code));
        $levelKey = match (true) {
            str_contains($level, '2nde'), str_contains($level, 'seconde') => '2nde',
            str_contains($level, '1re'), str_contains($level, '1ere'), str_contains($level, 'premiere') => '1re',
            default => null,
        };

        if ($levelKey === '2nde' && in_array($subjectCode, ['EPS', 'ECM', 'PHILO'], true)) {
            return $levelKey.':'.$subjectCode;
        }
        if ($levelKey === '1re' && in_array($subjectCode, ['EPS', 'ECM'], true)) {
            return $levelKey.':'.$subjectCode;
        }

        return null;
    }

    private function sharedCourseLabel(string $group): string
    {
        [$level, $subject] = explode(':', $group, 2);

        return $level.' - '.$subject;
    }

    private function fingerprint(array $input): string
    {
        unset(
            $input['fingerprint'],
            $input['time_limit_seconds'],
            $input['workers'],
            $input['variation_seed'],
            $input['excluded_solutions'],
        );

        return hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));
    }

    private function slotKey(string $day, int $periodId): string
    {
        return $day.'|'.$periodId;
    }
}
