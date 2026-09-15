<?php

declare(strict_types=1);

namespace App\Filament\Resources\PollingStations\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PollingStationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('number')
            ->columns([
                TextColumn::make('number')->label('Br.')->sortable()->searchable(),
                TextColumn::make('name')->label('Naziv')->searchable()->sortable()->description(fn ($record) => $record->address),
                TextColumn::make('municipality.name')->label('Opština')->sortable()->searchable(),
                TextColumn::make('municipality.district.name')->label('Okrug')->toggleable(),
                TextColumn::make('registered_voters')->label('Birača')->numeric()->alignEnd()->sortable(),
                IconColumn::make('accessible')->label('Pristup.')->boolean(),
                IconColumn::make('is_diaspora')->label('DKP')->boolean()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('election.name')->label('Izbor')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('election')->label('Izbor')->relationship('election', 'name'),
                SelectFilter::make('municipality')->label('Opština')->relationship('municipality', 'name')->searchable()->preload(),
                TernaryFilter::make('is_diaspora')->label('U inostranstvu'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
