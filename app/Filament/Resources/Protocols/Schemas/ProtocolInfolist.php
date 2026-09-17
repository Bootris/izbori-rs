<?php

declare(strict_types=1);

namespace App\Filament\Resources\Protocols\Schemas;

use App\Models\Protocol;
use App\Models\ProtocolRevision;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProtocolInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Biračko mesto')
                    ->columnSpan(2)
                    ->columns(3)
                    ->components([
                        TextEntry::make('pollingStation.number')->label('Broj'),
                        TextEntry::make('pollingStation.name')->label('Naziv'),
                        TextEntry::make('pollingStation.municipality.name')->label('Opština'),
                        TextEntry::make('election.name')->label('Izbori'),
                        TextEntry::make('round')->label('Krug'),
                        TextEntry::make('revision')->label('Revizija'),
                    ]),
                Section::make('Status')
                    ->columnSpan(1)
                    ->components([
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('deviation')->label('Odstupanje (K4)')->color(fn (int $state) => $state === 0 ? 'success' : 'danger'),
                        TextEntry::make('validation_errors')
                            ->label('Greške kontrolnih suma')
                            ->placeholder('Nema')
                            ->listWithLineBreaks()
                            ->state(function (Protocol $record): ?array {
                                $errors = $record->validation_errors;
                                if (! is_array($errors) || $errors === []) {
                                    return null;
                                }

                                return array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($errors), $errors);
                            }),
                        TextEntry::make('verifiedBy.name')->label('Verifikovao')->placeholder('—'),
                        TextEntry::make('verified_at')->label('Verifikovano')->dateTime('d.m.Y H:i')->placeholder('—'),
                        TextEntry::make('enteredBy.name')->label('Uneo')->placeholder('—'),
                    ]),
                Section::make('Brojevi')
                    ->columnSpanFull()
                    ->columns(4)
                    ->components([
                        TextEntry::make('registered_voters')->label('Upisanih birača')->numeric(),
                        TextEntry::make('ballots_received')->label('Primljeno listića')->numeric(),
                        TextEntry::make('ballots_unused')->label('Neupotrebljeno')->numeric(),
                        TextEntry::make('voters_voted')->label('Glasalo')->numeric(),
                        TextEntry::make('ballots_in_box')->label('U kutiji')->numeric(),
                        TextEntry::make('ballots_valid')->label('Važećih')->numeric(),
                        TextEntry::make('ballots_invalid')->label('Nevažećih')->numeric(),
                        TextEntry::make('turnout')->label('Izlaznost')->state(fn (Protocol $record) => $record->turnoutPct().' %'),
                    ]),
                Section::make('Glasovi po listama')
                    ->columnSpanFull()
                    ->components([
                        TextEntry::make('votes_by_list')
                            ->hiddenLabel()
                            ->placeholder('Nema unetih glasova po listama.')
                            ->listWithLineBreaks()
                            ->state(function (Protocol $record): ?array {
                                $lines = $record->items
                                    ->sortBy(fn ($item) => $item->list?->number ?? PHP_INT_MAX)
                                    ->map(function ($item): string {
                                        $number = $item->list?->number;
                                        $name = $item->list?->name ?? 'Lista #'.$item->electoral_list_id;
                                        $label = $number !== null ? "{$number}. {$name}" : $name;

                                        return $label.' — '.number_format((int) $item->votes, 0, ',', '.');
                                    })
                                    ->values()
                                    ->all();
                                if ($lines !== []) {
                                    return $lines;
                                }
                                foreach ($record->revisions as $revision) {
                                    $fromHistory = $revision->itemSnapshotLines();
                                    if ($fromHistory !== []) {
                                        return $fromHistory;
                                    }
                                }

                                return null;
                            }),
                    ]),
                Section::make('Skenirani zapisnik')
                    ->columnSpan(1)
                    ->components([
                        RepeatableEntry::make('scans')
                            ->hiddenLabel()
                            ->placeholder('Nema skeniranih stranica.')
                            ->schema([
                                TextEntry::make('original_name')->label('Fajl')->url(fn ($record) => $record->url(), shouldOpenInNewTab: true)->color('primary'),
                            ]),
                    ]),
                Section::make('Istorija izmena')
                    ->columnSpan(2)
                    ->components([
                        RepeatableEntry::make('revisions')
                            ->hiddenLabel()
                            ->placeholder('Još nema izmena.')
                            ->schema([
                                TextEntry::make('created_at')->label('Kada')->dateTime('d.m.Y H:i:s'),
                                TextEntry::make('user.name')->label('Ko')->placeholder('sistem'),
                                TextEntry::make('action')
                                    ->label('Radnja')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => ProtocolRevision::formatActionLabel($state))
                                    ->color(fn (ProtocolRevision $record): string => $record->actionColor()),
                                TextEntry::make('changes_summary')
                                    ->label('Izmene')
                                    ->columnSpanFull()
                                    ->placeholder('—')
                                    ->listWithLineBreaks()
                                    ->bulleted(),
                            ]),
                    ]),
            ]);
    }
}
