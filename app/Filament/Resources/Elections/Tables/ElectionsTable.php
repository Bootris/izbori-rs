<?php

declare(strict_types=1);

namespace App\Filament\Resources\Elections\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ElectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('election_date', 'desc')
            ->columns([
                TextColumn::make('name')->label('Naziv')->searchable()->sortable()->description(fn ($record) => $record->slug),
                TextColumn::make('type')->label('Tip')->badge()->color('gray'),
                TextColumn::make('election_date')->label('Dan glasanja')->date('d.m.Y')->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('units_count')->label('Jedinica')->counts('units')->alignEnd(),
                TextColumn::make('polling_stations_count')->label('Biračkih mesta')->counts('pollingStations')->alignEnd(),
                TextColumn::make('protocols_count')->label('Zapisnika')->counts('protocols')->alignEnd(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
