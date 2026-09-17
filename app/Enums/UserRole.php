<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Admin = 'admin';        // RIK: everything, including registry and publishing
    case Verifier = 'verifier';  // OIK/GIK: verifies protocols for its municipality
    case Operator = 'operator';  // data entry for its municipality
    case Controller = 'controller'; // birački odbor: writes only its assigned polling station(s), reads its municipality

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Administrator (RIK) — sve',
            self::Verifier => 'Verifikator (OIK/GIK) — unos i verifikacija za svoju opštinu',
            self::Operator => 'Operater — samo unos zapisnika za svoju opštinu',
            self::Controller => 'Kontrolor biračkog mesta — unos samo za dodeljena BM, ostala u opštini samo čita',
        };
    }

    /** Roles whose write scope is a whole municipality (the others are per station or unlimited). */
    public function writesWholeMunicipality(): bool
    {
        return in_array($this, [self::Verifier, self::Operator], true);
    }
}
