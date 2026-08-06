<?php

namespace Tests\Unit;

use App\Models\ReportCard;
use App\Services\CompetitionRankingService;
use PHPUnit\Framework\TestCase;

class CompetitionRankingServiceTest extends TestCase
{
    private CompetitionRankingService $ranking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ranking = new CompetitionRankingService;
    }

    public function test_simple_tie_uses_standard_competition_ranking(): void
    {
        $rows = $this->ranking->rank([
            ['student' => 'A', 'average' => 12.0],
            ['student' => 'B', 'average' => 12.0],
            ['student' => 'C', 'average' => 10.0],
        ]);

        $this->assertSame([1, 1, 3], array_column($rows, 'rank'));
        $this->assertSame([true, true, false], array_column($rows, 'rank_is_tied'));
    }

    public function test_triple_tie_skips_all_shared_positions(): void
    {
        $rows = $this->ranking->rank([
            ['student' => 'A', 'average' => 15.0],
            ['student' => 'B', 'average' => 12.0],
            ['student' => 'C', 'average' => 12.0],
            ['student' => 'D', 'average' => 12.0],
            ['student' => 'E', 'average' => 10.0],
        ]);

        $this->assertSame([1, 2, 2, 2, 5], array_column($rows, 'rank'));
    }

    public function test_tie_at_the_top_uses_ranks_one_one_three(): void
    {
        $rows = $this->ranking->rank([
            ['student' => 'A', 'average' => 16.0],
            ['student' => 'B', 'average' => 16.0],
            ['student' => 'C', 'average' => 14.0],
        ]);

        $this->assertSame([1, 1, 3], array_column($rows, 'rank'));
        $this->assertSame('1e ex æquo', $rows[0]['rank_label']);
    }

    public function test_null_averages_are_unranked_without_breaking_the_sequence(): void
    {
        $rows = $this->ranking->rank([
            ['student' => 'A', 'average' => null],
            ['student' => 'B', 'average' => 15.0],
            ['student' => 'C', 'average' => 13.0],
        ]);

        $this->assertSame(['B', 'C', 'A'], array_column($rows, 'student'));
        $this->assertSame([1, 2, null], array_column($rows, 'rank'));
    }

    public function test_equal_averages_are_compared_after_rounding_to_two_decimals(): void
    {
        $rows = $this->ranking->rank([
            ['student' => 'A', 'average' => 18.0],
            ['student' => 'B', 'average' => 17.0],
            ['student' => 'C', 'average' => 14.504],
            ['student' => 'D', 'average' => 14.503],
            ['student' => 'E', 'average' => 14.2],
        ]);

        $this->assertSame([1, 2, 3, 3, 5], array_column($rows, 'rank'));
        $this->assertSame('3e ex æquo', $rows[2]['rank_label']);
    }

    public function test_report_card_accessor_exposes_the_shared_rank_label(): void
    {
        $reportCard = new ReportCard([
            'rank' => 3,
            'rank_is_tied' => true,
        ]);

        $this->assertSame('3e ex æquo', $reportCard->rank_label);
    }
}
