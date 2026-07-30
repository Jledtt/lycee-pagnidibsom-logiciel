<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('teacher_fee_statement_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->restrictOnDelete();
            $table->unique('teacher_fee_statement_id', 'expenses_teacher_fee_statement_unique');
        });

        DB::table('teacher_fee_statements')
            ->where('status', 'paid')
            ->orderBy('id')
            ->each(function (object $statement): void {
                $createdBy = $statement->paid_by ?: $statement->created_by;
                $paidAt = $statement->paid_at ?: $statement->updated_at ?: now();

                if (! $createdBy) {
                    return;
                }

                DB::table('expenses')->insert([
                    'teacher_fee_statement_id' => $statement->id,
                    'academic_year_id' => $statement->academic_year_id,
                    'spent_at' => substr((string) $paidAt, 0, 10),
                    'category' => 'salaries',
                    'beneficiary' => $statement->beneficiary_name,
                    'payment_method' => $this->accountingPaymentMethod($statement->payment_method),
                    'amount' => $statement->net_amount,
                    'proof_reference' => $statement->payment_reference ?: $statement->reference,
                    'status' => 'valid',
                    'notes' => 'Générée automatiquement depuis l’ordre d’honoraires '.$statement->reference.'.',
                    'created_by' => $createdBy,
                    'created_at' => $paidAt,
                    'updated_at' => $paidAt,
                ]);
            });

        Schema::table('teacher_work_sessions', function (Blueprint $table) {
            $table->unique(
                ['teacher_id', 'session_date', 'school_class_id', 'subject_id', 'starts_at', 'ends_at'],
                'teacher_work_sessions_unique_slot',
            );
        });
    }

    public function down(): void
    {
        Schema::table('teacher_work_sessions', function (Blueprint $table) {
            $table->dropUnique('teacher_work_sessions_unique_slot');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['teacher_fee_statement_id']);
            $table->dropUnique('expenses_teacher_fee_statement_unique');
            $table->dropColumn('teacher_fee_statement_id');
        });
    }

    private function accountingPaymentMethod(?string $method): string
    {
        return match ($method) {
            'Espèces' => 'cash',
            'Virement', 'Chèque' => 'bank_transfer',
            'Mobile Money' => 'mobile_money',
            default => 'other',
        };
    }
};
