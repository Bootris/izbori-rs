<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Protocol;
use App\Services\Validation\ProtocolValidator;
use PHPUnit\Framework\TestCase;

class ProtocolValidatorTest extends TestCase
{
    private function protocol(array $overrides = []): Protocol
    {
        $p = new Protocol();
        foreach ($overrides + [
            'registered_voters' => 1000,
            'ballots_received' => 1020,
            'ballots_unused' => 420,
            'voters_voted' => 600,
            'ballots_in_box' => 600,
            'ballots_valid' => 590,
            'ballots_invalid' => 10,
        ] as $k => $v) {
            $p->{$k} = $v;
        }

        return $p;
    }

    public function test_consistent_protocol_passes(): void
    {
        $result = (new ProtocolValidator())->validate($this->protocol(), [1 => 300, 2 => 290]);

        $this->assertTrue($result->passes());
        $this->assertSame(0, $result->deviation);
    }

    public function test_deviation_between_box_and_voters_is_reported_as_k4(): void
    {
        $result = (new ProtocolValidator())->validate(
            $this->protocol(['ballots_in_box' => 598, 'ballots_valid' => 588]),
            [1 => 300, 2 => 288],
        );

        $this->assertFalse($result->passes());
        $this->assertSame(-2, $result->deviation);
        $this->assertArrayHasKey('K4', $result->errors);
        $this->assertArrayNotHasKey('K3', $result->errors);
        $this->assertArrayNotHasKey('K5', $result->errors);
    }

    public function test_vote_sum_must_equal_valid_ballots(): void
    {
        $result = (new ProtocolValidator())->validate($this->protocol(), [1 => 300, 2 => 291]);

        $this->assertArrayHasKey('K5', $result->errors);
        $this->assertCount(1, $result->errors);
    }

    public function test_more_voters_than_registered_is_k6(): void
    {
        $result = (new ProtocolValidator())->validate(
            $this->protocol(['registered_voters' => 599]),
            [1 => 300, 2 => 290],
        );

        $this->assertArrayHasKey('K6', $result->errors);
    }

    public function test_negative_numbers_are_k7(): void
    {
        $result = (new ProtocolValidator())->validate($this->protocol(['ballots_invalid' => -1]), [1 => 300, 2 => 290]);

        $this->assertArrayHasKey('K7', $result->errors);
    }
}
