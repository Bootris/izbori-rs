<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AllocationMethod: string implements HasLabel
{
    case DHondt = 'dhondt';
    case MajorityRunoff = 'majority_runoff';

    public function getLabel(): string
    {
        return match ($this) {
            self::DHondt => "D'Hondt (sistem najvećeg količnika)",
            self::MajorityRunoff => 'Većinski, dva kruga',
        };
    }
}
