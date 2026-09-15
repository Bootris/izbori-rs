<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProtocolStatus: string implements HasLabel, HasColor
{
    case Entered = 'entered';
    case Flagged = 'flagged';
    case Verified = 'verified';
    case Annulled = 'annulled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Entered => 'Unet',
            self::Flagged => 'Sa odstupanjem',
            self::Verified => 'Verifikovan',
            self::Annulled => 'Poništen',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Entered => 'gray',
            self::Flagged => 'danger',
            self::Verified => 'success',
            self::Annulled => 'warning',
        };
    }

    /** Only verified protocols enter the aggregates that get published. */
    public function countsTowardsResults(): bool
    {
        return $this === self::Verified;
    }
}
