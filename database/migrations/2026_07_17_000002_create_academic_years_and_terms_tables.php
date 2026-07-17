<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->boolean('is_active')->default(false);
            $table->enum('status', ['planned', 'active', 'closed', 'archived'])->default('planned');
            $table->timestamps();
        });

        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['trimestre', 'semestre', 'autre'])->default('trimestre');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->unsignedSmallInteger('position')->default(1);
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique(['academic_year_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms');
        Schema::dropIfExists('academic_years');
    }
};
