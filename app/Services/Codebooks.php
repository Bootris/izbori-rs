<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AllocationMethod;
use App\Enums\ElectionStatus;
use App\Enums\ElectionType;
use App\Enums\ProtocolStatus;
use App\Enums\SubmitterType;

/**
 * One flat code table with a `table` discriminator (the Hungarian Kodtablak
 * pattern): every enumeration the frontend needs, in one file, translatable.
 */
final class Codebooks
{
    /** @return array<int, array{table:string, code:string, label:string}> */
    public function all(): array
    {
        $rows = [];

        foreach ([
            'ELECTION_TYPE' => ElectionType::cases(),
            'ELECTION_STATUS' => ElectionStatus::cases(),
            'ALLOCATION' => AllocationMethod::cases(),
            'PROTOCOL_STATUS' => ProtocolStatus::cases(),
            'SUBMITTER_TYPE' => SubmitterType::cases(),
        ] as $table => $cases) {
            foreach ($cases as $case) {
                $rows[] = ['table' => $table, 'code' => $case->value, 'label' => $case->getLabel()];
            }
        }

        foreach (config('izbori.turnout_cutoffs') as $i => $cutoff) {
            $rows[] = ['table' => 'TURNOUT_CUTOFF', 'code' => (string) $cutoff, 'label' => 'Presek '.($i + 1).' — '.$cutoff];
        }

        foreach ([
            'K1' => 'Primljeni listići = neupotrebljeni + upotrebljeni',
            'K2' => 'Upotrebljeni listići = birači koji su glasali',
            'K3' => 'Listići u kutiji = važeći + nevažeći',
            'K4' => 'Odstupanje (listići u kutiji − glasali) mora biti 0',
            'K5' => 'Zbir glasova po listama = važeći listići',
            'K6' => 'Glasali ≤ upisani birači',
            'K7' => 'Sve vrednosti ≥ 0',
        ] as $code => $label) {
            $rows[] = ['table' => 'CONTROL_SUM', 'code' => $code, 'label' => $label];
        }

        return $rows;
    }
}
