<?php

declare(strict_types=1);

namespace App\Filament\Resources\PollingStations\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PollingStationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->components([
                    Select::make('election_id')
                        ->label('Izbori')
                        ->relationship('election', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->columnSpanFull(),
                    Select::make('municipality_id')
                        ->label('Opština')
                        ->relationship('municipality', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->native(false),
                    TextInput::make('number')
                        ->label('Broj')
                        ->required()
                        ->maxLength(16),
                    TextInput::make('name')
                        ->label('Naziv')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('address')
                        ->label('Adresa')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('registered_voters')
                        ->label('Upisanih birača')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->required()
                        ->default(0),
                    Toggle::make('accessible')
                        ->label('Pristupačno osobama sa invaliditetom')
                        ->inline(false),
                    Toggle::make('is_diaspora')
                        ->label('U inostranstvu (DKP)')
                        ->inline(false)
                        ->live(),
                    TextInput::make('country')
                        ->label('Država')
                        ->maxLength(64)
                        ->visible(fn (Get $get): bool => (bool) $get('is_diaspora')),
                    TextInput::make('lat')
                        ->label('Geo širina')
                        ->numeric()
                        ->rule('nullable|numeric')
                        ->inputMode('decimal')
                        ->placeholder('44.817813'),
                    TextInput::make('lng')
                        ->label('Geo dužina')
                        ->numeric()
                        ->rule('nullable|numeric')
                        ->inputMode('decimal')
                        ->placeholder('20.456897'),
                ]),
        ]);
    }
}
