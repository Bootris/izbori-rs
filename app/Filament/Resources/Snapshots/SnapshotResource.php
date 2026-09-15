<?php

declare(strict_types=1);

namespace App\Filament\Resources\Snapshots;

use App\Filament\Resources\Snapshots\Pages\ListSnapshots;
use App\Models\Snapshot;
use App\Support\Access;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SnapshotResource extends Resource
{
    protected static ?string $model = Snapshot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloudArrowUp;

    protected static string|\UnitEnum|null $navigationGroup = 'Objava';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'objava';

    protected static ?string $pluralModelLabel = 'Objavljeni snapshot-ovi';

    public static function canViewAny(): bool
    {
        return Access::isAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['election', 'publishedBy']);
    }

    public static function table(Table $table): Table
    {
        $publicUrl = rtrim((string) config('izbori.snapshots.public_url'), '/');

        return $table
            ->defaultSort('id', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('election.name')->label('Izbori')->sortable(),
                TextColumn::make('source')->label('Izvor')->badge()->color('gray'),
                TextColumn::make('version')->label('Verzija')->weight('bold')->copyable(),
                TextColumn::make('generated_at')->label('Generisano')->dateTime('d.m.Y H:i:s')->sortable(),
                TextColumn::make('file_count')->label('Fajlova')->alignEnd(),
                TextColumn::make('bytes')->label('Veličina')->formatStateUsing(fn (int $state) => round($state / 1024).' KB')->alignEnd(),
                TextColumn::make('duration_ms')->label('ms')->alignEnd(),
                TextColumn::make('hash')->label('Hash')->formatStateUsing(fn (string $state) => substr($state, 0, 12).'…')->copyable()->tooltip('SHA-256 lanac: hash prethodne objave + hash svih fajlova'),
                TextColumn::make('publishedBy.name')->label('Objavio')->placeholder('scheduler'),
            ])
            ->filters([
                SelectFilter::make('election')->label('Izbori')->relationship('election', 'name'),
                SelectFilter::make('source')->label('Izvor')->options(\App\Enums\SnapshotSource::class),
            ])
            ->recordActions([
                Action::make('manifest')
                    ->label('Manifest')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->url(fn (Snapshot $record) => "{$publicUrl}/{$record->path}/manifest.json", shouldOpenInNewTab: true),
                Action::make('config')
                    ->label('config.json')
                    ->icon(Heroicon::OutlinedRocketLaunch)
                    ->url(fn (Snapshot $record) => "{$publicUrl}/{$record->election->slug}/config.json", shouldOpenInNewTab: true),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListSnapshots::route('/')];
    }
}
