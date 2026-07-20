<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('term_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('position')->default(1);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['term_id', 'position']);
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->foreignId('term_period_id')
                ->nullable()
                ->after('term_id')
                ->constrained('term_periods')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('term_period_id');
        });

        Schema::dropIfExists('term_periods');
    }
};
