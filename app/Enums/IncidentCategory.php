<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** What went wrong at the polling station (kept short so it can be picked in seconds). */
enum IncidentCategory: string implements HasLabel
{
    case VotingInterrupted = 'voting_interrupted';
    case Materials = 'materials';
    case BoardDispute = 'board_dispute';
    case VoterRoll = 'voter_roll';
    case Intimidation = 'intimidation';
    case Observers = 'observers';
    case Facility = 'facility';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::VotingInterrupted => 'Prekid glasanja',
            self::Materials => 'Izborni materijal (listići, kutija, spisak)',
            self::BoardDispute => 'Spor u biračkom odboru',
            self::VoterRoll => 'Birački spisak (birač nije upisan, dupli upis)',
            self::Intimidation => 'Pritisak na birače ili nasilje',
            self::Observers => 'Posmatrači (ometanje, udaljavanje)',
            self::Facility => 'Prostorija (struja, pristup, gužva)',
            self::Other => 'Ostalo',
        };
    }
}
