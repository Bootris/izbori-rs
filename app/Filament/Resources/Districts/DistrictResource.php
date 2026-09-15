<?php

declare(strict_types=1);

namespace App\Filament\Resources\Districts;

use App\Filament\Resources\Districts\Pages\ManageDistricts;
use App\Models\District;
use App\Support\Access;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DistrictResource extends Resource
{
    protected static ?string $model = District::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|\UnitEnum|null $navigationGroup = 'Teritorija';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'okrug';

    protected static ?string $pluralModelLabel = 'Okruzi';

    public static function canViewAny(): bool
    {
        return Access::isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('Šifra')->required()->maxLength(8)->unique(ignoreRecord: true),
            TextInput::make('name')->label('Naziv')->required()->maxLength(255),
            TextInput::make('sort_order')->label('Redosled')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('code')->label('Šifra')->sortable(),
                TextColumn::make('name')->label('Naziv')->searchable()->sortable(),
                TextColumn::make('municipalities_count')->label('Opština')->counts('municipalities')->alignEnd(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDistricts::route('/')];
    }
}
