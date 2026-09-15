<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SubmitterType: string implements HasLabel
{
    case Party = 'party';
    case Coalition = 'coalition';
    case CitizenGroup = 'citizen_group';

    public function getLabel(): string
    {
        return match ($this) {
            self::Party => 'Politička stranka',
            self::Coalition => 'Koalicija',
            self::CitizenGroup => 'Grupa građana',
        };
    }
}
