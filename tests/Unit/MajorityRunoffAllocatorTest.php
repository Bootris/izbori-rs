<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Allocation\MajorityRunoffAllocator;
use PHPUnit\Framework\TestCase;

class MajorityRunoffAllocatorTest extends TestCase
{
    public function test_absolute_majority_wins_first_round(): void
    {
        $r = (new MajorityRunoffAllocator())->allocate([
            ['id' => 1, 'votes' => 5_001, 'is_minority' => false],
            ['id' => 2, 'votes' => 3_000, 'is_minority' => false],
            ['id' => 3, 'votes' => 1_999, 'is_minority' => false],
        ], 10_000, ['seats' => 1, 'threshold_pct' => null, 'minority_coef' => null, 'round' => 1]);

        $this->assertSame(1, $r->extra['winner']);
        $this->assertSame([], $r->extra['runoff']);
        $this->assertSame(1, $r->seatsByList[1]);
    }

    public function test_no_majority_sends_top_two_to_runoff(): void
    {
        $r = (new MajorityRunoffAllocator())->allocate([
            ['id' => 1, 'votes' => 4_500, 'is_minority' => false],
            ['id' => 2, 'votes' => 3_500, 'is_minority' => false],
            ['id' => 3, 'votes' => 2_000, 'is_minority' => false],
        ], 10_000, ['seats' => 1, 'threshold_pct' => null, 'minority_coef' => null, 'round' => 1]);

        $this->assertNull($r->extra['winner']);
        $this->assertSame([1, 2], $r->extra['runoff']);
    }

    public function test_plain_majority_decides_second_round(): void
    {
        $r = (new MajorityRunoffAllocator())->allocate([
            ['id' => 1, 'votes' => 4_000, 'is_minority' => false],
            ['id' => 2, 'votes' => 3_000, 'is_minority' => false],
        ], 7_500, ['seats' => 1, 'threshold_pct' => null, 'minority_coef' => null, 'round' => 2]);

        $this->assertSame(1, $r->extra['winner']);
    }
}
