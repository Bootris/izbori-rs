<?php

declare(strict_types=1);

namespace App\Filament\Resources\TurnoutSnapshots;

use App\Filament\Resources\TurnoutSnapshots\Pages\ManageTurnoutSnapshots;
use App\Models\Election;
use App\Models\Municipality;
use App\Models\PollingStation;
use App\Models\TurnoutSnapshot;
use App\Support\Access;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

/**
 * Presek izlaznosti. One figure per station and cut-off (a repeated entry
 * updates it); the municipality total is computed from the stations by the
 * SnapshotBuilder, a whole-municipality row is only a fallback the OIK enters
 * when no station reports. A controller always enters for its own station.
 */
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
        $controller = Access::user()?->isController() ?? false;

        return $schema->columns(2)->components([
            Select::make('election_id')
                ->label('Izbori')
                ->options(fn () => Access::electionsOpenForEntry()->pluck('name', 'id'))
                ->default(fn () => Access::electionsOpenForEntry()->value('id'))
                ->required()->native(false)->live()
                ->afterStateUpdated(fn (Set $set, $state) => $set('polling_station_id', Access::singleWritableStation((int) $state)?->id))
                ->disabledOn('edit')->dehydrated(),
            Select::make('municipality_id')
                ->label('Opština')
                ->options(fn () => Access::scopeMunicipality(Municipality::query(), 'id')->orderBy('name')->pluck('name', 'id'))
                ->default(fn () => Access::isAdmin() ? null : Access::user()?->municipality_id)
                ->required()->searchable()->live()
                ->disabledOn('edit')->dehydrated(),
            Select::make('polling_station_id')
                ->label($controller ? 'Biračko mesto' : 'Biračko mesto (opciono)')
                ->options(fn (Get $get) => Access::scopeWritableStations(PollingStation::query())
                    ->where('election_id', $get('election_id'))->where('municipality_id', $get('municipality_id'))
                    ->orderBy('number')->get()->mapWithKeys(fn ($s) => [$s->id => "{$s->number} — {$s->name}"]))
                ->default(fn () => Access::singleWritableStation((int) Access::electionsOpenForEntry()->value('id'))?->id)
                ->required($controller)
                ->searchable(! $controller)
                ->native(false)
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $station = PollingStation::query()->find((int) $value);
                    if ($station === null || ! Access::canWriteStation($station)) {
                        $fail('Nemate pravo unosa za ovo biračko mesto.');
                    }
                })
                ->helperText($controller
                    ? 'Samo biračka mesta koja su vam dodeljena. Zbir opštine se računa automatski.'
                    : 'Prazno = zbir za celu opštinu (koristi se samo dok nijedno biračko mesto nije javilo presek).')
                ->disabledOn('edit')->dehydrated(),
            Select::make('cutoff')->label('Vremenski presek')->options($cutoffs)->required()->native(false)
                ->disabledOn('edit')->dehydrated(),
            TextInput::make('voters_voted')->label('Glasalo do preseka')->numeric()->integer()->minValue(0)->placeholder('0')->required(),
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
            // Edit and delete follow TurnoutSnapshotPolicy (own station, open election).
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    /**
     * Upsert on (election, municipality, station, cut-off): the second entry for
     * the same cut-off corrects the first instead of doubling the sum.
     *
     * @param array<string, mixed> $data
     */
    public static function record(array $data): TurnoutSnapshot
    {
        $key = [
            'election_id' => (int) $data['election_id'],
            'municipality_id' => (int) $data['municipality_id'],
            'polling_station_id' => filled($data['polling_station_id'] ?? null) ? (int) $data['polling_station_id'] : null,
            'cutoff' => (string) $data['cutoff'],
        ];

        // The form only offers what the user may write; this is the check a hand-crafted request cannot skip.
        $user = Access::user();
        $station = $key['polling_station_id'] ? PollingStation::query()->findOrFail($key['polling_station_id']) : null;
        $allowed = $user !== null && ($station !== null
            ? $user->canWriteStation($station) && $station->municipality_id === $key['municipality_id']
            : $user->isAdmin() || ($user->role->writesWholeMunicipality() && $user->canReadMunicipality($key['municipality_id'])));
        if (! $allowed) {
            throw new AuthorizationException('Nemate pravo unosa izlaznosti za ovo biračko mesto.');
        }
        if (! Election::query()->findOrFail($key['election_id'])->acceptsEntries()) {
            throw new AuthorizationException('Izbori su zaključani — izlaznost se više ne unosi.');
        }

        return TurnoutSnapshot::query()->updateOrCreate($key, [
            'voters_voted' => (int) $data['voters_voted'],
            'entered_by' => Access::user()?->id,
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTurnoutSnapshots::route('/')];
    }
}
