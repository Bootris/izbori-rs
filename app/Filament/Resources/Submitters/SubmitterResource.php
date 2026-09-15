<?php

declare(strict_types=1);

namespace App\Filament\Resources\Submitters;

use App\Enums\SubmitterType;
use App\Filament\Resources\Submitters\Pages\ManageSubmitters;
use App\Models\Submitter;
use App\Support\Access;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubmitterResource extends Resource
{
    protected static ?string $model = Submitter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Izbori';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'podnosilac';

    protected static ?string $pluralModelLabel = 'Podnosioci lista';

    public static function canViewAny(): bool
    {
        return Access::isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('election_id')->label('Izbori')->relationship('election', 'name')->required()->native(false),
            Select::make('type')->label('Tip')->options(SubmitterType::class)->required()->native(false),
            TextInput::make('name')->label('Naziv')->required()->maxLength(255),
            TextInput::make('short_name')->label('Skraćeno')->maxLength(64),
            ColorPicker::make('color')->label('Boja'),
            Toggle::make('is_minority')->label('Politička stranka nacionalne manjine'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ColorColumn::make('color')->label(''),
                TextColumn::make('name')->label('Naziv')->searchable()->sortable()->description(fn ($record) => $record->short_name),
                TextColumn::make('type')->label('Tip')->badge()->color('gray'),
                IconColumn::make('is_minority')->label('Manjina')->boolean(),
                TextColumn::make('election.name')->label('Izbori')->toggleable(),
                TextColumn::make('lists_count')->label('Lista')->counts('lists')->alignEnd(),
            ])
            ->filters([SelectFilter::make('election')->label('Izbori')->relationship('election', 'name')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSubmitters::route('/')];
    }
}
