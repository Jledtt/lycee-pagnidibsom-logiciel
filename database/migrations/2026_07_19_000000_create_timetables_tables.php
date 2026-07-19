<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

            $table->unique(['academic_year_id', 'school_class_id']);
        });

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

            $table->index(['timetable_id', 'sort_order', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_entries');
        Schema::dropIfExists('timetables');
    }
};
