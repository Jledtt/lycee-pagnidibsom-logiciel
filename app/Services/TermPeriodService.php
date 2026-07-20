<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Term;
use App\Models\TermPeriod;
use Illuminate\Support\Collection;

class TermPeriodService
{
    public function ensureDefaults(Term $term): Collection
    {
        $firstPeriod = null;

        foreach ([
            ['position' => 1, 'name' => '1er devoir'],
            ['position' => 2, 'name' => '2e devoir'],
            ['position' => 3, 'name' => '3e devoir'],
        ] as $period) {
            $createdPeriod = TermPeriod::query()->firstOrCreate(
                [
                    'term_id' => $term->id,
                    'position' => $period['position'],
                ],
                [
                    'name' => $period['name'],
                    'status' => 'active',
                ],
            );

            if ((int) $period['position'] === 1) {
                $firstPeriod = $createdPeriod;
            }
        }

        if ($firstPeriod) {
            Assessment::query()
                ->where('term_id', $term->id)
                ->whereNull('term_period_id')
                ->update(['term_period_id' => $firstPeriod->id]);
        }

        return $term->periods()
            ->where('status', 'active')
            ->orderBy('position')
            ->get();
    }
}
