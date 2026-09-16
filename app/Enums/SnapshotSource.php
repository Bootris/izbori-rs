<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The independently versioned data sources (registry / turnout / results
 * mirror the Hungarian ver / napkozi / szavossz split; incidents are the
 * election-day reports). Each has its own pointer in config.json.
 */
enum SnapshotSource: string implements HasLabel
{
    case Registry = 'registry';
    case Turnout = 'turnout';
    case Results = 'results';
    case Incidents = 'incidents';

    public function getLabel(): string
    {
        return match ($this) {
            self::Registry => 'Registar (jedinice, biračka mesta, liste)',
            self::Turnout => 'Izlaznost',
            self::Results => 'Rezultati',
            self::Incidents => 'Prijave sa biračkih mesta (vanredni događaji)',
        };
    }
}
