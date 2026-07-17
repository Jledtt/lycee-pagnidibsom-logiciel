<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('session_date');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['present', 'absent', 'late', 'excused'])->default('present');
            $table->unsignedSmallInteger('minutes_late')->nullable();
            $table->string('reason')->nullable();
            $table->dateTime('justified_at')->nullable();
            $table->foreignId('justified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['attendance_session_id', 'student_id']);
        });

        Schema::create('disciplinary_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['observation', 'warning', 'sanction'])->default('observation');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('record_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('student_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('document_type');
            $table->string('file_path')->nullable();
            $table->enum('status', ['received', 'missing', 'expired'])->default('received');
            $table->date('received_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_documents');
        Schema::dropIfExists('disciplinary_records');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_sessions');
    }
};
