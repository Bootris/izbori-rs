<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\IncidentSeverity;
use App\Filament\Resources\Incidents\IncidentResource;
use App\Models\Incident;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** What is burning right now: open reports, newest first, refreshed at the alarm cadence. */
class OpenIncidents extends TableWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Otvorene prijave sa biračkih mesta';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => IncidentResource::getEloquentQuery()->open())
            ->defaultSort('reported_at', 'desc')
            ->poll(config('izbori.alarm_poll_seconds').'s')
            ->paginated([5, 10])
            ->emptyStateHeading('Nema otvorenih prijava')
            ->emptyStateDescription('Problem sa biračkog mesta prijavljuje se dugmetom „Prijavi problem" u meniju Prijave.')
            ->columns([
                TextColumn::make('reported_at')->label('Prijavljeno')->dateTime('d.m.Y H:i:s'),
                TextColumn::make('severity')->label('Ozbiljnost')->badge()->formatStateUsing(fn (IncidentSeverity $state) => $state->shortLabel()),
                TextColumn::make('pollingStation.number')->label('BM')->formatStateUsing(fn ($state, Incident $record) => "{$state} {$record->pollingStation->name}")->wrap(),
                TextColumn::make('municipality.name')->label('Opština'),
                TextColumn::make('category')->label('Vrsta'),
                TextColumn::make('description')->label('Opis')->limit(90)->wrap(),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->recordUrl(fn (Incident $record) => IncidentResource::getUrl('view', ['record' => $record]));
    }
}
