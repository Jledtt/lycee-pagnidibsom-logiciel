<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_tracks', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->enum('kind', ['serie', 'filiere'])->default('serie');
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['kind', 'name'], 'academic_tracks_kind_name_unique');
            $table->index(['status', 'kind'], 'academic_tracks_status_kind_index');
        });

        Schema::table('school_classes', function (Blueprint $table): void {
            $table->foreignId('academic_track_id')
                ->nullable()
                ->after('level_id')
                ->constrained('academic_tracks')
                ->nullOnDelete();
        });

        $now = now();
        DB::table('academic_tracks')->insert([
            ['name' => 'Série A', 'code' => 'A', 'kind' => 'serie', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Série C', 'code' => 'C', 'kind' => 'serie', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $trackIds = DB::table('academic_tracks')->whereIn('code', ['A', 'C'])->pluck('id', 'code');

        DB::table('school_classes')
            ->where(function ($query): void {
                $query->whereRaw('LOWER(name) = ?', ['2nde a'])
                    ->orWhereRaw('UPPER(code) = ?', ['2NDA']);
            })
            ->update(['academic_track_id' => $trackIds['A']]);

        DB::table('school_classes')
            ->where(function ($query): void {
                $query->whereRaw('LOWER(name) = ?', ['2nde c'])
                    ->orWhereRaw('UPPER(code) = ?', ['2NDC']);
            })
            ->update(['academic_track_id' => $trackIds['C']]);
    }

    public function down(): void
    {
        Schema::table('school_classes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('academic_track_id');
        });

        Schema::dropIfExists('academic_tracks');
    }
};
