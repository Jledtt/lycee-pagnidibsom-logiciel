<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetables', function (Blueprint $table): void {
            $table->timestamp('published_at')->nullable()->after('status');
            $table->foreignId('published_by')->nullable()->after('published_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('timetable_entries', function (Blueprint $table): void {
            $table->foreignId('generation_run_id')->nullable()->after('timetable_id')
                ->constrained('timetable_generation_runs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('generation_run_id');
        });

        Schema::table('timetables', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('published_by');
            $table->dropColumn('published_at');
        });
    }
};
