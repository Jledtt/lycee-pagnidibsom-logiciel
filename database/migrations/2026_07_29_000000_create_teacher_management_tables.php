<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('employee_number')->nullable()->unique();
            $table->string('specialty')->nullable();
            $table->string('identity_document_type', 30)->nullable();
            $table->string('identity_document_number', 80)->nullable();
            $table->date('identity_document_issued_at')->nullable();
            $table->date('identity_document_expires_at')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->decimal('default_hourly_rate', 12, 2)->default(0);
            $table->decimal('withholding_tax_rate', 5, 2)->default(2);
            $table->string('payment_method', 40)->nullable();
            $table->string('payment_reference', 120)->nullable();
            $table->string('contract_type', 60)->nullable();
            $table->date('hired_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('teacher_work_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->date('session_date');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->decimal('hours_worked', 5, 2);
            $table->decimal('hourly_rate', 12, 2)->nullable();
            $table->enum('status', ['draft', 'validated', 'cancelled'])->default('draft');
            $table->dateTime('teacher_signed_at')->nullable();
            $table->dateTime('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['teacher_id', 'session_date']);
            $table->index(['academic_year_id', 'status']);
        });

        Schema::create('teacher_fee_statements', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->date('period_month');
            $table->string('beneficiary_name');
            $table->string('identity_document_type', 30)->nullable();
            $table->string('identity_document_number', 80)->nullable();
            $table->decimal('gross_amount', 14, 2)->default(0);
            $table->decimal('withholding_tax_rate', 5, 2)->default(2);
            $table->decimal('withholding_tax_amount', 14, 2)->default(0);
            $table->decimal('advance_amount', 14, 2)->default(0);
            $table->decimal('other_deduction_amount', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->enum('status', ['draft', 'approved', 'paid', 'cancelled'])->default('draft');
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_method', 40)->nullable();
            $table->string('payment_reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['academic_year_id', 'period_month']);
            $table->index(['teacher_id', 'status']);
        });

        Schema::create('teacher_fee_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_fee_statement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_work_session_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('hours', 7, 2);
            $table->decimal('hourly_rate', 12, 2);
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });

        Schema::create('teacher_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('document_type', 60);
            $table->string('document_number', 100)->nullable();
            $table->string('file_path');
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_documents');
        Schema::dropIfExists('teacher_fee_lines');
        Schema::dropIfExists('teacher_fee_statements');
        Schema::dropIfExists('teacher_work_sessions');
        Schema::dropIfExists('teacher_profiles');
    }
};
