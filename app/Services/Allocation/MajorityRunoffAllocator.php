<?php

declare(strict_types=1);

namespace App\Services\Allocation;

/**
 * Presidential rule: a candidate wins in round one with more than half of the
 * votes of voters who voted; otherwise the two best go to a runoff, where a
 * plain majority decides. No seats are distributed — `seats` is always 1.
 */
final class MajorityRunoffAllocator implements Allocator
{
    public function allocate(array $lists, int $totalVoted, array $rules): AllocationResult
    {
        $round = (int) ($rules['round'] ?? 1);

        usort($lists, fn (array $a, array $b): int => $b['votes'] <=> $a['votes'] ?: $a['id'] <=> $b['id']);

        $first = $lists[0] ?? null;
        $second = $lists[1] ?? null;
        $majority = intdiv($totalVoted, 2) + 1;

        $winnerId = null;
        $runoff = [];
        $notes = [];

        if ($first !== null && $totalVoted > 0) {
            $firstWins = $round >= 2 ? true : $first['votes'] >= $majority;
            if ($firstWins && ($second === null || $first['votes'] !== $second['votes'])) {
                $winnerId = $first['id'];
            } elseif ($firstWins) {
                $notes[] = 'Izjednačen broj glasova prva dva kandidata — ponavljanje glasanja.';
            } else {
                $runoff = array_map(fn (array $c) => $c['id'], array_slice($lists, 0, 2));
            }
        }

        $seatsByList = [];
        foreach ($lists as $c) {
            $seatsByList[$c['id']] = $c['id'] === $winnerId ? 1 : 0;
        }

        return new AllocationResult(
            seats: 1,
            thresholdVotes: $majority,
            qualified: array_map(fn (array $c) => $c['id'], $lists),
            seatsByList: $seatsByList,
            seatOrder: $winnerId === null ? [] : [['seat_no' => 1, 'list_id' => $winnerId, 'divisor' => 1, 'quotient' => (float) $first['votes']]],
            matrix: [],
            notes: $notes,
            extra: ['round' => $round, 'winner' => $winnerId, 'runoff' => $runoff],
        );
    }
}
