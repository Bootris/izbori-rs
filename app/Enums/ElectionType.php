<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ElectionType: string implements HasLabel
{
    case Parliamentary = 'parliamentary';
    case Provincial = 'provincial';
    case Local = 'local';
    case Presidential = 'presidential';

    public function getLabel(): string
    {
        return match ($this) {
            self::Parliamentary => 'Parlamentarni (Narodna skupština)',
            self::Provincial => 'Pokrajinski (Skupština AP Vojvodine)',
            self::Local => 'Lokalni (skupštine gradova i opština)',
            self::Presidential => 'Predsednički',
        };
    }

    /** @return array{seats:?int, allocation:string, threshold_pct:?float, minority_coef:?float, rounds:int} */
    public function defaults(): array
    {
        return config("izbori.defaults.{$this->value}");
    }
}
