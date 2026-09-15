<?php

declare(strict_types=1);

namespace App\Filament\Resources\ElectoralLists;

use App\Filament\Resources\ElectoralLists\Pages\CreateElectoralList;
use App\Filament\Resources\ElectoralLists\Pages\EditElectoralList;
use App\Filament\Resources\ElectoralLists\Pages\ListElectoralLists;
use App\Filament\Resources\ElectoralLists\RelationManagers\CandidatesRelationManager;
use App\Models\ElectionUnit;
use App\Models\ElectoralList;
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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ElectoralListResource extends Resource
{
    protected static ?string $model = ElectoralList::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|\UnitEnum|null $navigationGroup = 'Izbori';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'izborna lista';

    protected static ?string $pluralModelLabel = 'Izborne liste';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return Access::isAdmin();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['election', 'unit', 'submitter']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->components([
                Select::make('election_id')
                    ->label('Izbori')
                    ->relationship('election', 'name')
                    ->required()
                    ->native(false)
                    ->live()
                    ->disabledOn('edit')
                    ->dehydrated(),
                Select::make('election_unit_id')
                    ->label('Izborna jedinica')
                    ->options(fn (Get $get) => ElectionUnit::query()->where('election_id', $get('election_id'))->orderBy('code')->pluck('name', 'id'))
                    ->required()
                    ->native(false)
                    ->searchable(),
                Select::make('submitter_id')
                    ->label('Podnosilac')
                    ->options(fn (Get $get) => Submitter::query()->where('election_id', $get('election_id'))->orderBy('name')->pluck('name', 'id'))
                    ->native(false)
                    ->searchable(),
                TextInput::make('number')->label('Redni broj na listiću')->numeric()->minValue(1)->required(),
                TextInput::make('name')->label('Naziv liste')->required()->maxLength(255)->columnSpan(2),
                TextInput::make('short_name')->label('Skraćeno')->maxLength(64),
                TextInput::make('holder_name')->label('Nosilac liste / kandidat')->maxLength(255)->columnSpan(2),
                ColorPicker::make('color')->label('Boja'),
                Toggle::make('is_minority')->label('Lista nacionalne manjine')->helperText('Učestvuje u raspodeli i ispod cenzusa, količnici ×1,35.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('number')
            ->columns([
                ColorColumn::make('color')->label(''),
                TextColumn::make('number')->label('Br.')->sortable(),
                TextColumn::make('name')->label('Naziv')->searchable()->sortable()->description(fn ($record) => $record->holder_name),
                TextColumn::make('unit.name')->label('Jedinica')->toggleable(),
                TextColumn::make('submitter.name')->label('Podnosilac')->toggleable(),
                IconColumn::make('is_minority')->label('Manjina')->boolean(),
                TextColumn::make('candidates_count')->label('Kandidata')->counts('candidates')->alignEnd(),
                TextColumn::make('election.name')->label('Izbori')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('election')->label('Izbori')->relationship('election', 'name'),
                SelectFilter::make('unit')->label('Jedinica')->relationship('unit', 'name'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getRelations(): array
    {
        return [CandidatesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListElectoralLists::route('/'),
            'create' => CreateElectoralList::route('/create'),
            'edit' => EditElectoralList::route('/{record}/edit'),
        ];
    }
}
