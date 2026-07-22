<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->string('entry_mode')->default('standard');
        });

        Schema::table('grades', function (Blueprint $table): void {
            $table->string('status')->default('graded');
        });

        DB::table('grades')
            ->where('is_absent', true)
            ->update(['status' => 'absent']);
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table): void {
            $table->dropColumn('status');
        });

        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropColumn('entry_mode');
        });
    }
};
