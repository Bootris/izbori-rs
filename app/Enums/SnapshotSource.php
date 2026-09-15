<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The three independently versioned data sources (mirrors the Hungarian
 * ver / napkozi / szavossz split). Each has its own pointer in config.json.
 */
enum SnapshotSource: string implements HasLabel
{
    case Registry = 'registry';
    case Turnout = 'turnout';
    case Results = 'results';

    public function getLabel(): string
    {
        return match ($this) {
            self::Registry => 'Registar (jedinice, biračka mesta, liste)',
            self::Turnout => 'Izlaznost',
            self::Results => 'Rezultati',
        };
    }
}
