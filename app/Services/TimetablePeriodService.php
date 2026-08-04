<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Timetable;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimetablePeriodService
{
    public function __construct(private readonly TimetableTemplateService $templates) {}

    public function synchronize(AcademicYear $academicYear, array $rows): void
    {
        DB::transaction(function () use ($academicYear, $rows): void {
            $submittedIds = [];
            $existingIds = TimetablePeriod::query()
                ->where('academic_year_id', $academicYear->id)
                ->pluck('id');
            $requestedExistingIds = collect($rows)->pluck('id')->filter()->map(fn ($id) => (int) $id);

            if ($existingIds->diff($requestedExistingIds)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'periods' => 'Désactive un créneau au lieu de le retirer de la liste.',
                ]);
            }

            // Libère temporairement les positions pour permettre de réordonner deux créneaux.
            TimetablePeriod::query()
                ->where('academic_year_id', $academicYear->id)
                ->increment('sort_order', 100);

            foreach ($rows as $row) {
                $period = $this->period($academicYear, $row['id'] ?? null);
                $period->fill([
                    'academic_year_id' => $academicYear->id,
                    'sort_order' => (int) $row['sort_order'],
                    'label' => trim($row['label']),
                    'starts_at' => $row['is_break'] ? null : $row['starts_at'],
                    'ends_at' => $row['is_break'] ? null : $row['ends_at'],
                    'is_break' => (bool) $row['is_break'],
                    'is_active' => (bool) $row['is_active'],
                ])->save();

                $submittedIds[] = $period->id;
                $this->synchronizeEntries($academicYear, $period);
            }

            TimetablePeriod::query()
                ->where('academic_year_id', $academicYear->id)
                ->whereNotIn('id', $submittedIds)
                ->update(['is_active' => false]);
        });
    }

    private function period(AcademicYear $academicYear, mixed $periodId): TimetablePeriod
    {
        if (! $periodId) {
            return new TimetablePeriod;
        }

        $period = TimetablePeriod::query()
            ->where('academic_year_id', $academicYear->id)
            ->find($periodId);

        if (! $period) {
            throw ValidationException::withMessages([
                'periods' => 'Un créneau ne correspond pas à l’année scolaire active.',
            ]);
        }

        return $period;
    }

    private function synchronizeEntries(AcademicYear $academicYear, TimetablePeriod $period): void
    {
        TimetableEntry::query()
            ->where('timetable_period_id', $period->id)
            ->update([
                'sort_order' => $period->sort_order,
                'period_label' => $period->label,
                'starts_at' => $period->starts_at,
                'ends_at' => $period->ends_at,
                'is_break' => $period->is_break,
                ...($period->is_break ? [
                    'class_subject_id' => null,
                    'subject_id' => null,
                    'teacher_id' => null,
                    'subject_name' => $period->label,
                    'teacher_name' => null,
                    'room' => null,
                ] : []),
            ]);

        if (! $period->is_active) {
            return;
        }

        foreach (Timetable::query()->where('academic_year_id', $academicYear->id)->get() as $timetable) {
            foreach (array_keys($this->templates->days()) as $day) {
                $timetable->entries()->firstOrCreate(
                    [
                        'timetable_period_id' => $period->id,
                        'day_of_week' => $day,
                    ],
                    [
                        'sort_order' => $period->sort_order,
                        'period_label' => $period->label,
                        'starts_at' => $period->starts_at,
                        'ends_at' => $period->ends_at,
                        'subject_name' => $period->is_break ? $period->label : null,
                        'is_break' => $period->is_break,
                    ],
                );
            }
        }
    }
}
