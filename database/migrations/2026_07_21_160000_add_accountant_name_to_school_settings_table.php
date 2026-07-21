<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->string('accountant_name')->nullable()->after('principal_title');
        });

        DB::table('school_settings')->whereNull('accountant_name')->update([
            'accountant_name' => 'Le Comptable',
        ]);
    }

    public function down(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->dropColumn('accountant_name');
        });
    }
};
