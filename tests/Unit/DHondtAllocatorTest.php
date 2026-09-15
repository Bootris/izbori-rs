<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Allocation\DHondtAllocator;
use PHPUnit\Framework\TestCase;

class DHondtAllocatorTest extends TestCase
{
    private function rules(int $seats = 250): array
    {
        return ['seats' => $seats, 'threshold_pct' => 3.0, 'minority_coef' => 1.35];
    }

    public function test_textbook_dhondt_distribution(): void
    {
        // Classic example: 8 seats, votes 100k/80k/30k/20k → 4/3/1/0
        $result = (new DHondtAllocator())->allocate([
            ['id' => 'A', 'votes' => 100_000, 'is_minority' => false],
            ['id' => 'B', 'votes' => 80_000, 'is_minority' => false],
            ['id' => 'C', 'votes' => 30_000, 'is_minority' => false],
            ['id' => 'D', 'votes' => 20_000, 'is_minority' => false],
        ], 230_000, ['seats' => 8, 'threshold_pct' => null, 'minority_coef' => null]);

        $this->assertSame(['A' => 4, 'B' => 3, 'C' => 1, 'D' => 0], $result->seatsByList);
        $this->assertSame(8, array_sum($result->seatsByList));
        $this->assertSame('A', $result->seatOrder[0]['list_id']);
        $this->assertSame(1, $result->seatOrder[0]['divisor']);
    }

    public function test_threshold_is_three_percent_of_voters_who_voted(): void
    {
        $result = (new DHondtAllocator())->allocate([
            ['id' => 1, 'votes' => 900_000, 'is_minority' => false],
            ['id' => 2, 'votes' => 29_999, 'is_minority' => false],   // just under 3 % of 1,000,000
            ['id' => 3, 'votes' => 30_000, 'is_minority' => false],   // exactly 3 %
        ], 1_000_000, $this->rules());

        $this->assertSame(30_000, $result->thresholdVotes);
        $this->assertSame([1, 3], $result->qualified);
        $this->assertSame(0, $result->seatsByList[2] ?? 0);
        $this->assertSame(250, array_sum($result->seatsByList));
    }

    public function test_minority_list_below_threshold_qualifies_with_boosted_quotients(): void
    {
        $result = (new DHondtAllocator())->allocate([
            ['id' => 'big', 'votes' => 970_000, 'is_minority' => false],
            ['id' => 'min', 'votes' => 10_000, 'is_minority' => true],   // 1 % — below threshold
        ], 1_000_000, $this->rules());

        $this->assertContains('min', $result->qualified);
        $this->assertSame(13_500.0, $result->matrix['min'][1]);          // 10 000 × 1.35
        $this->assertSame(970_000.0, $result->matrix['big'][1]);         // no boost above threshold
        $this->assertGreaterThan(0, $result->seatsByList['min']);
        $this->assertSame(250, array_sum($result->seatsByList));
    }

    public function test_minority_list_above_threshold_is_not_boosted(): void
    {
        $result = (new DHondtAllocator())->allocate([
            ['id' => 'a', 'votes' => 500_000, 'is_minority' => false],
            ['id' => 'm', 'votes' => 100_000, 'is_minority' => true],   // 10 %
        ], 1_000_000, $this->rules(10));

        $this->assertSame(100_000.0, $result->matrix['m'][1]);
        $this->assertSame(['a' => 9, 'm' => 1], $result->seatsByList);   // m/1 = 100 000 takes the 6th seat, nothing else
    }

    public function test_equal_quotient_goes_to_list_with_more_total_votes(): void
    {
        // A/2 = 50 000 and B/1 = 50 000 compete for the 3rd seat → A (more votes) wins.
        $result = (new DHondtAllocator())->allocate([
            ['id' => 'A', 'votes' => 100_000, 'is_minority' => false],
            ['id' => 'B', 'votes' => 50_000, 'is_minority' => false],
        ], 150_000, ['seats' => 2, 'threshold_pct' => null, 'minority_coef' => null]);

        $this->assertSame(['A' => 2, 'B' => 0], $result->seatsByList);
        $this->assertNotEmpty($result->notes);
        $this->assertStringContainsString('više glasova', $result->notes[0]);
    }

    public function test_unresolved_tie_is_flagged_for_lottery(): void
    {
        $result = (new DHondtAllocator())->allocate([
            ['id' => 'A', 'votes' => 1_000, 'is_minority' => false],
            ['id' => 'B', 'votes' => 1_000, 'is_minority' => false],
        ], 2_000, ['seats' => 1, 'threshold_pct' => null, 'minority_coef' => null]);

        $this->assertSame(1, array_sum($result->seatsByList));
        $this->assertStringContainsString('ŽREB', $result->notes[0]);
    }

    public function test_no_votes_means_no_seats(): void
    {
        $result = (new DHondtAllocator())->allocate([
            ['id' => 1, 'votes' => 0, 'is_minority' => false],
        ], 0, $this->rules());

        $this->assertSame([], $result->qualified);
        $this->assertSame([], $result->seatOrder);
    }
}
