<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('weight', 5, 2)->default(1);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('assessment_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->decimal('max_score', 5, 2)->default(20);
            $table->date('assessment_date')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
        });

        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->string('comment')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['assessment_id', 'student_id']);
        });

        Schema::create('report_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->decimal('general_average', 5, 2)->nullable();
            $table->unsignedSmallInteger('rank')->nullable();
            $table->unsignedSmallInteger('class_size')->nullable();
            $table->text('appreciation')->nullable();
            $table->string('pdf_path')->nullable();
            $table->enum('status', ['draft', 'validated', 'published'])->default('draft');
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('validated_at')->nullable();
            $table->timestamps();

            $table->unique(['academic_year_id', 'term_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_cards');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('assessment_types');
    }
};
