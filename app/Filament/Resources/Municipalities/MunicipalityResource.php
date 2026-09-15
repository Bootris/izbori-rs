<?php

declare(strict_types=1);

namespace App\Filament\Resources\Municipalities;

use App\Filament\Resources\Municipalities\Pages\ManageMunicipalities;
use App\Models\Municipality;
use App\Support\Access;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MunicipalityResource extends Resource
{
    protected static ?string $model = Municipality::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|\UnitEnum|null $navigationGroup = 'Teritorija';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'opština';

    protected static ?string $pluralModelLabel = 'Opštine';

    public static function canViewAny(): bool
    {
        return Access::isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('district_id')->label('Okrug')->relationship('district', 'name')->required()->searchable()->preload(),
            TextInput::make('code')->label('Šifra')->required()->maxLength(8)->unique(ignoreRecord: true),
            TextInput::make('name')->label('Naziv')->required()->maxLength(255),
            TextInput::make('sort_order')->label('Redosled')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->label('Šifra')->sortable(),
                TextColumn::make('name')->label('Naziv')->searchable()->sortable(),
                TextColumn::make('district.name')->label('Okrug')->sortable(),
                TextColumn::make('polling_stations_count')->label('Biračkih mesta')->counts('pollingStations')->alignEnd(),
            ])
            ->filters([SelectFilter::make('district')->label('Okrug')->relationship('district', 'name')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageMunicipalities::route('/')];
    }
}
