<?php

namespace App\Services;

use App\Models\ClassSubject;
use App\Models\Timetable;
use App\Models\TimetableEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimetableGridService
{
    public function __construct(
        private readonly TeacherAvailabilityService $availabilities,
    ) {}

    public function update(Timetable $timetable, array $attributes, array $rows): void
    {
        DB::transaction(function () use ($timetable, $attributes, $rows): void {
            $lockedTimetable = Timetable::query()
                ->lockForUpdate()
                ->findOrFail($timetable->id);

            if ($lockedTimetable->status === 'active') {
                throw ValidationException::withMessages([
                    'timetable' => 'Cet emploi du temps est publié. Repasse-le en brouillon avant de le modifier.',
                ]);
            }

            $assignments = ClassSubject::query()
                ->with(['subject', 'teacher'])
                ->where('school_class_id', $lockedTimetable->school_class_id)
                ->where('is_active', true)
                ->get()
                ->keyBy('id');
            $existingEntries = $lockedTimetable->entries()
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $entries = collect($rows)
                ->map(fn (array $row): array => $this->entryPayload(
                    $row,
                    $assignments,
                    filled($row['entry_id'] ?? null) ? $existingEntries->get((int) $row['entry_id']) : null,
                ))
                ->values();

            $this->ensureTeachersAreAvailable($lockedTimetable, $entries);
            $this->availabilities->ensureTimetableEntriesAllowed($lockedTimetable, $entries);

            $lockedTimetable->update($attributes);
            $lockedTimetable->entries()->delete();
            $lockedTimetable->entries()->createMany($entries->all());
        });
    }

    private function entryPayload(array $entry, Collection $assignments, ?TimetableEntry $existing): array
    {
        if ($existing?->is_locked) {
            return $this->preservedPayload($existing);
        }

        $assignment = filled($entry['class_subject_id'] ?? null)
            ? $assignments->get((int) $entry['class_subject_id'])
            : null;

        $payload = [
            'generation_run_id' => $existing?->generation_run_id,
            'timetable_period_id' => filled($entry['timetable_period_id'] ?? null) ? (int) $entry['timetable_period_id'] : null,
            'sort_order' => (int) $entry['sort_order'],
            'period_label' => $entry['period_label'],
            'starts_at' => $entry['starts_at'] ?: null,
            'ends_at' => $entry['ends_at'] ?: null,
            'day_of_week' => $entry['day_of_week'],
            'class_subject_id' => $assignment?->id,
            'subject_id' => $assignment?->subject_id,
            'teacher_id' => $assignment?->teacher_id,
            'subject_name' => $assignment?->subject?->name
                ?? (filled($entry['subject_name'] ?? null) ? trim($entry['subject_name']) : null),
            'teacher_name' => $assignment?->teacher?->name
                ?? (filled($entry['teacher_name'] ?? null) ? trim($entry['teacher_name']) : null),
            'room' => filled($entry['room'] ?? null) ? trim($entry['room']) : null,
            'is_break' => (bool) ($entry['is_break'] ?? false),
            'is_locked' => (bool) ($existing?->is_locked ?? false),
            'source' => $existing?->source === 'automatic' ? 'automatic' : 'manual',
        ];

        if ($existing?->source === 'automatic' && $this->contentChanged($existing, $payload)) {
            $payload['source'] = 'manual';
        }

        return $payload;
    }

    private function preservedPayload(TimetableEntry $entry): array
    {
        return collect($entry->only([
            'generation_run_id',
            'timetable_period_id',
            'sort_order',
            'period_label',
            'starts_at',
            'ends_at',
            'day_of_week',
            'class_subject_id',
            'subject_id',
            'teacher_id',
            'subject_name',
            'teacher_name',
            'room',
            'is_break',
            'is_locked',
            'source',
        ]))->all();
    }

    private function contentChanged(TimetableEntry $existing, array $payload): bool
    {
        foreach (['class_subject_id', 'subject_id', 'teacher_id', 'subject_name', 'teacher_name', 'room'] as $field) {
            if ((string) ($existing->{$field} ?? '') !== (string) ($payload[$field] ?? '')) {
                return true;
            }
        }

        return false;
    }

    private function ensureTeachersAreAvailable(Timetable $timetable, Collection $entries): void
    {
        $linked = $entries->filter(fn (array $entry): bool => filled($entry['teacher_id']) && filled($entry['timetable_period_id']));
        $duplicates = $linked
            ->groupBy(fn (array $entry): string => $this->slotKey($entry))
            ->first(fn (Collection $group): bool => $group->count() > 1);

        if ($duplicates) {
            throw ValidationException::withMessages([
                'entries' => 'Un professeur ne peut pas occuper deux cours au même créneau.',
            ]);
        }

        if ($linked->isEmpty()) {
            return;
        }

        $conflicts = TimetableEntry::query()
            ->with('timetable.schoolClass')
            ->where('timetable_id', '!=', $timetable->id)
            ->whereHas('timetable', fn ($query) => $query->where('academic_year_id', $timetable->academic_year_id))
            ->whereIn('teacher_id', $linked->pluck('teacher_id')->unique())
            ->whereIn('timetable_period_id', $linked->pluck('timetable_period_id')->unique())
            ->whereIn('day_of_week', $linked->pluck('day_of_week')->unique())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (TimetableEntry $entry): string => $this->slotKey($entry->toArray()));

        foreach ($linked as $entry) {
            $conflict = $conflicts->get($this->slotKey($entry));

            if ($conflict) {
                throw ValidationException::withMessages([
                    'entries' => sprintf(
                        '%s est déjà programmé en %s dans la classe %s.',
                        $entry['teacher_name'],
                        $entry['period_label'],
                        $conflict->timetable?->schoolClass?->name ?? 'une autre classe',
                    ),
                ]);
            }
        }
    }

    private function slotKey(array $entry): string
    {
        return implode('|', [
            $entry['teacher_id'],
            $entry['day_of_week'],
            $entry['timetable_period_id'],
        ]);
    }
}
