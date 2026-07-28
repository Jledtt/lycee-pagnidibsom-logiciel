<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mock_exam_subjects', function (Blueprint $table) {
            $table->decimal('fee_quantity', 10, 2)->nullable()->after('correction_teacher_name');
            $table->string('fee_quantity_unit', 30)->default('copies')->after('fee_quantity');
            $table->decimal('fee_withholding_amount', 12, 2)->default(0)->after('fee_amount');
            $table->decimal('fee_advance_amount', 12, 2)->default(0)->after('fee_withholding_amount');
            $table->decimal('fee_other_deduction_amount', 12, 2)->default(0)->after('fee_advance_amount');
            $table->string('beneficiary_identity_type', 30)->nullable()->after('fee_other_deduction_amount');
            $table->string('beneficiary_identity_number', 80)->nullable()->after('beneficiary_identity_type');
        });
    }

    public function down(): void
    {
        Schema::table('mock_exam_subjects', function (Blueprint $table) {
            $table->dropColumn([
                'fee_quantity',
                'fee_quantity_unit',
                'fee_withholding_amount',
                'fee_advance_amount',
                'fee_other_deduction_amount',
                'beneficiary_identity_type',
                'beneficiary_identity_number',
            ]);
        });
    }
};
