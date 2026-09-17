<?php

declare(strict_types=1);

namespace App\Filament\Resources\Protocols\Tables;

use App\Enums\ProtocolStatus;
use App\Filament\Resources\Protocols\ProtocolResource;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProtocolsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('pollingStation.number')->label('BM')->sortable()->searchable(),
                TextColumn::make('pollingStation.name')->label('Naziv')->searchable()->wrap(),
                TextColumn::make('pollingStation.municipality.name')->label('Opština')->sortable(),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
                TextColumn::make('deviation')->label('Odstup.')->alignEnd()->color(fn (int $state) => $state === 0 ? 'gray' : 'danger')->weight(fn (int $state) => $state === 0 ? null : 'bold'),
                TextColumn::make('voters_voted')->label('Glasalo')->numeric()->alignEnd(),
                TextColumn::make('ballots_valid')->label('Važećih')->numeric()->alignEnd()->toggleable(),
                TextColumn::make('revision')->label('Rev.')->alignEnd()->toggleable(),
                IconColumn::make('recount_requested')->label('Pon. brojanje')->boolean()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('verified_at')->label('Verifikovano')->dateTime('d.m.Y H:i')->sortable()->toggleable(),
                TextColumn::make('enteredBy.name')->label('Uneo')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('election.name')->label('Izbori')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('election')->label('Izbori')->relationship('election', 'name'),
                SelectFilter::make('status')->label('Status')->options(ProtocolStatus::class),
                SelectFilter::make('municipality')->label('Opština')
                    ->relationship('pollingStation.municipality', 'name')->searchable()->preload(),
                TernaryFilter::make('flagged')->label('Sa odstupanjem')
                    ->queries(
                        true: fn ($q) => $q->where('status', ProtocolStatus::Flagged),
                        false: fn ($q) => $q->where('status', '!=', ProtocolStatus::Flagged),
                    ),
            ])
            // Edit is hidden by ProtocolPolicy::update (verified, closed election, foreign station).
            ->recordActions([
                ProtocolResource::verifyAction(),
                ProtocolResource::returnAction(),
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
