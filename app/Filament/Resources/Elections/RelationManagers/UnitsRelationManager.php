<?php

declare(strict_types=1);

namespace App\Filament\Resources\Elections\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UnitsRelationManager extends RelationManager
{
    protected static string $relationship = 'units';

    protected static ?string $title = 'Izborne jedinice';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('Šifra')->required()->maxLength(16)->helperText('Npr. RS za celu Republiku, ili šifra opštine za lokalne izbore.'),
            TextInput::make('name')->label('Naziv')->required()->maxLength(255),
            TextInput::make('seats')->label('Mandata (prepisuje broj sa izbora)')->numeric()->minValue(1),
            Select::make('municipalities')
                ->label('Opštine u jedinici')
                ->relationship('municipalities', 'name')
                ->multiple()
                ->searchable()
                ->preload()
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('code')->label('Šifra')->sortable(),
                TextColumn::make('name')->label('Naziv')->searchable(),
                TextColumn::make('seats')->label('Mandata')->alignEnd(),
                TextColumn::make('municipalities_count')->label('Opština')->counts('municipalities')->alignEnd(),
                TextColumn::make('lists_count')->label('Lista')->counts('lists')->alignEnd(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
