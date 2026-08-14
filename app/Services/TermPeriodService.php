<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Term;
use App\Models\TermPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TermPeriodService
{
    /** @return Collection<int, TermPeriod> */
    public function ensureDefaults(Term $term): Collection
    {
        return DB::transaction(function () use ($term): Collection {
            $periods = collect($this->definitionsFor($term))
                ->map(fn (array $period): TermPeriod => TermPeriod::query()->updateOrCreate(
                    [
                        'term_id' => $term->id,
                        'position' => $period['position'],
                    ],
                    [
                        'name' => $period['name'],
                        'status' => 'active',
                    ],
                ));

            $firstPeriod = $periods->first();
            $compositionPeriod = $periods->firstWhere('name', 'Composition');

            if ($compositionPeriod) {
                Assessment::query()
                    ->where('term_id', $term->id)
                    ->whereHas('assessmentType', fn ($query) => $query->where('name', 'Composition'))
                    ->update(['term_period_id' => $compositionPeriod->id]);
            }

            if ($firstPeriod) {
                Assessment::query()
                    ->where('term_id', $term->id)
                    ->whereNull('term_period_id')
                    ->update(['term_period_id' => $firstPeriod->id]);
            }

            $obsoletePeriods = $term->periods()
                ->whereNotIn('position', $periods->pluck('position'))
                ->get();

            foreach ($obsoletePeriods as $obsoletePeriod) {
                if ($compositionPeriod) {
                    Assessment::query()
                        ->where('term_id', $term->id)
                        ->where('term_period_id', $obsoletePeriod->id)
                        ->update(['term_period_id' => $compositionPeriod->id]);
                }

                $obsoletePeriod->update(['status' => 'inactive']);
            }

            return $term->periods()
                ->where('status', 'active')
                ->orderBy('position')
                ->get();
        });
    }

    /** @return list<array{position: int, name: string}> */
    public function definitionsFor(Term $term): array
    {
        return match ((int) $term->position) {
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
            default => [
                ['position' => 1, 'name' => 'Période 1'],
                ['position' => 2, 'name' => 'Période 2'],
                ['position' => 3, 'name' => 'Composition'],
            ],
        };
    }
}
