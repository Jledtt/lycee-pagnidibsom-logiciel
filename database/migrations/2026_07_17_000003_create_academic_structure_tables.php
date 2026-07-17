<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('cycle')->nullable();
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->nullable()->unique();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->foreignId('main_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->timestamps();

            $table->unique(['academic_year_id', 'name']);
        });

        Schema::create('class_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('coefficient', 5, 2)->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_class_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_subjects');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('levels');
    }
};
