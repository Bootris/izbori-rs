<?php

declare(strict_types=1);

namespace App\Filament\Resources\TurnoutSnapshots;

use App\Filament\Resources\TurnoutSnapshots\Pages\ManageTurnoutSnapshots;
use App\Models\Municipality;
use App\Models\PollingStation;
use App\Models\TurnoutSnapshot;
use App\Support\Access;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TurnoutSnapshotResource extends Resource
{
    protected static ?string $model = TurnoutSnapshot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'Zapisnici';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'presek izlaznosti';

    protected static ?string $pluralModelLabel = 'Izlaznost';

    public static function getEloquentQuery(): Builder
    {
        return Access::scopeMunicipality(parent::getEloquentQuery()->with(['municipality', 'pollingStation', 'election']));
    }

    public static function form(Schema $schema): Schema
    {
        $cutoffs = collect(config('izbori.turnout_cutoffs'))->mapWithKeys(fn ($c) => [$c => $c])->all();

        return $schema->columns(2)->components([
            Select::make('election_id')->label('Izbori')->relationship('election', 'name')->required()->native(false)->live(),
            Select::make('municipality_id')
                ->label('Opština')
                ->options(fn () => Access::scopeMunicipality(Municipality::query(), 'id')->orderBy('name')->pluck('name', 'id'))
                ->required()->searchable()->live(),
            Select::make('polling_station_id')
                ->label('Biračko mesto (opciono)')
                ->options(fn (Get $get) => PollingStation::query()
                    ->where('election_id', $get('election_id'))->where('municipality_id', $get('municipality_id'))
                    ->orderBy('number')->get()->mapWithKeys(fn ($s) => [$s->id => "{$s->number} — {$s->name}"]))
                ->searchable()
                ->helperText('Prazno = zbir za celu opštinu.'),
            Select::make('cutoff')->label('Vremenski presek')->options($cutoffs)->required()->native(false),
            TextInput::make('voters_voted')->label('Glasalo do preseka')->numeric()->integer()->minValue(0)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('municipality.name')->label('Opština')->sortable()->searchable(),
                TextColumn::make('pollingStation.number')->label('BM')->placeholder('cela opština'),
                TextColumn::make('cutoff')->label('Presek')->sortable(),
                TextColumn::make('voters_voted')->label('Glasalo')->numeric()->alignEnd(),
                TextColumn::make('election.name')->label('Izbori')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label('Uneto')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('election')->label('Izbori')->relationship('election', 'name'),
                SelectFilter::make('cutoff')->label('Presek')->options(collect(config('izbori.turnout_cutoffs'))->mapWithKeys(fn ($c) => [$c => $c])->all()),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTurnoutSnapshots::route('/')];
    }
}
