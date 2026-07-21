<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mock_exams', function (Blueprint $table) {
            $table->foreignId('term_id')->nullable()->after('academic_year_id')->constrained()->nullOnDelete();
            $table->string('result_status')->default('preparation')->after('status');
            $table->timestamp('validated_at')->nullable()->after('result_status');
            $table->foreignId('validated_by')->nullable()->after('validated_at')->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable()->after('validated_by');
            $table->foreignId('finalized_by')->nullable()->after('finalized_at')->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable()->after('finalized_by');
            $table->foreignId('locked_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('mock_exam_subjects', function (Blueprint $table) {
            $table->date('exam_date')->nullable()->after('position');
            $table->time('starts_at')->nullable()->after('exam_date');
            $table->time('ends_at')->nullable()->after('starts_at');
            $table->string('supervisor_one')->nullable()->after('ends_at');
            $table->string('supervisor_two')->nullable()->after('supervisor_one');
            $table->unsignedInteger('expected_copies')->nullable()->after('supervisor_two');
            $table->unsignedInteger('received_copies')->nullable()->after('expected_copies');
            $table->unsignedInteger('absent_count')->nullable()->after('received_copies');
            $table->text('incident_notes')->nullable()->after('absent_count');
            $table->timestamp('copies_received_at')->nullable()->after('incident_notes');
            $table->string('copy_receiver_name')->nullable()->after('copies_received_at');
            $table->string('correction_teacher_name')->nullable()->after('copy_receiver_name');
            $table->decimal('fee_rate', 12, 2)->nullable()->after('correction_teacher_name');
            $table->decimal('fee_amount', 12, 2)->nullable()->after('fee_rate');
            $table->string('fee_status')->default('pending')->after('fee_amount');
            $table->timestamp('fee_paid_at')->nullable()->after('fee_status');
            $table->string('fee_payment_reference')->nullable()->after('fee_paid_at');
        });

        Schema::table('mock_exam_candidates', function (Blueprint $table) {
            $table->string('jury_decision')->nullable()->after('status');
            $table->text('jury_observation')->nullable()->after('jury_decision');
            $table->timestamp('jury_decided_at')->nullable()->after('jury_observation');
            $table->foreignId('jury_decided_by')->nullable()->after('jury_decided_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mock_exam_candidates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('jury_decided_by');
            $table->dropColumn(['jury_decision', 'jury_observation', 'jury_decided_at']);
        });

        Schema::table('mock_exam_subjects', function (Blueprint $table) {
            $table->dropColumn([
                'exam_date',
                'starts_at',
                'ends_at',
                'supervisor_one',
                'supervisor_two',
                'expected_copies',
                'received_copies',
                'absent_count',
                'incident_notes',
                'copies_received_at',
                'copy_receiver_name',
                'correction_teacher_name',
                'fee_rate',
                'fee_amount',
                'fee_status',
                'fee_paid_at',
                'fee_payment_reference',
            ]);
        });

        Schema::table('mock_exams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('term_id');
            $table->dropConstrainedForeignId('validated_by');
            $table->dropConstrainedForeignId('finalized_by');
            $table->dropConstrainedForeignId('locked_by');
            $table->dropColumn(['result_status', 'validated_at', 'finalized_at', 'locked_at']);
        });
    }
};
