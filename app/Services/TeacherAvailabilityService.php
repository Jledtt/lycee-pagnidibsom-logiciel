<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\TeacherAvailability;
use App\Models\TeacherAvailabilitySchedule;
use App\Models\Timetable;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherAvailabilityService
{
    public function __construct(
        private readonly TimetableTemplateService $templates,
    ) {}

    public function update(
        AcademicYear $academicYear,
        User $teacher,
        User $actor,
        array $data,
    ): TeacherAvailabilitySchedule {
        $periods = $this->coursePeriods($academicYear);
        $slots = $this->normalizeSlots($periods, $data['slots'] ?? []);
        $status = $data['workflow_status'];

        if (in_array($status, [
            TeacherAvailabilitySchedule::STATUS_SUBMITTED,
            TeacherAvailabilitySchedule::STATUS_VALIDATED,
        ], true)) {
            $this->ensureAtLeastOneAvailableSlot($slots);
            $this->ensureExistingCoursesFit($academicYear, $teacher, $slots);
        }

        return DB::transaction(function () use ($academicYear, $teacher, $actor, $data, $slots, $status): TeacherAvailabilitySchedule {
            $schedule = TeacherAvailabilitySchedule::query()->firstOrNew([
                'academic_year_id' => $academicYear->id,
                'teacher_id' => $teacher->id,
            ]);

            $schedule->fill([
                'status' => $status,
                'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
                'source' => 'manual',
                'submitted_at' => $status === TeacherAvailabilitySchedule::STATUS_DRAFT ? null : now(),
                'validated_at' => $status === TeacherAvailabilitySchedule::STATUS_VALIDATED ? now() : null,
                'updated_by' => $actor->id,
            ])->save();

            $schedule->availabilities()->delete();
            $schedule->availabilities()->createMany($slots->values()->all());

            return $schedule->load(['availabilities.period', 'teacher']);
        });
    }

    public function ensureTimetableEntriesAllowed(Timetable $timetable, Collection $entries): void
    {
        $linked = $entries->filter(fn (array $entry): bool => filled($entry['teacher_id']) && filled($entry['timetable_period_id']));

        if ($linked->isEmpty()) {
            return;
        }

        $schedules = TeacherAvailabilitySchedule::query()
            ->with('availabilities')
            ->where('academic_year_id', $timetable->academic_year_id)
            ->whereIn('teacher_id', $linked->pluck('teacher_id')->unique())
            ->whereIn('status', [
                TeacherAvailabilitySchedule::STATUS_SUBMITTED,
                TeacherAvailabilitySchedule::STATUS_VALIDATED,
            ])
            ->get()
            ->keyBy('teacher_id');

        foreach ($linked as $entry) {
            $schedule = $schedules->get($entry['teacher_id']);

            if (! $schedule) {
                continue;
            }

            $slot = $schedule->availabilities->first(fn (TeacherAvailability $availability): bool => $availability->timetable_period_id === $entry['timetable_period_id']
                && $availability->day_of_week === $entry['day_of_week']
            );

            if (! $slot || $slot->status === TeacherAvailability::STATUS_UNAVAILABLE) {
                throw ValidationException::withMessages([
                    'entries' => sprintf(
                        '%s est indisponible le %s au créneau %s.',
                        $entry['teacher_name'],
                        $this->dayLabel($entry['day_of_week']),
                        $entry['period_label'],
                    ),
                ]);
            }
        }
    }

    public function conflictsFor(
        AcademicYear $academicYear,
        User $teacher,
        ?TeacherAvailabilitySchedule $schedule,
    ): Collection {
        if (! $schedule || $schedule->availabilities->isEmpty()) {
            return collect();
        }

        $slots = $schedule->availabilities->keyBy(fn (TeacherAvailability $availability): string => $this->slotKey($availability->timetable_period_id, $availability->day_of_week)
        );

        return $this->existingCourseConflicts($academicYear, $teacher, $slots);
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

    private function normalizeSlots(Collection $periods, array $submittedSlots): Collection
    {
        $days = array_keys($this->templates->days());
        $normalized = collect();

        foreach ($periods as $period) {
            foreach ($days as $day) {
                $status = $submittedSlots[$period->id][$day] ?? TeacherAvailability::STATUS_UNAVAILABLE;
                $normalized->put($this->slotKey($period->id, $day), [
                    'timetable_period_id' => $period->id,
                    'day_of_week' => $day,
                    'status' => $status,
                ]);
            }
        }

        return $normalized;
    }

    private function ensureAtLeastOneAvailableSlot(Collection $slots): void
    {
        $hasAvailability = $slots->contains(fn (array $slot): bool => in_array($slot['status'], [
            TeacherAvailability::STATUS_AVAILABLE,
            TeacherAvailability::STATUS_PREFERRED,
        ], true));

        if (! $hasAvailability) {
            throw ValidationException::withMessages([
                'slots' => 'Indique au moins un créneau disponible avant de transmettre la fiche.',
            ]);
        }
    }

    private function ensureExistingCoursesFit(AcademicYear $academicYear, User $teacher, Collection $slots): void
    {
        $conflict = $this->existingCourseConflicts($academicYear, $teacher, $slots)->first();

        if (! $conflict) {
            return;
        }

        throw ValidationException::withMessages([
            'slots' => sprintf(
                'Impossible de transmettre : %s est déjà programmé le %s à %s pour la classe %s.',
                $conflict->subject_name ?: 'un cours',
                $this->dayLabel($conflict->day_of_week),
                $conflict->period_label,
                $conflict->timetable?->schoolClass?->name ?? 'inconnue',
            ),
        ]);
    }

    private function existingCourseConflicts(AcademicYear $academicYear, User $teacher, Collection $slots): Collection
    {
        return TimetableEntry::query()
            ->with('timetable.schoolClass')
            ->where('teacher_id', $teacher->id)
            ->whereNotNull('timetable_period_id')
            ->where('is_break', false)
            ->whereHas('timetable', fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->get()
            ->filter(function (TimetableEntry $entry) use ($slots): bool {
                $slot = $slots->get($this->slotKey($entry->timetable_period_id, $entry->day_of_week));
                $status = $slot instanceof TeacherAvailability ? $slot->status : ($slot['status'] ?? null);

                return $status === null || $status === TeacherAvailability::STATUS_UNAVAILABLE;
            })
            ->values();
    }

    private function slotKey(int $periodId, string $day): string
    {
        return $periodId.'|'.$day;
    }

    private function dayLabel(string $day): string
    {
        return $this->templates->days()[$day] ?? $day;
    }
}
