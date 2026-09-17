<?php

declare(strict_types=1);

namespace App\Filament\Resources\Elections\Schemas;

use App\Enums\AllocationMethod;
use App\Enums\ElectionStatus;
use App\Enums\ElectionType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ElectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Osnovno')
                    ->columnSpan(2)
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('Naziv')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, Set $set) {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->required()
                            ->rules(['alpha_dash'])
                            ->unique(ignoreRecord: true)
                            ->disabledOn('edit')
                            ->dehydrated()
                            ->helperText('Deo URL-a i ime foldera sa snapshot-ovima — ne menja se posle objave.'),
                        Select::make('type')
                            ->label('Tip izbora')
                            ->options(ElectionType::class)
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set) {
                                if ($state === null) {
                                    return;
                                }
                                foreach (ElectionType::from($state)->defaults() as $key => $value) {
                                    $set($key, $value);
                                }
                            })
                            ->helperText('Popunjava podrazumevana pravila; sve ispod je i dalje izmenljivo.'),
                        DatePicker::make('election_date')
                            ->label('Dan glasanja')
                            ->required()
                            ->native(false)
                            ->displayFormat('d.m.Y'),
                        Select::make('status')
                            ->options(ElectionStatus::class)
                            ->default(ElectionStatus::Draft)
                            ->required()
                            ->native(false)
                            ->helperText('Rezultati se objavljuju samo u „Brojanje" ili „Konačni rezultati", i tek posle zatvaranja biračkih mesta na dan glasanja. „Brojanje" uključuje automatsku objavu na svakih par minuta; vraćanje unazad skida rezultate sa sajta. „Konačni rezultati" zaključavaju unos.'),
                        Textarea::make('description')
                            ->label('Opis')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Pravila raspodele')
                    ->columnSpan(1)
                    ->components([
                        Select::make('allocation')
                            ->label('Metod')
                            ->options(AllocationMethod::class)
                            ->required()
                            ->native(false),
                        TextInput::make('seats')
                            ->label('Broj mandata')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Za lokalne izbore ostavi prazno i upiši po jedinici.'),
                        TextInput::make('threshold_pct')
                            ->label('Cenzus (%)')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(100),
                        TextInput::make('minority_coef')
                            ->label('Manjinski koeficijent')
                            ->numeric()
                            ->step(0.001)
                            ->minValue(1)
                            ->helperText('Količnici manjinskih lista ispod cenzusa se množe ovim brojem (1,35).'),
                        TextInput::make('rounds')
                            ->label('Broj krugova')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(2)
                            ->required(),
                        TextInput::make('round')
                            ->label('Tekući krug')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(2)
                            ->required(),
                    ]),
            ]);
    }
}
