<?php

declare(strict_types=1);

namespace App\Filament\Resources\Elections\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeadlinesRelationManager extends RelationManager
{
    protected static string $relationship = 'deadlines';

    protected static ?string $title = 'Izborni rokovi';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('date')->label('Datum')->required()->native(false)->displayFormat('d.m.Y'),
            TextInput::make('title')->label('Naziv')->required()->maxLength(255),
            Textarea::make('description')->label('Opis')->rows(2)->columnSpanFull(),
            TextInput::make('legal_basis')->label('Pravni osnov')->maxLength(255),
            TextInput::make('sort_order')->label('Redosled')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('date')
            ->columns([
                TextColumn::make('date')->label('Datum')->date('d.m.Y')->sortable(),
                TextColumn::make('title')->label('Naziv')->searchable(),
                TextColumn::make('legal_basis')->label('Pravni osnov')->color('gray'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
