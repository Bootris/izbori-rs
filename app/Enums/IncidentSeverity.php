<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** How urgently an election-day report needs eyes on it. */
enum IncidentSeverity: string implements HasLabel, HasColor
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function getLabel(): string
    {
        return match ($this) {
            self::Low => 'Niska: napomena, glasanje teče',
            self::Medium => 'Srednja: zastoj, rešava se na licu mesta',
            self::High => 'Visoka: ugroženo glasanje, potrebna reakcija komisije',
            self::Critical => 'Kritična: glasanje prekinuto ili nasilje',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Low => 'Niska',
            self::Medium => 'Srednja',
            self::High => 'Visoka',
            self::Critical => 'Kritična',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Medium => 'info',
            self::High => 'warning',
            self::Critical => 'danger',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Critical => 4,
        };
    }
}
