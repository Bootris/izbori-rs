<?php

declare(strict_types=1);

namespace App\Filament\Resources\PollingStations;

use App\Filament\Resources\PollingStations\Pages\ManagePollingStations;
use App\Models\PollingStation;
use App\Support\Access;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PollingStationResource extends Resource
{
    protected static ?string $model = PollingStation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|\UnitEnum|null $navigationGroup = 'Teritorija';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'biračko mesto';

    protected static ?string $pluralModelLabel = 'Biračka mesta';

    public static function canCreate(): bool
    {
        return Access::isAdmin();
    }

    public static function canEdit($record): bool
    {
        return Access::isAdmin();
    }

    public static function canDelete($record): bool
    {
        return Access::isAdmin();
    }

    public static function getEloquentQuery(): Builder
    {
        return Access::scopeMunicipality(parent::getEloquentQuery()->with(['municipality.district', 'election']));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->components([
                Select::make('election_id')->label('Izbori')->relationship('election', 'name')->required()->native(false),
                Select::make('municipality_id')->label('Opština')->relationship('municipality', 'name')->required()->searchable()->preload(),
                TextInput::make('number')->label('Broj')->required()->maxLength(16),
                TextInput::make('name')->label('Naziv')->required()->maxLength(255)->columnSpan(2),
                TextInput::make('registered_voters')->label('Upisanih birača')->numeric()->minValue(0)->required()->default(0),
                TextInput::make('address')->label('Adresa')->maxLength(255)->columnSpan(2),
                Toggle::make('accessible')->label('Pristupačno osobama sa invaliditetom'),
                Toggle::make('is_diaspora')->label('U inostranstvu (DKP)')->live(),
                TextInput::make('country')->label('Država')->maxLength(64)->visible(fn ($get) => (bool) $get('is_diaspora')),
                TextInput::make('lat')->label('Geo širina')->numeric()->step(0.000001),
                TextInput::make('lng')->label('Geo dužina')->numeric()->step(0.000001),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('number')
            ->columns([
                TextColumn::make('number')->label('Broj')->sortable()->searchable(),
                TextColumn::make('name')->label('Naziv')->searchable()->description(fn ($record) => $record->address),
                TextColumn::make('municipality.name')->label('Opština')->sortable()->searchable(),
                TextColumn::make('municipality.district.name')->label('Okrug')->toggleable(),
                TextColumn::make('registered_voters')->label('Birača')->numeric()->alignEnd()->sortable(),
                IconColumn::make('accessible')->label('Pristup.')->boolean(),
                IconColumn::make('is_diaspora')->label('DKP')->boolean()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('election.name')->label('Izbori')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('election')->label('Izbori')->relationship('election', 'name'),
                SelectFilter::make('municipality')->label('Opština')->relationship('municipality', 'name')->searchable()->preload(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePollingStations::route('/')];
    }
}
