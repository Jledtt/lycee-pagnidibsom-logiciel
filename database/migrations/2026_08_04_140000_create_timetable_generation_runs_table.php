<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_generation_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('draft');
            $table->string('solver_status', 30)->nullable();
            $table->json('input_snapshot');
            $table->json('result')->nullable();
            $table->json('diagnostics')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['academic_year_id', 'created_at'], 'timetable_generation_year_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_generation_runs');
    }
};
