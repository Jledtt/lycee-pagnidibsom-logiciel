<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('subject_label');
            $table->string('teacher_name')->nullable();
            $table->string('room')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['academic_year_id', 'school_class_id', 'day_of_week'], 'timetable_class_day_idx');
            $table->unique(['school_class_id', 'day_of_week', 'starts_at', 'ends_at'], 'timetable_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_entries');
    }
};
