<?php

declare(strict_types=1);

namespace App\Services\Allocation;

/**
 * D'Hondt (highest quotient) with the Serbian rules (spec §6):
 *  - threshold = threshold_pct of voters who voted;
 *  - minority lists qualify regardless of the threshold, and while they are
 *    below it every one of their quotients is multiplied by minority_coef;
 *  - ties: more total votes wins; a residual tie is recorded for a lottery.
 */
final class DHondtAllocator implements Allocator
{
    public function allocate(array $lists, int $totalVoted, array $rules): AllocationResult
    {
        $seats = (int) $rules['seats'];
        $thresholdPct = $rules['threshold_pct'] ?? null;
        $minorityCoef = $rules['minority_coef'] ?? null;

        $thresholdVotes = $thresholdPct === null ? 0 : (int) ceil($totalVoted * $thresholdPct / 100);

        $votesById = [];
        $qualified = [];
        $matrix = [];
        $quotients = [];

        foreach ($lists as $list) {
            $votesById[$list['id']] = $list['votes'];
            $belowThreshold = $list['votes'] < $thresholdVotes;

            if ($belowThreshold && ! $list['is_minority']) {
                continue;
            }
            if ($list['votes'] <= 0) {
                continue;
            }

            $qualified[] = $list['id'];
            $coef = ($belowThreshold && $list['is_minority'] && $minorityCoef !== null) ? (float) $minorityCoef : 1.0;

            for ($d = 1; $d <= $seats; $d++) {
                $q = $list['votes'] / $d * $coef;
                $matrix[$list['id']][$d] = round($q, 4);
                $quotients[] = ['list_id' => $list['id'], 'divisor' => $d, 'quotient' => $q];
            }
        }

        usort($quotients, function (array $a, array $b) use ($votesById): int {
            return $b['quotient'] <=> $a['quotient']
                ?: $votesById[$b['list_id']] <=> $votesById[$a['list_id']]
                ?: $a['list_id'] <=> $b['list_id'];
        });

        $notes = [];
        $winning = array_slice($quotients, 0, $seats);
        $this->detectUnresolvedTie($quotients, $seats, $votesById, $notes);

        $seatsByList = array_fill_keys($qualified, 0);
        $seatOrder = [];
        foreach ($winning as $i => $row) {
            $seatsByList[$row['list_id']]++;
            $seatOrder[] = [
                'seat_no' => $i + 1,
                'list_id' => $row['list_id'],
                'divisor' => $row['divisor'],
                'quotient' => round($row['quotient'], 4),
            ];
        }

        return new AllocationResult(
            seats: $seats,
            thresholdVotes: $thresholdVotes,
            qualified: $qualified,
            seatsByList: $seatsByList,
            seatOrder: $seatOrder,
            matrix: $matrix,
            notes: $notes,
        );
    }

    /**
     * The last seat is a lottery when the quotient at position `seats` equals
     * the one right after it AND both lists have the same total votes.
     *
     * @param array<int, array{list_id:int|string, divisor:int, quotient:float}> $sorted
     * @param array<int|string, int> $votesById
     * @param array<int, string> $notes
     */
    private function detectUnresolvedTie(array $sorted, int $seats, array $votesById, array &$notes): void
    {
        if ($seats <= 0 || ! isset($sorted[$seats - 1], $sorted[$seats])) {
            return;
        }
        $last = $sorted[$seats - 1];
        $next = $sorted[$seats];

        if (abs($last['quotient'] - $next['quotient']) > 1e-9) {
            return;
        }
        if ($votesById[$last['list_id']] !== $votesById[$next['list_id']]) {
            $notes[] = sprintf('Poslednji mandat: izjednačen količnik lista %s i %s, dodeljen listi sa više glasova.', $last['list_id'], $next['list_id']);

            return;
        }
        $notes[] = sprintf('ŽREB: poslednji mandat izjednačen između lista %s i %s (isti količnik i isti broj glasova).', $last['list_id'], $next['list_id']);
    }
}
