<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('exam_type')->default('bepc_blanc');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status')->default('preparation');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('mock_exam_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mock_exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['mock_exam_id', 'school_class_id'], 'mock_exam_class_unique');
        });

        Schema::create('mock_exam_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mock_exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('exam_part')->default('written');
            $table->decimal('max_score', 6, 2)->default(20);
            $table->decimal('coefficient', 6, 2)->default(1);
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();
            $table->unique(['mock_exam_id', 'subject_id', 'exam_part'], 'mock_exam_subject_unique');
        });

        Schema::create('mock_exam_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mock_exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->string('anonymous_code')->nullable();
            $table->string('room_name')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['mock_exam_id', 'student_id'], 'mock_exam_candidate_unique');
        });

        Schema::create('mock_exam_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mock_exam_subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mock_exam_candidate_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 6, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->string('observation')->nullable();
            $table->timestamps();
            $table->unique(['mock_exam_subject_id', 'mock_exam_candidate_id'], 'mock_exam_score_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_exam_scores');
        Schema::dropIfExists('mock_exam_candidates');
        Schema::dropIfExists('mock_exam_subjects');
        Schema::dropIfExists('mock_exam_classes');
        Schema::dropIfExists('mock_exams');
    }
};
