<?php

declare(strict_types=1);

namespace App\Services\Validation;

use App\Models\Protocol;

/**
 * Control sums every polling-station protocol must satisfy before it is
 * allowed into the aggregates (spec §5). A failing protocol is stored, shown
 * publicly as "flagged", but never summed.
 *
 *   K1  ballots_received = ballots_unused + ballots_used
 *   K2  ballots_used     = voters_voted
 *   K3  ballots_in_box   = ballots_valid + ballots_invalid
 *   K4  deviation        = ballots_in_box − voters_voted   → must be 0
 *   K5  Σ list votes     = ballots_valid
 *   K6  voters_voted     ≤ registered_voters
 *   K7  every number     ≥ 0
 */
final class ProtocolValidator
{
    /**
     * @param array<int, int> $itemVotes  electoral_list_id => votes
     */
    public function validate(Protocol $protocol, array $itemVotes): ValidationResult
    {
        $errors = [];
        $p = $protocol;

        foreach (Protocol::COUNT_FIELDS as $field) {
            if ((int) $p->{$field} < 0) {
                $errors['K7'] = "Polje {$field} ne može biti negativno.";
            }
        }
        foreach ($itemVotes as $votes) {
            if ($votes < 0) {
                $errors['K7'] = 'Broj glasova po listi ne može biti negativan.';
            }
        }

        $ballotsUsed = $p->ballots_received - $p->ballots_unused;
        if ($ballotsUsed < 0) {
            $errors['K1'] = "Neupotrebljenih listića ({$p->ballots_unused}) ima više nego primljenih ({$p->ballots_received}).";
        }

        if ($ballotsUsed !== (int) $p->voters_voted) {
            $errors['K2'] = "Upotrebljeni listići ({$ballotsUsed}) ≠ birača koji su glasali ({$p->voters_voted}).";
        }

        $validPlusInvalid = $p->ballots_valid + $p->ballots_invalid;
        if ((int) $p->ballots_in_box !== $validPlusInvalid) {
            $errors['K3'] = "Listići u kutiji ({$p->ballots_in_box}) ≠ važeći + nevažeći ({$validPlusInvalid}).";
        }

        $deviation = $p->ballots_in_box - $p->voters_voted;
        if ($deviation !== 0) {
            $errors['K4'] = "Odstupanje listića u kutiji od broja glasalih: {$deviation}.";
        }

        $sumVotes = array_sum($itemVotes);
        if ($sumVotes !== (int) $p->ballots_valid) {
            $errors['K5'] = "Zbir glasova po listama ({$sumVotes}) ≠ važećih listića ({$p->ballots_valid}).";
        }

        if ($p->voters_voted > $p->registered_voters) {
            $errors['K6'] = "Glasalo je više birača ({$p->voters_voted}) nego što je upisano ({$p->registered_voters}).";
        }

        ksort($errors);

        return new ValidationResult($errors, $deviation);
    }
}
