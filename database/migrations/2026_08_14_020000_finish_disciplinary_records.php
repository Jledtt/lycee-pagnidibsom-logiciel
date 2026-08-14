<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disciplinary_records', function (Blueprint $table): void {
            $table->enum('status', ['active', 'resolved', 'cancelled'])
                ->default('active')
                ->after('description');
            $table->text('action_taken')->nullable()->after('status');
            $table->dateTime('resolved_at')->nullable()->after('record_date');
            $table->foreignId('resolved_by')->nullable()->after('resolved_at')->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable()->after('resolved_by');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('cancelled_by');

            $table->index(
                ['academic_year_id', 'status', 'record_date'],
                'disciplinary_records_year_status_date_index',
            );
            $table->index(
                ['student_id', 'status'],
                'disciplinary_records_student_status_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('disciplinary_records', function (Blueprint $table): void {
            $table->dropIndex('disciplinary_records_year_status_date_index');
            $table->dropIndex('disciplinary_records_student_status_index');
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn([
                'status',
                'action_taken',
                'resolved_at',
                'cancelled_at',
                'cancellation_reason',
            ]);
        });
    }
};
