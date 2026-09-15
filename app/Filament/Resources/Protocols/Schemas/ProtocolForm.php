<?php

declare(strict_types=1);

namespace App\Filament\Resources\Protocols\Schemas;

use App\Models\Election;
use App\Models\ElectionUnit;
use App\Models\PollingStation;
use App\Models\Protocol;
use App\Services\Validation\ProtocolValidator;
use App\Support\Access;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ProtocolForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Biračko mesto')
                    ->columnSpanFull()
                    ->columns(3)
                    ->components([
                        Select::make('election_id')
                            ->label('Izbori')
                            ->options(fn () => Election::query()->orderByDesc('election_date')->pluck('name', 'id'))
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('polling_station_id', null))
                            ->disabledOn('edit')
                            ->dehydrated(),
                        Select::make('polling_station_id')
                            ->label('Biračko mesto')
                            ->required()
                            ->searchable()
                            ->live()
                            ->disabledOn('edit')
                            ->dehydrated()
                            ->getSearchResultsUsing(fn (string $search, Get $get) => self::stationQuery($get)
                                ->where(fn ($q) => $q->where('number', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")
                                    ->orWhereHas('municipality', fn ($m) => $m->where('name', 'like', "%{$search}%")))
                                ->limit(50)->get()->mapWithKeys(fn (PollingStation $s) => [$s->id => self::stationLabel($s)]))
                            ->getOptionLabelUsing(fn ($value) => ($s = PollingStation::with('municipality')->find($value)) ? self::stationLabel($s) : null)
                            ->helperText('Pretraga po broju, nazivu ili opštini.'),
                        TextInput::make('round')->label('Krug')->numeric()->default(1)->minValue(1)->maxValue(2)->required()->disabledOn('edit')->dehydrated(),
                    ]),

                Section::make('Brojevi iz zapisnika')
                    ->columnSpan(2)
                    ->columns(2)
                    ->components([
                        self::count('registered_voters', 'Upisanih birača (izvod iz biračkog spiska)'),
                        self::count('ballots_received', 'Primljeno glasačkih listića'),
                        self::count('ballots_unused', 'Neupotrebljeni glasački listići'),
                        self::count('voters_voted', 'Birača koji su glasali (po izvodu)'),
                        self::count('ballots_in_box', 'Glasačkih listića u glasačkoj kutiji'),
                        self::count('ballots_invalid', 'Nevažećih glasačkih listića'),
                        self::count('ballots_valid', 'Važećih glasačkih listića'),
                    ]),

                Section::make('Kontrolne sume')
                    ->columnSpan(1)
                    ->description('Računa se dok kucate. Zapisnik sa greškom se čuva kao „sa odstupanjem" i ne ulazi u zbir.')
                    ->components([
                        Placeholder::make('control')->hiddenLabel()->content(fn (Get $get) => self::controlSummary($get)),
                    ]),

                Section::make('Glasovi po izbornim listama')
                    ->columnSpanFull()
                    ->columns(3)
                    ->components(fn (Get $get): array => self::voteFields($get)),

                Section::make('Ostalo')
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        FileUpload::make('scans')
                            ->label('Skenirani zapisnik (PDF / slika)')
                            ->multiple()
                            ->disk('public')
                            ->directory('scans')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->maxSize(20480)
                            ->openable()
                            ->downloadable()
                            ->helperText('Objavljuje se javno uz brojeve — najjača garancija poverenja.'),
                        Textarea::make('notes')->label('Napomene')->rows(3),
                        Toggle::make('recount_requested')->label('Zatraženo ponovno brojanje'),
                    ]),
            ]);
    }

    private static function count(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->numeric()
            ->integer()
            ->minValue(0)
            ->default(0)
            ->required()
            ->live(onBlur: true);
    }

    private static function stationQuery(Get $get)
    {
        return Access::scopeMunicipality(
            PollingStation::query()->where('election_id', $get('election_id'))->with('municipality')->orderBy('number')
        );
    }

    private static function stationLabel(PollingStation $s): string
    {
        return "{$s->municipality->name} — BM {$s->number}: {$s->name}";
    }

    /** One numeric field per list of the unit the chosen station belongs to. */
    private static function voteFields(Get $get): array
    {
        $stationId = $get('polling_station_id');
        $electionId = $get('election_id');
        if (! $stationId || ! $electionId) {
            return [Placeholder::make('votes_hint')->hiddenLabel()->content('Izaberite biračko mesto da bi se prikazale liste.')->columnSpanFull()];
        }

        $station = PollingStation::find($stationId);
        $unit = $station ? ElectionUnit::query()
            ->where('election_id', $electionId)
            ->whereHas('municipalities', fn ($q) => $q->where('municipalities.id', $station->municipality_id))
            ->with('lists')
            ->first() : null;

        if ($unit === null) {
            return [Placeholder::make('votes_hint')->hiddenLabel()->content('Opština ovog biračkog mesta nije dodeljena nijednoj izbornoj jedinici.')->columnSpanFull()];
        }

        return $unit->lists->map(fn ($list) => TextInput::make("votes.{$list->id}")
            ->label("{$list->number}. {$list->name}")
            ->numeric()->integer()->minValue(0)->default(0)->required()
            ->live(onBlur: true)
        )->all();
    }

    private static function controlSummary(Get $get): HtmlString
    {
        $protocol = new Protocol();
        foreach (Protocol::COUNT_FIELDS as $field) {
            $protocol->{$field} = (int) ($get($field) ?? 0);
        }
        $votes = array_map('intval', (array) ($get('votes') ?? []));
        $result = app(ProtocolValidator::class)->validate($protocol, $votes);

        $rows = [];
        foreach (['K1', 'K2', 'K3', 'K4', 'K5', 'K6', 'K7'] as $code) {
            $ok = ! isset($result->errors[$code]);
            $rows[] = sprintf(
                '<li class="flex gap-2 text-sm"><span class="%s">%s</span><span><b>%s</b> %s</span></li>',
                $ok ? 'text-success-600' : 'text-danger-600',
                $ok ? '✔' : '✘',
                $code,
                e($ok ? 'u redu' : $result->errors[$code]),
            );
        }
        $rows[] = sprintf('<li class="text-sm mt-2">Odstupanje (K4): <b>%d</b> · Zbir glasova: <b>%d</b></li>', $result->deviation, array_sum($votes));

        return new HtmlString('<ul class="space-y-1">'.implode('', $rows).'</ul>');
    }
}
