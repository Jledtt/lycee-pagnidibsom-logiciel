<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->enum('gender', ['male', 'female'])->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('students')->whereNull('gender')->update(['gender' => 'male']);

        Schema::table('students', function (Blueprint $table) {
            $table->enum('gender', ['male', 'female'])->nullable(false)->change();
        });
    }
};
