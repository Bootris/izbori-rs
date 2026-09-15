<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
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
use Filament\Tables\Table;

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
                ->searchable()->preload()
                ->visible(fn (Get $get) => $get('role') !== UserRole::Admin->value)
                ->required(fn (Get $get) => $get('role') !== UserRole::Admin->value)
                ->helperText('Korisnik vidi i unosi samo zapisnike ove opštine.'),
            TextInput::make('password')
                ->label('Lozinka')
                ->password()->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->minLength(8)
                ->helperText('Ostavi prazno da zadržiš postojeću.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Ime')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('role')->label('Uloga')->badge()->formatStateUsing(fn (UserRole $state) => $state->value)->color(fn (UserRole $state) => $state === UserRole::Admin ? 'primary' : 'gray'),
                TextColumn::make('municipality.name')->label('Opština')->placeholder('—'),
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
