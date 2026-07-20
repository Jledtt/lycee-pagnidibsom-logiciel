<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createTimetablesTable();

        if (! Schema::hasTable('timetable_entries')) {
            $this->createGridEntriesTable();

            return;
        }

        if (Schema::hasColumn('timetable_entries', 'timetable_id')) {
            return;
        }

        $legacyEntries = DB::table('timetable_entries')->get();

        Schema::drop('timetable_entries');
        $this->createGridEntriesTable();
        $this->convertLegacyEntries($legacyEntries);
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_entries');
        Schema::dropIfExists('timetables');
    }

    private function createTimetablesTable(): void
    {
        if (Schema::hasTable('timetables')) {
            return;
        }

        Schema::create('timetables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->string('title')->default('Emploi du temps');
            $table->string('principal_teacher')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academic_year_id', 'school_class_id'], 'timetables_year_class_unique');
        });
    }

    private function createGridEntriesTable(): void
    {
        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timetable_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sort_order');
            $table->string('period_label', 40);
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('day_of_week', 20);
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject_name')->nullable();
            $table->string('teacher_name')->nullable();
            $table->string('room')->nullable();
            $table->boolean('is_break')->default(false);
            $table->timestamps();

            $table->index(['timetable_id', 'sort_order', 'day_of_week'], 'timetable_entries_slot_idx');
        });
    }

    private function convertLegacyEntries(Collection $legacyEntries): void
    {
        $now = now();

        foreach ($legacyEntries->groupBy(fn ($entry) => $entry->academic_year_id.'-'.$entry->school_class_id) as $entries) {
            $first = $entries->first();
            $className = DB::table('school_classes')->where('id', $first->school_class_id)->value('name');

            $timetableId = DB::table('timetables')->insertGetId([
                'academic_year_id' => $first->academic_year_id,
                'school_class_id' => $first->school_class_id,
                'title' => 'Emploi du temps'.($className ? ' - '.$className : ''),
                'status' => 'draft',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($entries->sortBy(['starts_at', 'day_of_week']) as $index => $entry) {
                DB::table('timetable_entries')->insert([
                    'timetable_id' => $timetableId,
                    'sort_order' => $index + 1,
                    'period_label' => $this->periodLabel($entry->starts_at, $entry->ends_at),
                    'starts_at' => $entry->starts_at,
                    'ends_at' => $entry->ends_at,
                    'day_of_week' => $this->dayName((int) $entry->day_of_week),
                    'subject_name' => $entry->subject_label,
                    'teacher_name' => $entry->teacher_name,
                    'room' => $entry->room,
                    'is_break' => false,
                    'created_at' => $entry->created_at ?? $now,
                    'updated_at' => $entry->updated_at ?? $now,
                ]);
            }
        }
    }

    private function dayName(int $day): string
    {
        return [
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday',
        ][$day] ?? 'monday';
    }

    private function periodLabel(?string $startsAt, ?string $endsAt): string
    {
        if (! $startsAt || ! $endsAt) {
            return 'Cours';
        }

        return $this->formatTime($startsAt).'-'.$this->formatTime($endsAt);
    }

    private function formatTime(string $time): string
    {
        [$hours, $minutes] = explode(':', $time);

        return ((int) $hours).'h'.$minutes;
    }
};
