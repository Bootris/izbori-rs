<?php

declare(strict_types=1);

namespace App\Filament\Resources\Incidents;

use App\Enums\ElectionStatus;
use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Filament\Resources\Incidents\Pages\CreateIncident;
use App\Filament\Resources\Incidents\Pages\ListIncidents;
use App\Filament\Resources\Incidents\Pages\ViewIncident;
use App\Models\Election;
use App\Models\Incident;
use App\Models\PollingStation;
use App\Services\Incidents\IncidentService;
use App\Support\Access;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Prijave problema sa biračkih mesta. Anyone with an account files one for a
 * station of their municipality; verifiers and admins triage it; only an
 * admin decides whether it appears on the public site.
 */
class IncidentResource extends Resource
{
    protected static ?string $model = Incident::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|\UnitEnum|null $navigationGroup = 'Zapisnici';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'prijava';

    protected static ?string $pluralModelLabel = 'Prijave sa biračkih mesta';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->open()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return Access::scopeMunicipality(parent::getEloquentQuery())
            ->with(['pollingStation', 'municipality', 'election', 'reportedBy', 'reviewedBy', 'resolvedBy']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Biračko mesto')
                    ->description('Prijavu podnosi osoba koja je na biračkom mestu. Sistem sam beleži tačno vreme prijave.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        Select::make('election_id')
                            ->label('Izbori')
                            ->options(fn () => Election::query()->where('status', '!=', ElectionStatus::Draft)->orderByDesc('election_date')->pluck('name', 'id'))
                            ->default(fn () => self::activeElection()?->id)
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('polling_station_id', null)),
                        Select::make('polling_station_id')
                            ->label('Biračko mesto')
                            ->required()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search, Get $get) => self::stationQuery($get)
                                ->where(fn ($q) => $q->where('number', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")
                                    ->orWhereHas('municipality', fn ($m) => $m->where('name', 'like', "%{$search}%")))
                                ->limit(50)->get()->mapWithKeys(fn (PollingStation $s) => [$s->id => self::stationLabel($s)]))
                            ->getOptionLabelUsing(fn ($value) => ($s = PollingStation::with('municipality')->find($value)) ? self::stationLabel($s) : null)
                            ->helperText('Pretraga po broju, nazivu ili opštini.'),
                    ]),
                Section::make('Šta se dogodilo')
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        Select::make('category')
                            ->label('Vrsta problema')
                            ->options(IncidentCategory::class)
                            ->required()
                            ->native(false),
                        DateTimePicker::make('occurred_at')
                            ->label('Vreme događaja')
                            ->seconds(false)
                            ->default(now())
                            ->maxDate(fn () => now()->addMinutes(5))
                            ->required()
                            ->helperText('Kada se dogodilo. Vreme same prijave beleži server.'),
                        ToggleButtons::make('severity')
                            ->label('Ozbiljnost')
                            ->options(IncidentSeverity::class)
                            ->default(IncidentSeverity::Medium->value)
                            ->inline()
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Opis situacije')
                            ->rows(5)
                            ->minLength(10)
                            ->maxLength(4000)
                            ->required()
                            ->columnSpanFull()
                            ->helperText('Ko, šta, gde. Bez ličnih podataka birača. Opis se posle slanja ne menja.'),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Prijava')
                    ->columnSpan(2)
                    ->columns(2)
                    ->components([
                        TextEntry::make('pollingStation.number')->label('Biračko mesto')->formatStateUsing(fn ($state, Incident $record) => "BM {$state}: {$record->pollingStation->name}"),
                        TextEntry::make('municipality.name')->label('Opština'),
                        TextEntry::make('category')->label('Vrsta problema'),
                        TextEntry::make('severity')->label('Ozbiljnost')->badge(),
                        TextEntry::make('description')->label('Opis situacije')->columnSpanFull()->prose(),
                        TextEntry::make('election.name')->label('Izbori')->columnSpanFull(),
                    ]),
                Section::make('Satnica')
                    ->columnSpan(1)
                    ->components([
                        TextEntry::make('reported_at')->label('Prijavljeno (server)')->dateTime('d.m.Y H:i:s'),
                        TextEntry::make('occurred_at')->label('Vreme događaja')->dateTime('d.m.Y H:i'),
                        TextEntry::make('reportedBy.name')->label('Prijavio')->placeholder('nepoznat'),
                        TextEntry::make('status')->label('Status')->badge(),
                        IconEntry::make('is_public')->label('Na javnom sajtu')->boolean(),
                    ]),
                Section::make('Obrada')
                    ->columnSpanFull()
                    ->columns(3)
                    ->components([
                        TextEntry::make('reviewedBy.name')->label('Preuzeo')->placeholder('-'),
                        TextEntry::make('reviewed_at')->label('Preuzeto')->dateTime('d.m.Y H:i:s')->placeholder('-'),
                        TextEntry::make('resolvedBy.name')->label('Zatvorio')->placeholder('-'),
                        TextEntry::make('resolved_at')->label('Zatvoreno')->dateTime('d.m.Y H:i:s')->placeholder('-'),
                        TextEntry::make('resolution')->label('Šta je preduzeto')->columnSpanFull()->placeholder('-')->prose(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('reported_at', 'desc')
            ->poll(config('izbori.alarm_poll_seconds').'s')
            ->columns([
                TextColumn::make('reported_at')->label('Prijavljeno')->dateTime('d.m.Y H:i:s')->sortable(),
                TextColumn::make('pollingStation.number')->label('BM')->sortable()->searchable(),
                TextColumn::make('pollingStation.name')->label('Naziv')->searchable()->wrap()->toggleable(),
                TextColumn::make('municipality.name')->label('Opština')->sortable(),
                TextColumn::make('category')->label('Vrsta'),
                TextColumn::make('severity')->label('Ozbiljnost')->badge()->formatStateUsing(fn (IncidentSeverity $state) => $state->shortLabel())->sortable(),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
                IconColumn::make('is_public')->label('Javno')->boolean()->toggleable(),
                TextColumn::make('reportedBy.name')->label('Prijavio')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('election.name')->label('Izbori')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(IncidentStatus::class)->multiple(),
                SelectFilter::make('severity')->label('Ozbiljnost')->options(IncidentSeverity::class)->multiple(),
                SelectFilter::make('election')->label('Izbori')->relationship('election', 'name'),
                SelectFilter::make('municipality')->label('Opština')->relationship('municipality', 'name')->searchable()->preload(),
                TernaryFilter::make('is_public')->label('Na javnom sajtu'),
            ])
            ->recordActions([
                self::reviewAction(),
                self::closeAction(),
                self::publishAction(),
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIncidents::route('/'),
            'create' => CreateIncident::route('/create'),
            'view' => ViewIncident::route('/{record}'),
        ];
    }

    // ---- shared actions (table rows + view header)

    public static function reviewAction(): Action
    {
        return Action::make('review')
            ->label('Preuzmi')
            ->icon(Heroicon::OutlinedHandRaised)
            ->color('warning')
            ->visible(fn (Incident $record): bool => (Access::user()?->canVerify() ?? false) && $record->status === IncidentStatus::Open)
            ->action(function (Incident $record, IncidentService $service) {
                $service->review($record, Access::user());
                Notification::make()->success()->title('Prijava je u obradi')->send();
            });
    }

    public static function closeAction(): Action
    {
        return Action::make('close')
            ->label('Zatvori')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Incident $record): bool => (Access::user()?->canVerify() ?? false) && $record->isOpen())
            ->schema([
                ToggleButtons::make('outcome')
                    ->label('Ishod')
                    ->options([
                        IncidentStatus::Resolved->value => IncidentStatus::Resolved->getLabel(),
                        IncidentStatus::Dismissed->value => IncidentStatus::Dismissed->getLabel(),
                    ])
                    ->default(IncidentStatus::Resolved->value)
                    ->inline()
                    ->required(),
                Textarea::make('resolution')->label('Šta je preduzeto')->rows(4)->required(),
            ])
            ->action(function (Incident $record, array $data, IncidentService $service) {
                $service->close($record, Access::user(), (string) $data['resolution'], IncidentStatus::from($data['outcome']));
                Notification::make()->success()->title('Prijava zatvorena')->send();
            });
    }

    public static function publishAction(): Action
    {
        return Action::make('publish')
            ->label(fn (Incident $record): string => $record->is_public ? 'Skloni sa sajta' : 'Objavi javno')
            ->icon(fn (Incident $record) => $record->is_public ? Heroicon::OutlinedEyeSlash : Heroicon::OutlinedGlobeAlt)
            ->color('gray')
            ->visible(fn (): bool => Access::isAdmin())
            ->requiresConfirmation()
            ->modalDescription('Javna prijava se pojavljuje u incidents.json i na stranici „Vanredni događaji" pri sledećoj objavi. Opis se objavljuje u celini, proverite da nema ličnih podataka.')
            ->action(function (Incident $record, IncidentService $service) {
                $service->setPublic($record, ! $record->is_public);
                Notification::make()->success()->title($record->is_public ? 'Prijava će biti objavljena' : 'Prijava sklonjena sa javnog sajta')->send();
            });
    }

    // ---- helpers

    /** The election whose polling day it is: voting first, then counting, then the newest that is not a draft. */
    public static function activeElection(): ?Election
    {
        return Election::query()
            ->where('status', '!=', ElectionStatus::Draft)
            ->orderByRaw('case when status = ? then 0 when status = ? then 1 else 2 end', [ElectionStatus::Voting->value, ElectionStatus::Counting->value])
            ->orderByDesc('election_date')
            ->first();
    }

    private static function stationQuery(Get $get): Builder
    {
        return Access::scopeMunicipality(
            PollingStation::query()->where('election_id', $get('election_id'))->with('municipality')->orderBy('number')
        );
    }

    private static function stationLabel(PollingStation $s): string
    {
        return "{$s->municipality->name}, BM {$s->number}: {$s->name}";
    }
}
