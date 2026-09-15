<?php

declare(strict_types=1);

namespace App\Services\Allocation;

/**
 * Allocator output, serialisable straight into the results snapshot.
 *
 * @phpstan-type SeatRow array{seat_no:int, list_id:int|string, divisor:int, quotient:float}
 */
final class AllocationResult
{
    /**
     * @param array<int|string, int>   $seatsByList     list id => seats won
     * @param array<int, array{seat_no:int, list_id:int|string, divisor:int, quotient:float}> $seatOrder
     * @param array<int|string, array<int, float>> $matrix   list id => [divisor => quotient] (qualified lists only)
     * @param array<int|string> $qualified   list ids that passed the threshold (or are minority lists)
     * @param array<int, string> $notes      human-readable notes (tie-breaks, lottery needed…)
     * @param array<string, mixed> $extra    allocator-specific data (winner, runoff…)
     */
    public function __construct(
        public readonly int $seats,
        public readonly int $thresholdVotes,
        public readonly array $qualified,
        public readonly array $seatsByList,
        public readonly array $seatOrder,
        public readonly array $matrix,
        public readonly array $notes = [],
        public readonly array $extra = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'seats' => $this->seats,
            'threshold_votes' => $this->thresholdVotes,
            'qualified' => array_values($this->qualified),
            'seats_by_list' => $this->seatsByList,
            'seat_order' => $this->seatOrder,
            'matrix' => $this->matrix,
            'notes' => $this->notes,
            ...$this->extra,
        ];
    }
}
