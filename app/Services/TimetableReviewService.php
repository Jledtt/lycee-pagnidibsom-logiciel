<?php

namespace App\Services;

use App\Models\ClassSubject;
use App\Models\Timetable;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimetableReviewService
{
    public function __construct(
        private readonly TeacherAvailabilityService $availabilities,
    ) {}

    public function audit(Timetable $timetable): array
    {
        $timetable->loadMissing(['entries', 'schoolClass', 'academicYear']);
        $assignments = ClassSubject::query()
            ->with(['subject', 'teacher'])
            ->where('school_class_id', $timetable->school_class_id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
        $courses = $timetable->entries
            ->filter(fn (TimetableEntry $entry): bool => $this->isCourse($entry))
            ->values();

        $blockers = [];
        $warnings = [];
        $coverage = $this->coverage($assignments, $courses, $blockers);

        if ($courses->isEmpty()) {
            $blockers[] = 'La grille ne contient encore aucun cours.';
        }

        if ($assignments->isEmpty() && $courses->isNotEmpty()) {
            $warnings[] = 'Cette grille utilise des libelles historiques. Les volumes horaires ne peuvent pas etre controles.';
        }

        $unlinked = $courses->whereNull('class_subject_id');
        if ($unlinked->isNotEmpty() && $assignments->isNotEmpty()) {
            $warnings[] = $unlinked->count().' cours ne sont pas relies a une affectation pedagogique.';
        }

        $invalidLinks = $courses->whereNotNull('class_subject_id')
            ->reject(fn (TimetableEntry $entry): bool => $assignments->contains('id', $entry->class_subject_id));
        if ($invalidLinks->isNotEmpty()) {
            $blockers[] = $invalidLinks->count().' cours utilisent une affectation inactive ou appartenant a une autre classe.';
        }

        $this->availabilityBlocker($timetable, $courses, $blockers);
        $this->teacherConflictBlocker($timetable, $courses, $blockers);
        $this->roomConflictBlocker($timetable, $courses, $blockers);

        return [
            'blockers' => array_values(array_unique($blockers)),
            'warnings' => array_values(array_unique($warnings)),
            'coverage' => $coverage,
            'metrics' => [
                'courses' => $courses->count(),
                'expected' => (int) $coverage->sum('expected'),
                'automatic' => $courses->where('source', 'automatic')->count(),
                'manual' => $courses->where('source', 'manual')->count(),
                'locked' => $courses->where('is_locked', true)->count(),
                'rooms' => $courses->pluck('room')->filter()->unique()->count(),
            ],
            'can_publish' => $blockers === [] && $courses->isNotEmpty(),
        ];
    }

    public function setLock(Timetable $timetable, TimetableEntry $entry, bool $locked): void
    {
        $this->ensureDraft($timetable);

        if ($entry->timetable_id !== $timetable->id || ! $this->isCourse($entry)) {
            throw ValidationException::withMessages([
                'entry' => 'Ce creneau ne peut pas etre verrouille.',
            ]);
        }

        $entry->update(['is_locked' => $locked]);
    }

    public function setAllLocks(Timetable $timetable, bool $locked): int
    {
        $this->ensureDraft($timetable);

        return $timetable->entries()
            ->where('is_break', false)
            ->where(function ($query): void {
                $query->whereNotNull('class_subject_id')
                    ->orWhereNotNull('subject_name');
            })
            ->update(['is_locked' => $locked]);
    }

    public function publish(Timetable $timetable, User $actor): void
    {
        $this->ensureDraft($timetable);
        $audit = $this->audit($timetable);

        if (! $audit['can_publish']) {
            throw ValidationException::withMessages([
                'publication' => $audit['blockers'][0] ?? 'La grille doit etre corrigee avant publication.',
            ]);
        }

        DB::transaction(function () use ($timetable, $actor): void {
            $this->setAllLocks($timetable, true);
            $timetable->update([
                'status' => 'active',
                'published_at' => now(),
                'published_by' => $actor->id,
            ]);
        });
    }

    public function reopen(Timetable $timetable): void
    {
        if ($timetable->status !== 'active') {
            throw ValidationException::withMessages([
                'timetable' => 'Seul un emploi du temps publie peut etre repasse en brouillon.',
            ]);
        }

        $timetable->update([
            'status' => 'draft',
            'published_at' => null,
            'published_by' => null,
        ]);
    }

    private function coverage(Collection $assignments, Collection $courses, array &$blockers): Collection
    {
        return $assignments->map(function (ClassSubject $assignment) use ($courses, &$blockers): array {
            $hours = (float) $assignment->weekly_hours;
            $expected = max(0, (int) round($hours));
            $placed = $courses->where('class_subject_id', $assignment->id)->count();
            $label = $assignment->subject?->name ?? 'Matiere #'.$assignment->id;

            if (! $assignment->teacher_id) {
                $blockers[] = $label.' : aucun professeur affecte.';
            }
            if ($hours <= 0) {
                $blockers[] = $label.' : volume horaire hebdomadaire manquant.';
            } elseif (abs($hours - round($hours)) > 0.001) {
                $blockers[] = $label.' : le volume horaire doit etre un nombre entier de creneaux.';
            } elseif ($placed !== $expected) {
                $blockers[] = $label.' : '.$placed.'/'.$expected.' creneaux places.';
            }

            return [
                'assignment' => $assignment,
                'expected' => $expected,
                'placed' => $placed,
                'complete' => $expected > 0 && $placed === $expected && filled($assignment->teacher_id),
            ];
        })->values();
    }

    private function availabilityBlocker(Timetable $timetable, Collection $courses, array &$blockers): void
    {
        try {
            $this->availabilities->ensureTimetableEntriesAllowed(
                $timetable,
                $courses->map(fn (TimetableEntry $entry): array => $entry->toArray()),
            );
        } catch (ValidationException $error) {
            $blockers[] = collect($error->errors())->flatten()->first();
        }
    }

    private function teacherConflictBlocker(Timetable $timetable, Collection $courses, array &$blockers): void
    {
        $linked = $courses->whereNotNull('teacher_id')->whereNotNull('timetable_period_id');
        if ($linked->isEmpty()) {
            return;
        }

        $conflicts = TimetableEntry::query()
            ->with('timetable.schoolClass')
            ->where('timetable_id', '!=', $timetable->id)
            ->whereIn('teacher_id', $linked->pluck('teacher_id')->unique())
            ->whereIn('timetable_period_id', $linked->pluck('timetable_period_id')->unique())
            ->whereIn('day_of_week', $linked->pluck('day_of_week')->unique())
            ->whereHas('timetable', fn ($query) => $query
                ->where('academic_year_id', $timetable->academic_year_id)
                ->where('status', 'active'))
            ->get()
            ->keyBy(fn (TimetableEntry $entry): string => $this->slotKey($entry, true));

        foreach ($linked as $entry) {
            $conflict = $conflicts->get($this->slotKey($entry, true));
            if ($conflict) {
                $blockers[] = sprintf(
                    '%s est deja programme en %s dans la classe %s.',
                    $entry->teacher_name,
                    $entry->period_label,
                    $conflict->timetable?->schoolClass?->name ?? 'une autre classe',
                );
            }
        }
    }

    private function roomConflictBlocker(Timetable $timetable, Collection $courses, array &$blockers): void
    {
        $withRooms = $courses->filter(fn (TimetableEntry $entry): bool => filled($entry->room) && filled($entry->timetable_period_id));
        if ($withRooms->isEmpty()) {
            return;
        }

        $otherEntries = TimetableEntry::query()
            ->with('timetable.schoolClass')
            ->where('timetable_id', '!=', $timetable->id)
            ->whereNotNull('room')
            ->whereIn('timetable_period_id', $withRooms->pluck('timetable_period_id')->unique())
            ->whereIn('day_of_week', $withRooms->pluck('day_of_week')->unique())
            ->whereHas('timetable', fn ($query) => $query
                ->where('academic_year_id', $timetable->academic_year_id)
                ->where('status', 'active'))
            ->get();

        foreach ($withRooms as $entry) {
            $conflict = $otherEntries->first(fn (TimetableEntry $other): bool => $this->slotKey($entry) === $this->slotKey($other)
                && $this->normalizeRoom($entry->room) === $this->normalizeRoom($other->room));
            if ($conflict) {
                $blockers[] = sprintf(
                    'La salle %s est deja occupee en %s par la classe %s.',
                    $entry->room,
                    $entry->period_label,
                    $conflict->timetable?->schoolClass?->name ?? 'une autre classe',
                );
            }
        }
    }

    private function ensureDraft(Timetable $timetable): void
    {
        if ($timetable->status !== 'draft') {
            throw ValidationException::withMessages([
                'timetable' => 'Cette action est reservee aux emplois du temps en brouillon.',
            ]);
        }
    }

    private function isCourse(TimetableEntry $entry): bool
    {
        return ! $entry->is_break && (filled($entry->class_subject_id) || filled($entry->subject_name));
    }

    private function slotKey(TimetableEntry $entry, bool $withTeacher = false): string
    {
        return implode('|', array_filter([
            $withTeacher ? $entry->teacher_id : null,
            $entry->day_of_week,
            $entry->timetable_period_id,
        ], fn ($value): bool => $value !== null));
    }

    private function normalizeRoom(?string $room): string
    {
        return mb_strtolower(trim((string) $room));
    }
}
