<?php

declare(strict_types=1);

namespace App\Filament\Resources\ElectoralLists\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CandidatesRelationManager extends RelationManager
{
    protected static string $relationship = 'candidates';

    protected static ?string $title = 'Kandidati';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('position')->label('Redni broj')->numeric()->minValue(1)->required(),
            TextInput::make('full_name')->label('Ime i prezime')->required()->maxLength(255)->columnSpan(2),
            TextInput::make('birth_year')->label('Godina rođenja')->numeric()->minValue(1900)->maxValue(2010),
            TextInput::make('occupation')->label('Zanimanje')->maxLength(255),
            TextInput::make('residence')->label('Prebivalište')->maxLength(255),
            Select::make('gender')->label('Pol')->options(['M' => 'Muški', 'F' => 'Ženski'])->native(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->defaultSort('position')
            ->columns([
                TextColumn::make('position')->label('#')->sortable(),
                TextColumn::make('full_name')->label('Ime i prezime')->searchable(),
                TextColumn::make('birth_year')->label('God.'),
                TextColumn::make('occupation')->label('Zanimanje'),
                TextColumn::make('residence')->label('Prebivalište'),
                TextColumn::make('gender')->label('Pol'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
