<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->nullable()->unique();
            $table->boolean('is_required')->default(true);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('fee_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_type_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('due_date')->nullable();
            $table->string('period')->nullable();
            $table->timestamps();

            $table->unique(['academic_year_id', 'school_class_id', 'fee_type_id', 'period'], 'fee_schedule_unique');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('paid_at');
            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['cash', 'mobile_money', 'bank_transfer', 'other'])->default('cash');
            $table->enum('status', ['valid', 'cancelled'])->default('valid');
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_lines');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('fee_schedules');
        Schema::dropIfExists('fee_types');
    }
};
