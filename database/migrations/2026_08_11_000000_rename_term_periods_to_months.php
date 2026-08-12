<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $compositionTypeIds = DB::table('assessment_types')
            ->where('name', 'Composition')
            ->pluck('id');

        DB::table('terms')
            ->orderBy('id')
            ->get(['id', 'position'])
            ->each(function (object $term) use ($compositionTypeIds): void {
                $definitions = $this->definitionsFor((int) $term->position);

                if ($definitions === []) {
                    return;
                }

                $periodIds = [];

                foreach ($definitions as $definition) {
                    $existingPeriod = DB::table('term_periods')
                        ->where('term_id', $term->id)
                        ->where('position', $definition['position'])
                        ->first();

                    if ($existingPeriod) {
                        DB::table('term_periods')
                            ->where('id', $existingPeriod->id)
                            ->update([
                                'name' => $definition['name'],
                                'status' => 'active',
                                'updated_at' => now(),
                            ]);
                        $periodIds[$definition['position']] = $existingPeriod->id;

                        continue;
                    }

                    $periodIds[$definition['position']] = DB::table('term_periods')->insertGetId([
                        'term_id' => $term->id,
                        'name' => $definition['name'],
                        'position' => $definition['position'],
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $compositionPosition = collect($definitions)
                    ->firstWhere('name', 'Composition')['position'];
                $compositionPeriodId = $periodIds[$compositionPosition];
                $firstPeriodId = $periodIds[$definitions[0]['position']];

                if ($compositionTypeIds->isNotEmpty()) {
                    DB::table('assessments')
                        ->where('term_id', $term->id)
                        ->whereIn('assessment_type_id', $compositionTypeIds)
                        ->update(['term_period_id' => $compositionPeriodId]);
                }

                DB::table('assessments')
                    ->where('term_id', $term->id)
                    ->whereNull('term_period_id')
                    ->update(['term_period_id' => $firstPeriodId]);

                $obsoletePeriodIds = DB::table('term_periods')
                    ->where('term_id', $term->id)
                    ->whereNotIn('position', array_column($definitions, 'position'))
                    ->pluck('id');

                if ($obsoletePeriodIds->isNotEmpty()) {
                    DB::table('assessments')
                        ->where('term_id', $term->id)
                        ->whereIn('term_period_id', $obsoletePeriodIds)
                        ->update(['term_period_id' => $compositionPeriodId]);

                    DB::table('term_periods')
                        ->whereIn('id', $obsoletePeriodIds)
                        ->update([
                            'status' => 'inactive',
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('terms')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $term): void {
                foreach ([
                    1 => '1er devoir',
                    2 => '2e devoir',
                    3 => '3e devoir',
                ] as $position => $name) {
                    DB::table('term_periods')->updateOrInsert(
                        [
                            'term_id' => $term->id,
                            'position' => $position,
                        ],
                        [
                            'name' => $name,
                            'status' => 'active',
                            'updated_at' => now(),
                        ],
                    );
                }
            });
    }

    private function definitionsFor(int $termPosition): array
    {
        return match ($termPosition) {
            1 => [
                ['position' => 1, 'name' => 'Octobre'],
                ['position' => 2, 'name' => 'Novembre'],
                ['position' => 3, 'name' => 'Composition'],
            ],
            2 => [
                ['position' => 1, 'name' => 'Janvier'],
                ['position' => 2, 'name' => 'Février'],
                ['position' => 3, 'name' => 'Composition'],
            ],
            3 => [
                ['position' => 1, 'name' => 'Avril'],
                ['position' => 2, 'name' => 'Composition'],
            ],
            default => [],
        };
    }
};
