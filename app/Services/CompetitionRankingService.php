<?php

namespace App\Services;

class CompetitionRankingService
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function rank(
        array $rows,
        string $averageKey = 'average',
        string $rankKey = 'rank',
        string $tieKey = 'rank_is_tied',
        string $labelKey = 'rank_label',
    ): array {
        usort($rows, function (array $left, array $right) use ($averageKey): int {
            $leftAverage = $left[$averageKey] ?? null;
            $rightAverage = $right[$averageKey] ?? null;

            if ($leftAverage === null) {
                return $rightAverage === null ? 0 : 1;
            }

            if ($rightAverage === null) {
                return -1;
            }

            return round((float) $rightAverage, 2) <=> round((float) $leftAverage, 2);
        });

        $tieCounts = [];

        foreach ($rows as $row) {
            $average = $row[$averageKey] ?? null;

            if ($average === null) {
                continue;
            }

            $key = $this->averageKey((float) $average);
            $tieCounts[$key] = ($tieCounts[$key] ?? 0) + 1;
        }

        $position = 0;
        $previousAverage = null;
        $previousRank = null;

        foreach ($rows as &$row) {
            $average = $row[$averageKey] ?? null;

            if ($average === null) {
                $row[$rankKey] = null;
                $row[$tieKey] = false;
                $row[$labelKey] = null;

                continue;
            }

            $position++;
            $roundedAverage = round((float) $average, 2);
            $rank = $previousAverage !== null && $roundedAverage === $previousAverage
                ? $previousRank
                : $position;
            $isTied = $tieCounts[$this->averageKey($roundedAverage)] > 1;

            $row[$rankKey] = $rank;
            $row[$tieKey] = $isTied;
            $row[$labelKey] = $this->label($rank, $isTied);
            $previousAverage = $roundedAverage;
            $previousRank = $rank;
        }
        unset($row);

        return $rows;
    }

    public function label(?int $rank, bool $isTied): ?string
    {
        if ($rank === null) {
            return null;
        }

        return $rank.'e'.($isTied ? ' ex æquo' : '');
    }

    private function averageKey(float $average): string
    {
        return number_format(round($average, 2), 2, '.', '');
    }
}
