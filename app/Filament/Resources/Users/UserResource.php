<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users;

use App\Enums\ElectionStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\PollingStation;
use App\Models\User;
use App\Support\Access;
use BackedEnum;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'korisnik';

    protected static ?string $pluralModelLabel = 'Korisnici';

    public static function canViewAny(): bool
    {
        return Access::isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Ime')->required()->maxLength(255),
            TextInput::make('email')->label('Email')->email()->required()->unique(ignoreRecord: true),
            Select::make('role')->label('Uloga')->options(UserRole::class)->default(UserRole::Operator)->required()->native(false)->live(),
            Select::make('municipality_id')
                ->label('Opština (OIK/GIK)')
                ->relationship('municipality', 'name')
                ->searchable()->preload()->live()
                ->visible(fn (Get $get) => self::role($get) !== UserRole::Admin)
                ->required(fn (Get $get) => self::role($get) !== UserRole::Admin)
                ->afterStateUpdated(fn (Set $set) => $set('pollingStations', []))
                ->helperText(fn (Get $get) => self::role($get) === UserRole::Controller
                    ? 'Kontrolor vidi zapisnike, izlaznost i prijave ove opštine, ali upisuje samo za dodeljena biračka mesta.'
                    : 'Korisnik vidi i unosi samo zapisnike ove opštine.'),
            Select::make('pollingStations')
                ->label('Dodeljena biračka mesta')
                ->relationship(
                    'pollingStations',
                    'name',
                    modifyQueryUsing: fn (Builder $query, Get $get) => $query
                        ->where('municipality_id', (int) $get('municipality_id'))
                        ->whereHas('election', fn (Builder $q) => $q->whereIn('status', [ElectionStatus::Registry, ElectionStatus::Voting, ElectionStatus::Counting]))
                        ->with('election')
                        ->orderBy('election_id')->orderBy('number'),
                )
                ->getOptionLabelFromRecordUsing(fn (PollingStation $s) => "BM {$s->number}: {$s->name} ({$s->election->name})")
                ->multiple()->preload()->searchable()
                ->visible(fn (Get $get) => self::role($get) === UserRole::Controller)
                ->required(fn (Get $get) => self::role($get) === UserRole::Controller)
                ->columnSpanFull()
                ->helperText('Samo ova biračka mesta kontrolor može da upisuje: izlaznost, zapisnik, skenove i prijave. Nude se BM izabrane opštine za izbore u toku.'),
            TextInput::make('password')
                ->label('Lozinka')
                ->password()->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->minLength(8)
                ->helperText('Ostavi prazno da zadržiš postojeću.'),
        ]);
    }

    /** The role field holds the enum on edit and its value on create / after a change. */
    private static function role(Get $get): ?UserRole
    {
        $role = $get('role');

        return $role instanceof UserRole ? $role : UserRole::tryFrom((string) $role);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Ime')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('role')->label('Uloga')->badge()->formatStateUsing(fn (UserRole $state) => $state->value)->color(fn (UserRole $state) => $state === UserRole::Admin ? 'primary' : 'gray'),
                TextColumn::make('municipality.name')->label('Opština')->placeholder('—'),
                TextColumn::make('polling_stations_count')->label('BM')->counts('pollingStations')->alignEnd()
                    ->formatStateUsing(fn (int $state, User $record) => $record->isController() ? (string) $state : '—')
                    ->tooltip('Dodeljena biračka mesta (kontrolor)'),
                TextColumn::make('created_at')->label('Kreiran')->dateTime('d.m.Y')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->hidden(fn (User $record): bool => $record->id === auth()->id()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
