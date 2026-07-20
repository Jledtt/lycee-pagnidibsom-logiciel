<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            ['name' => 'Interrogation', 'weight' => 0, 'status' => 'inactive'],
            ['name' => 'Devoir', 'weight' => 40, 'status' => 'active'],
            ['name' => 'Composition', 'weight' => 60, 'status' => 'active'],
        ] as $type) {
            DB::table('assessment_types')->updateOrInsert(
                ['name' => $type['name']],
                [
                    'weight' => $type['weight'],
                    'status' => $type['status'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('assessment_types')->where('name', 'Interrogation')->update([
            'weight' => 1,
            'status' => 'active',
            'updated_at' => now(),
        ]);

        DB::table('assessment_types')->where('name', 'Devoir')->update([
            'weight' => 1,
            'status' => 'active',
            'updated_at' => now(),
        ]);

        DB::table('assessment_types')->where('name', 'Composition')->update([
            'weight' => 2,
            'status' => 'active',
            'updated_at' => now(),
        ]);
    }
};
