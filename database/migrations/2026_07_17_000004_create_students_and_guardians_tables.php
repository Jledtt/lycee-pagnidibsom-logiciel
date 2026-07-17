<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('matricule')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->enum('gender', ['male', 'female']);
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('address')->nullable();
            $table->text('health_notes')->nullable();
            $table->enum('status', ['active', 'transferred', 'dropped', 'graduated', 'suspended'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone_primary');
            $table->string('phone_secondary')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('profession')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('guardian_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->enum('relationship', ['father', 'mother', 'tutor', 'other'])->default('tutor');
            $table->boolean('is_primary')->default(false);
            $table->boolean('can_receive_sms')->default(true);
            $table->boolean('can_pickup_child')->default(false);
            $table->timestamps();

            $table->unique(['guardian_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_student');
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('students');
    }
};
