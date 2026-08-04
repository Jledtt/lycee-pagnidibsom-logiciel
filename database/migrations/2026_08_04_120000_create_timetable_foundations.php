<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sort_order');
            $table->string('label', 40);
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->boolean('is_break')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['academic_year_id', 'sort_order'], 'timetable_periods_year_order_unique');
        });

        Schema::table('class_subjects', function (Blueprint $table): void {
            $table->decimal('weekly_hours', 5, 2)->nullable()->after('coefficient');
        });

        Schema::table('timetable_entries', function (Blueprint $table): void {
            $table->foreignId('timetable_period_id')->nullable()->after('timetable_id')
                ->constrained('timetable_periods')->nullOnDelete();
            $table->foreignId('class_subject_id')->nullable()->after('day_of_week')
                ->constrained('class_subjects')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->after('subject_id')
                ->constrained('users')->nullOnDelete();
            $table->boolean('is_locked')->default(false)->after('is_break');
            $table->string('source', 20)->default('manual')->after('is_locked');

            $table->index(['teacher_id', 'day_of_week', 'timetable_period_id'], 'timetable_entries_teacher_slot_idx');
        });

        $this->seedDefaultPeriods();
        $this->linkExistingEntriesToPeriods();
    }

    public function down(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table): void {
            $table->dropIndex('timetable_entries_teacher_slot_idx');
            $table->dropConstrainedForeignId('teacher_id');
            $table->dropConstrainedForeignId('class_subject_id');
            $table->dropConstrainedForeignId('timetable_period_id');
            $table->dropColumn(['is_locked', 'source']);
        });

        Schema::table('class_subjects', function (Blueprint $table): void {
            $table->dropColumn('weekly_hours');
        });

        Schema::dropIfExists('timetable_periods');
    }

    private function seedDefaultPeriods(): void
    {
        $now = now();

        foreach (DB::table('academic_years')->pluck('id') as $academicYearId) {
            foreach ($this->defaultPeriods() as $period) {
                DB::table('timetable_periods')->insert([
                    'academic_year_id' => $academicYearId,
                    ...$period,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function linkExistingEntriesToPeriods(): void
    {
        $periods = DB::table('timetable_periods')
            ->get()
            ->groupBy('academic_year_id');

        DB::table('timetable_entries')
            ->join('timetables', 'timetables.id', '=', 'timetable_entries.timetable_id')
            ->select([
                'timetable_entries.id',
                'timetable_entries.period_label',
                'timetable_entries.sort_order',
                'timetables.academic_year_id',
            ])
            ->orderBy('timetable_entries.id')
            ->each(function (object $entry) use ($periods): void {
                $yearPeriods = $periods->get($entry->academic_year_id, collect());
                $period = $yearPeriods->firstWhere('label', $entry->period_label)
                    ?? $yearPeriods->firstWhere('sort_order', $entry->sort_order);

                if ($period) {
                    DB::table('timetable_entries')
                        ->where('id', $entry->id)
                        ->update(['timetable_period_id' => $period->id]);
                }
            });
    }

    private function defaultPeriods(): array
    {
        return [
            ['sort_order' => 1, 'label' => '7h00-7h55', 'starts_at' => '07:00', 'ends_at' => '07:55', 'is_break' => false],
            ['sort_order' => 2, 'label' => '7h55-8h50', 'starts_at' => '07:55', 'ends_at' => '08:50', 'is_break' => false],
            ['sort_order' => 3, 'label' => '8h50-9h45', 'starts_at' => '08:50', 'ends_at' => '09:45', 'is_break' => false],
            ['sort_order' => 4, 'label' => 'RECREATION', 'starts_at' => null, 'ends_at' => null, 'is_break' => true],
            ['sort_order' => 5, 'label' => '10h10-11h05', 'starts_at' => '10:10', 'ends_at' => '11:05', 'is_break' => false],
            ['sort_order' => 6, 'label' => '11h05-12h00', 'starts_at' => '11:05', 'ends_at' => '12:00', 'is_break' => false],
            ['sort_order' => 7, 'label' => 'SOIR', 'starts_at' => null, 'ends_at' => null, 'is_break' => true],
            ['sort_order' => 8, 'label' => '15h00-16h00', 'starts_at' => '15:00', 'ends_at' => '16:00', 'is_break' => false],
            ['sort_order' => 9, 'label' => '16h00-17h00', 'starts_at' => '16:00', 'ends_at' => '17:00', 'is_break' => false],
        ];
    }
};
