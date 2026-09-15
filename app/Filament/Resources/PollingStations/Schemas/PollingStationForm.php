<?php

declare(strict_types=1);

namespace App\Filament\Resources\PollingStations\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PollingStationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Biračko mesto')
                    ->columnSpan(2)
                    ->columns(2)
                    ->components([
                        Select::make('election_id')->label('Izbor')->relationship('election', 'name')->required()->native(false),
                        Select::make('municipality_id')->label('Opština')->relationship('municipality', 'name')->required()->searchable()->preload(),
                        TextInput::make('number')->label('Redni broj')->required()->maxLength(16),
                        TextInput::make('registered_voters')->label('Upisanih birača')->numeric()->minValue(0)->default(0)->required(),
                        TextInput::make('name')->label('Naziv (objekat)')->required()->maxLength(255)->columnSpanFull(),
                        TextInput::make('address')->label('Adresa')->maxLength(255)->columnSpanFull(),
                    ]),
                Section::make('Osobine')
                    ->columnSpan(1)
                    ->components([
                        Toggle::make('accessible')->label('Pristupačno osobama sa invaliditetom'),
                        Toggle::make('is_diaspora')->label('U inostranstvu (DKP)')->live(),
                        TextInput::make('country')->label('Država')->maxLength(64)->visible(fn ($get) => (bool) $get('is_diaspora')),
                        TextInput::make('lat')->label('Geo širina')->numeric()->step(0.000001),
                        TextInput::make('lng')->label('Geo dužina')->numeric()->step(0.000001),
                    ]),
            ]);
    }
}
