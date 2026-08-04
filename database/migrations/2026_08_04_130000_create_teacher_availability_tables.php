<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_availability_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->string('source', 20)->default('manual');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academic_year_id', 'teacher_id'], 'teacher_availability_schedule_unique');
            $table->index(['academic_year_id', 'status'], 'teacher_availability_schedule_status_idx');
        });

        Schema::create('teacher_availabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_availability_schedule_id')
                ->constrained('teacher_availability_schedules')
                ->cascadeOnDelete();
            $table->foreignId('timetable_period_id')->constrained()->cascadeOnDelete();
            $table->string('day_of_week', 20);
            $table->string('status', 20)->default('unavailable');
            $table->timestamps();

            $table->unique(
                ['teacher_availability_schedule_id', 'timetable_period_id', 'day_of_week'],
                'teacher_availability_slot_unique',
            );
            $table->index(
                ['timetable_period_id', 'day_of_week', 'status'],
                'teacher_availability_slot_lookup_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_availabilities');
        Schema::dropIfExists('teacher_availability_schedules');
    }
};
