<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Admin = 'admin';        // RIK: everything, including registry and publishing
    case Verifier = 'verifier';  // OIK/GIK: verifies protocols for its municipality
    case Operator = 'operator';  // data entry for its municipality

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Administrator (RIK) — sve',
            self::Verifier => 'Verifikator (OIK/GIK) — unos i verifikacija za svoju opštinu',
            self::Operator => 'Operater — samo unos zapisnika za svoju opštinu',
        };
    }
}
