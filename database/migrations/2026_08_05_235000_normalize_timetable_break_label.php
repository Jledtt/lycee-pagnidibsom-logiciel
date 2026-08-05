<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('timetable_periods')
            ->where('label', 'RECREATION')
            ->update(['label' => 'RÉCRÉATION']);
    }

    public function down(): void
    {
        DB::table('timetable_periods')
            ->where('label', 'RÉCRÉATION')
            ->update(['label' => 'RECREATION']);
    }
};
