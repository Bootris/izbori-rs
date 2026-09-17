<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ElectionStatus: string implements HasLabel, HasColor
{
    case Draft = 'draft';
    case Registry = 'registry';
    case Voting = 'voting';
    case Counting = 'counting';
    case Final = 'final';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Priprema',
            self::Registry => 'Registar objavljen',
            self::Voting => 'Glasanje u toku',
            self::Counting => 'Brojanje',
            self::Final => 'Konačni rezultati',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Registry => 'info',
            self::Voting => 'warning',
            self::Counting => 'primary',
            self::Final => 'success',
        };
    }

    /** Results are republished automatically only while counting is in progress. */
    public function autoPublishes(): bool
    {
        return $this === self::Counting;
    }

    /** Results may exist on the public site only once counting started (and after polls closed, see Election). */
    public function publishesResults(): bool
    {
        return in_array($this, [self::Counting, self::Final], true);
    }

    /** Protocols, turnout and reports are entered between the registry going public and the final result. */
    public function acceptsEntries(): bool
    {
        return in_array($this, [self::Registry, self::Voting, self::Counting], true);
    }
}
