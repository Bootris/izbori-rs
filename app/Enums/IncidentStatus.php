<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum IncidentStatus: string implements HasLabel, HasColor
{
    case Open = 'open';
    case InReview = 'in_review';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Otvorena',
            self::InReview => 'U obradi',
            self::Resolved => 'Rešena',
            self::Dismissed => 'Odbačena',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::InReview => 'warning',
            self::Resolved => 'success',
            self::Dismissed => 'gray',
        };
    }

    public function isClosed(): bool
    {
        return $this === self::Resolved || $this === self::Dismissed;
    }
}
