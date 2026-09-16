<?php

declare(strict_types=1);

namespace App\Services\Snapshots;

use App\Enums\ProtocolStatus;
use App\Enums\SnapshotSource;
use App\Models\Allocation;
use App\Models\Election;
use App\Models\ElectionUnit;
use App\Models\Incident;
use App\Models\Municipality;
use App\Models\PollingStation;
use App\Models\Protocol;
use App\Models\TurnoutSnapshot;
use App\Services\Allocation\AllocatorFactory;
use App\Services\Codebooks;
use App\Services\Results\ResultsAggregator;
use Illuminate\Support\Collection;

/**
 * Turns the database into the file set of one snapshot source (spec §7).
 * Returns relative path => ['list'|'data' => payload, 'processed' => ?float];
 * the publisher wraps every file in the common envelope and writes it.
 *
 * File names follow {district}/{kind}-{district}-{municipality}.json so a
 * municipality with 60 stations is a ~30 KB file — never one huge file.
 */
final class SnapshotBuilder
{
    /** Totals columns that add up across municipalities (the rest are recomputed). */
    private const DISTRICT_SUMS = [
        'stations_total', 'stations_verified', 'stations_entered', 'stations_flagged',
        'registered_voters_all', 'registered_voters', 'voters_voted',
        'ballots_in_box', 'ballots_valid', 'ballots_invalid',
    ];

    public function __construct(
        private readonly ResultsAggregator $aggregator,
        private readonly AllocatorFactory $allocators,
        private readonly Codebooks $codebooks,
    ) {}

    /** @return array<string, array{list?:array<int,mixed>, data?:array<string,mixed>, processed:?float}> */
    public function build(Election $election, SnapshotSource $source): array
    {
        return match ($source) {
            SnapshotSource::Registry => $this->registry($election),
            SnapshotSource::Turnout => $this->turnout($election),
            SnapshotSource::Results => $this->results($election),
            SnapshotSource::Incidents => $this->incidents($election),
        };
    }

    // ------------------------------------------------------------------ registry

    /** @return array<string, array<string, mixed>> */
    private function registry(Election $election): array
    {
        $units = $election->units()->with('municipalities.district')->get();
        $stations = PollingStation::query()->where('election_id', $election->id)->with('municipality.district')->orderBy('number')->get();
        $municipalities = $this->municipalitiesOf($units, $stations);
        $stationsByMunicipality = $stations->groupBy('municipality_id');
        $unitCodesByMunicipality = $this->unitCodesByMunicipality($units);
        $lists = $election->lists()->with(['candidates', 'submitter', 'unit'])->get();

        $files = [];

        $files['election.json'] = ['processed' => null, 'data' => [
            'id' => $election->id,
            'slug' => $election->slug,
            'name' => $election->name,
            'type' => $election->type->value,
            'election_date' => $election->election_date->toDateString(),
            'round' => $election->round,
            'rounds' => $election->rounds,
            'allocation' => $election->allocation->value,
            'seats' => $election->seats,
            'threshold_pct' => $election->threshold_pct,
            'minority_coef' => $election->minority_coef,
            'status' => $election->status->value,
            'description' => $election->description,
            'counts' => [
                'units' => $units->count(),
                'districts' => $municipalities->pluck('district_id')->unique()->count(),
                'municipalities' => $municipalities->count(),
                'stations' => $stations->count(),
                'registered_voters' => (int) $stations->sum('registered_voters'),
                'lists' => $lists->count(),
                'candidates' => $lists->sum(fn ($l) => $l->candidates->count()),
            ],
        ]];

        $files['codebooks.json'] = ['processed' => null, 'list' => $this->codebooks->all()];

        $files['districts.json'] = ['processed' => null, 'list' => $municipalities
            ->groupBy('district_id')
            ->map(function (Collection $group) use ($stationsByMunicipality) {
                $district = $group->first()->district;
                $districtStations = $group->flatMap(fn ($m) => $stationsByMunicipality->get($m->id, collect()));

                return [
                    'code' => $district->code,
                    'name' => $district->name,
                    'municipalities' => $group->count(),
                    'stations' => $districtStations->count(),
                    'registered_voters' => (int) $districtStations->sum('registered_voters'),
                ];
            })->sortBy('code')->values()->all()];

        $files['municipalities.json'] = ['processed' => null, 'list' => $municipalities->map(function (Municipality $m) use ($stationsByMunicipality, $unitCodesByMunicipality) {
            $ms = $stationsByMunicipality->get($m->id, collect());

            return [
                'code' => $m->code,
                'name' => $m->name,
                'district_code' => $m->district->code,
                'unit_codes' => $unitCodesByMunicipality[$m->id] ?? [],
                'stations' => $ms->count(),
                'registered_voters' => (int) $ms->sum('registered_voters'),
            ];
        })->values()->all()];

        $files['units.json'] = ['processed' => null, 'list' => $units->map(function (ElectionUnit $u) use ($stationsByMunicipality) {
            $us = $u->municipalities->flatMap(fn ($m) => $stationsByMunicipality->get($m->id, collect()));

            return [
                'code' => $u->code,
                'name' => $u->name,
                'seats' => $u->effectiveSeats() ?: null,
                'municipality_codes' => $u->municipalities->pluck('code')->all(),
                'stations' => $us->count(),
                'registered_voters' => (int) $us->sum('registered_voters'),
            ];
        })->values()->all()];

        $files['submitters.json'] = ['processed' => null, 'list' => $election->submitters()->orderBy('name')->get()->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'short_name' => $s->short_name,
            'type' => $s->type->value,
            'is_minority' => $s->is_minority,
            'color' => $s->color,
        ])->all()];

        $files['lists.json'] = ['processed' => null, 'list' => $lists->map(fn ($l) => [
            'id' => $l->id,
            'unit_code' => $l->unit->code,
            'number' => $l->number,
            'name' => $l->name,
            'short_name' => $l->short_name,
            'holder_name' => $l->holder_name,
            'is_minority' => $l->is_minority,
            'color' => $l->color ?? $l->submitter?->color,
            'submitter_id' => $l->submitter_id,
            'candidates' => $l->candidates->map(fn ($c) => [
                'position' => $c->position,
                'full_name' => $c->full_name,
                'birth_year' => $c->birth_year,
                'occupation' => $c->occupation,
                'residence' => $c->residence,
                'gender' => $c->gender,
            ])->all(),
        ])->all()];

        $files['deadlines.json'] = ['processed' => null, 'list' => $election->deadlines->map(fn ($d) => [
            'date' => $d->date->toDateString(),
            'title' => $d->title,
            'description' => $d->description,
            'legal_basis' => $d->legal_basis,
        ])->all()];

        foreach ($municipalities as $m) {
            $files[$this->municipalityFile('stations', $m)] = ['processed' => null, 'list' => $stationsByMunicipality
                ->get($m->id, collect())
                ->map(fn (PollingStation $s) => $this->stationRow($s))
                ->values()->all()];
        }

        return $files;
    }

    // ------------------------------------------------------------------- turnout

    /** @return array<string, array<string, mixed>> */
    private function turnout(Election $election): array
    {
        $cutoffs = config('izbori.turnout_cutoffs');
        $units = $election->units()->with('municipalities.district')->get();
        $stations = PollingStation::query()->where('election_id', $election->id)->with('municipality.district')->get();
        $municipalities = $this->municipalitiesOf($units, $stations);
        $registeredByMunicipality = $stations->groupBy('municipality_id')->map(fn ($g) => (int) $g->sum('registered_voters'));

        $rows = TurnoutSnapshot::query()->where('election_id', $election->id)->get();
        $stationRows = $rows->whereNotNull('polling_station_id');
        // Indexed once: 9,000 stations x 7 cut-offs must not be re-scanned per municipality.
        $stationSums = [];
        foreach ($stationRows as $r) {
            $stationSums[$r->municipality_id][$r->cutoff] = ($stationSums[$r->municipality_id][$r->cutoff] ?? 0) + (int) $r->voters_voted;
        }
        $municipalityValues = [];
        foreach ($rows->whereNull('polling_station_id') as $r) {
            $municipalityValues[$r->municipality_id][$r->cutoff] = (int) $r->voters_voted;
        }
        $stationsById = $stations->keyBy('id');

        // municipality value per cutoff: station-level sum wins over the municipality-level row
        $value = fn (int $municipalityId, string $cutoff): ?int => $stationSums[$municipalityId][$cutoff]
            ?? $municipalityValues[$municipalityId][$cutoff]
            ?? null;

        $pct = fn (?int $voted, int $registered): ?float => ($voted === null || $registered <= 0) ? null : round($voted / $registered * 100, 2);

        $municipalityList = $municipalities->map(function (Municipality $m) use ($cutoffs, $value, $registeredByMunicipality, $pct) {
            $registered = $registeredByMunicipality[$m->id] ?? 0;

            return [
                'code' => $m->code,
                'name' => $m->name,
                'district_code' => $m->district->code,
                'registered_voters' => $registered,
                'cutoffs' => collect($cutoffs)->map(fn ($c) => [
                    'cutoff' => $c,
                    'voters_voted' => $v = $value($m->id, $c),
                    'turnout_pct' => $pct($v, $registered),
                ])->all(),
            ];
        })->values();

        $rollup = function (Collection $group) use ($cutoffs, $pct): array {
            $registered = (int) $group->sum('registered_voters');

            return collect($cutoffs)->map(function ($c) use ($group, $registered, $pct) {
                $reported = $group->filter(fn ($m) => collect($m['cutoffs'])->firstWhere('cutoff', $c)['voters_voted'] !== null);
                $voted = $reported->isEmpty() ? null : (int) $reported->sum(fn ($m) => collect($m['cutoffs'])->firstWhere('cutoff', $c)['voters_voted']);

                return [
                    'cutoff' => $c,
                    'registered_voters' => $registered,
                    'voters_voted' => $voted,
                    'turnout_pct' => $pct($voted, $registered),
                    'municipalities_reported' => $reported->count(),
                    'municipalities_total' => $group->count(),
                ];
            })->all();
        };

        $files = [];
        $files['turnout-country.json'] = ['processed' => null, 'list' => $rollup($municipalityList)];
        // PHP turns numeric array keys ("12") into ints — keep codes textual as DATA-CONTRACT promises.
        $files['turnout-districts.json'] = ['processed' => null, 'list' => $municipalityList->groupBy('district_code')->map(fn ($g, $code) => [
            'district_code' => (string) $code,
            'registered_voters' => (int) $g->sum('registered_voters'),
            'cutoffs' => $rollup($g),
        ])->sortKeys()->values()->all()];
        $files['turnout-municipalities.json'] = ['processed' => null, 'list' => $municipalityList->all()];

        foreach ($stationRows->groupBy('municipality_id') as $municipalityId => $group) {
            $m = $municipalities->firstWhere('id', $municipalityId);
            if ($m === null) {
                continue;
            }
            $files[$this->municipalityFile('turnout', $m)] = ['processed' => null, 'list' => $group->groupBy('polling_station_id')->map(function ($g, $stationId) use ($stationsById, $cutoffs, $pct) {
                $s = $stationsById[$stationId];

                return [
                    'station_id' => $s->publicId(),
                    'number' => $s->number,
                    'registered_voters' => $s->registered_voters,
                    'cutoffs' => collect($cutoffs)->map(fn ($c) => [
                        'cutoff' => $c,
                        'voters_voted' => $v = $g->firstWhere('cutoff', $c)?->voters_voted,
                        'turnout_pct' => $pct($v, $s->registered_voters),
                    ])->all(),
                ];
            })->values()->all()];
        }

        return $files;
    }

    // ----------------------------------------------------------------- incidents

    /**
     * Election-day reports the commission chose to publish. Everything else about
     * an incident (reporter, internal notes) stays in the admin.
     *
     * @return array<string, array<string, mixed>>
     */
    private function incidents(Election $election): array
    {
        $incidents = Incident::query()
            ->where('election_id', $election->id)
            ->public()
            ->with(['pollingStation.municipality.district'])
            ->orderByDesc('reported_at')->orderByDesc('id')
            ->get();

        $list = $incidents->map(function (Incident $i) {
            $station = $i->pollingStation;
            $municipality = $station->municipality;

            return [
                'id' => $i->id,
                'station_id' => $station->publicId(),
                'station_number' => $station->number,
                'station_name' => $station->name,
                'municipality_code' => $municipality->code,
                'municipality_name' => $municipality->name,
                'district_code' => $municipality->district->code,
                'category' => $i->category->value,
                'severity' => $i->severity->value,
                'status' => $i->status->value,
                'description' => $i->description,
                'occurred_at' => $i->occurred_at->toIso8601String(),
                'reported_at' => $i->reported_at->toIso8601String(),
                'resolved_at' => $i->resolved_at?->toIso8601String(),
                'resolution' => $i->status->isClosed() ? $i->resolution : null,
            ];
        })->values()->all();

        return ['incidents.json' => ['processed' => null, 'list' => $list]];
    }

    // ------------------------------------------------------------------- results

    /** @return array<string, array<string, mixed>> */
    private function results(Election $election): array
    {
        $round = $election->round;
        $units = $election->units()->with(['municipalities.district', 'lists.submitter', 'lists.candidates'])->get();
        $stations = PollingStation::query()->where('election_id', $election->id)->with('municipality.district')->orderBy('number')->get();
        $municipalities = $this->municipalitiesOf($units, $stations);
        $unitByMunicipality = [];
        foreach ($units as $u) {
            foreach ($u->municipalities as $m) {
                $unitByMunicipality[$m->id] = $u;
            }
        }

        $files = [];
        $unitSummaries = [];
        $compositionByList = [];
        $compositionSeats = [];
        $seatsTotal = 0;
        $seatsAllocated = 0;
        $closeRaces = [];
        $winners = [];

        foreach ($units as $unit) {
            $totals = $this->aggregator->forUnit($unit, $round);
            $rows = $this->aggregator->listRows($unit->lists, $totals['votes_by_list'], $totals['ballots_valid']);
            $allocation = $this->allocate($election, $unit, $rows, $totals, $round);
            $result = $allocation->result;
            $seatsByList = $result['seats_by_list'];

            foreach ($rows as &$row) {
                $row['seats'] = $seatsByList[$row['list_id']] ?? 0;
                $row['qualified'] = in_array($row['list_id'], $result['qualified'], true);
            }
            unset($row);

            $seatRows = $this->seatRows($unit, $result['seat_order']);
            $unitSeats = $election->isProportional() ? $unit->effectiveSeats() : 0;
            $seatsTotal += $unitSeats;
            $seatsAllocated += count($seatRows);

            foreach ($rows as $row) {
                $key = $row['short_name'] ?? $row['name'];
                $compositionByList[$key] ??= ['name' => $row['name'], 'short_name' => $row['short_name'], 'color' => $row['color'], 'is_minority' => $row['is_minority'], 'seats' => 0, 'votes' => 0, 'list_ids' => []];
                $compositionByList[$key]['seats'] += $row['seats'];
                $compositionByList[$key]['votes'] += $row['votes'];
                $compositionByList[$key]['list_ids'][] = $row['list_id'];
            }
            foreach ($seatRows as $seat) {
                $compositionSeats[] = $seat + ['unit_code' => $unit->code];
            }

            $summary = [
                'code' => $unit->code,
                'name' => $unit->name,
                'seats' => $unitSeats ?: null,
                ...collect($totals)->except('votes_by_list')->all(),
                'lists' => $rows,
                'allocation' => [
                    'threshold_votes' => $result['threshold_votes'],
                    'notes' => $result['notes'],
                    'winner' => $result['winner'] ?? null,
                    'runoff' => $result['runoff'] ?? [],
                ],
            ];
            $unitSummaries[] = $summary;

            $files["results-unit-{$unit->code}.json"] = ['processed' => $totals['processed'], 'data' => $summary + [
                'matrix' => $result['matrix'],
                'seat_order' => $result['seat_order'],
                'seat_rows' => $seatRows,
            ]];

            $closeRaces = [...$closeRaces, ...$this->closeRaces($election, $unit, $rows, $result['threshold_votes'], $totals['voters_voted'])];
            $winners[] = $this->winnerRow($unit, $rows, $totals['processed'], $result);
        }

        $countryTotals = $this->aggregator->forElection($election, $round);

        $files['results-summary.json'] = ['processed' => $countryTotals['processed'], 'data' => [
            'round' => $round,
            ...collect($countryTotals)->except('votes_by_list')->all(),
            'units' => $unitSummaries,
        ]];

        $files['composition.json'] = ['processed' => $countryTotals['processed'], 'data' => [
            'seats_total' => $seatsTotal,
            'seats_allocated' => $seatsAllocated,
            'seats_empty' => max(0, $seatsTotal - $seatsAllocated),
            'by_list' => collect($compositionByList)->map(fn ($g) => $g + [
                'seats_pct' => $seatsTotal > 0 ? round($g['seats'] / $seatsTotal * 100, 2) : 0.0,
            ])->sortByDesc('seats')->values()->all(),
            'seats' => $compositionSeats,
        ]];

        $files['winners.json'] = ['processed' => $countryTotals['processed'], 'list' => $winners];
        $files['close-races.json'] = ['processed' => $countryTotals['processed'], 'list' => $closeRaces];

        $protocols = Protocol::query()
            ->where('election_id', $election->id)->where('round', $round)
            ->with(['items', 'scans'])
            ->get()->keyBy('polling_station_id');
        $stationsByMunicipality = $stations->groupBy('municipality_id');
        $flagged = [];
        $districtTotals = [];

        foreach ($municipalities as $m) {
            $unit = $unitByMunicipality[$m->id] ?? null;
            $mTotals = $this->aggregator->forMunicipality($election, $m, $round);
            $mRows = $unit ? $this->aggregator->listRows($unit->lists, $mTotals['votes_by_list'], $mTotals['ballots_valid']) : [];
            $this->addToDistrict($districtTotals, $m, $unit, $mTotals);

            $files[$this->municipalityFile('results', $m)] = ['processed' => $mTotals['processed'], 'data' => [
                'code' => $m->code,
                'name' => $m->name,
                'district_code' => $m->district->code,
                'unit_code' => $unit?->code,
                ...collect($mTotals)->except('votes_by_list')->all(),
                'lists' => $mRows,
            ]];

            $protocolRows = [];
            foreach ($stationsByMunicipality->get($m->id, collect()) as $station) {
                $p = $protocols->get($station->id);
                $protocolRows[] = $this->protocolRow($station, $p);
                if ($p?->status === ProtocolStatus::Flagged) {
                    $flagged[] = [
                        'station_id' => $station->publicId(),
                        'station_name' => $station->name,
                        'municipality_code' => $m->code,
                        'municipality_name' => $m->name,
                        'district_code' => $m->district->code,
                        'deviation' => $p->deviation,
                        'errors' => $p->validation_errors,
                        'revision' => $p->revision,
                    ];
                }
            }
            $files[$this->municipalityFile('protocols', $m)] = ['processed' => $mTotals['processed'], 'list' => $protocolRows];
        }

        $files['flagged.json'] = ['processed' => $countryTotals['processed'], 'list' => $flagged];
        $files['results-districts.json'] = ['processed' => $countryTotals['processed'], 'list' => $this->districtRows($districtTotals, $units)];

        return $files;
    }

    /**
     * Running sum of one district: municipality totals add up, per-list votes add
     * up, and the unit is kept only while every municipality shares the same one
     * (local elections can split a district across units, where list rows would
     * not be comparable).
     *
     * @param array<string, array<string, mixed>> $districts
     * @param array<string, mixed> $mTotals
     */
    private function addToDistrict(array &$districts, Municipality $m, ?ElectionUnit $unit, array $mTotals): void
    {
        $code = $m->district->code;
        $districts[$code] ??= [
            'name' => $m->district->name,
            'unit' => $unit,
            'mixed_units' => false,
            'votes' => [],
            'sums' => array_fill_keys(self::DISTRICT_SUMS, 0),
        ];

        if ($districts[$code]['unit']?->id !== $unit?->id) {
            $districts[$code]['mixed_units'] = true;
        }
        foreach (self::DISTRICT_SUMS as $key) {
            $districts[$code]['sums'][$key] += (int) $mTotals[$key];
        }
        foreach ($mTotals['votes_by_list'] as $listId => $votes) {
            $districts[$code]['votes'][$listId] = ($districts[$code]['votes'][$listId] ?? 0) + (int) $votes;
        }
    }

    /**
     * @param array<string, array<string, mixed>> $districts
     * @param Collection<int, ElectionUnit> $units
     * @return array<int, array<string, mixed>>
     */
    private function districtRows(array $districts, Collection $units): array
    {
        $rows = [];
        foreach ($districts as $code => $d) {
            $sums = $d['sums'];
            $unit = $d['mixed_units'] ? null : $d['unit'];
            $registered = $sums['registered_voters'];
            $voted = $sums['voters_voted'];

            $rows[] = [
                'district_code' => (string) $code,
                'name' => $d['name'],
                'unit_code' => $unit?->code,
                ...$sums,
                'processed' => $sums['stations_total'] > 0 ? round($sums['stations_verified'] / $sums['stations_total'] * 100, 2) : 0.0,
                'turnout_pct' => $registered > 0 ? round($voted / $registered * 100, 2) : 0.0,
                'invalid_pct' => $voted > 0 ? round($sums['ballots_invalid'] / $voted * 100, 2) : 0.0,
                'lists' => $unit ? $this->aggregator->listRows($unit->lists, $d['votes'], $sums['ballots_valid']) : [],
            ];
        }

        usort($rows, fn (array $a, array $b) => $a['district_code'] <=> $b['district_code']);

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $totals
     */
    private function allocate(Election $election, ElectionUnit $unit, array $rows, array $totals, int $round): Allocation
    {
        $input = array_map(fn ($r) => ['id' => $r['list_id'], 'votes' => $r['votes'], 'is_minority' => (bool) $r['is_minority']], $rows);

        $result = $this->allocators->for($election)->allocate($input, $totals['voters_voted'], [
            'seats' => $unit->effectiveSeats(),
            'threshold_pct' => $election->threshold_pct,
            'minority_coef' => $election->minority_coef,
            'round' => $round,
        ]);

        $allocation = Allocation::updateOrCreate(
            ['election_unit_id' => $unit->id, 'round' => $round],
            [
                'election_id' => $election->id,
                'computed_at' => now(),
                'total_voted' => $totals['voters_voted'],
                'valid_votes' => $totals['ballots_valid'],
                'threshold_votes' => $result->thresholdVotes,
                'result' => $result->toArray(),
            ],
        );

        $allocation->seats()->delete();
        $listsById = $unit->lists->keyBy('id');
        $taken = [];
        foreach ($result->seatOrder as $seat) {
            $k = $taken[$seat['list_id']] = ($taken[$seat['list_id']] ?? 0) + 1;
            $candidate = $listsById[$seat['list_id']]->candidates->firstWhere('position', $k);
            $allocation->seats()->create([
                'electoral_list_id' => $seat['list_id'],
                'candidate_id' => $candidate?->id,
                'seat_no' => $seat['seat_no'],
                'divisor' => $seat['divisor'],
                'quotient' => $seat['quotient'],
            ]);
        }

        return $allocation;
    }

    /**
     * Seat → candidate (k-th seat of a list goes to its k-th candidate).
     *
     * @param array<int, array{seat_no:int, list_id:int|string, divisor:int, quotient:float}> $seatOrder
     * @return array<int, array<string, mixed>>
     */
    private function seatRows(ElectionUnit $unit, array $seatOrder): array
    {
        $listsById = $unit->lists->keyBy('id');
        $taken = [];
        $rows = [];
        foreach ($seatOrder as $seat) {
            $list = $listsById[$seat['list_id']];
            $k = $taken[$list->id] = ($taken[$list->id] ?? 0) + 1;
            $candidate = $list->candidates->firstWhere('position', $k);
            $rows[] = [
                'seat_no' => $seat['seat_no'],
                'list_id' => $list->id,
                'list_number' => $list->number,
                'list_short_name' => $list->short_name ?? $list->name,
                'color' => $list->color ?? $list->submitter?->color,
                'divisor' => $seat['divisor'],
                'quotient' => $seat['quotient'],
                'candidate' => $candidate ? [
                    'position' => $candidate->position,
                    'full_name' => $candidate->full_name,
                    'birth_year' => $candidate->birth_year,
                    'occupation' => $candidate->occupation,
                    'residence' => $candidate->residence,
                ] : null,
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function closeRaces(Election $election, ElectionUnit $unit, array $rows, int $thresholdVotes, int $voted): array
    {
        $margin = (float) config('izbori.close_race_margin_pct');
        $races = [];

        if ($election->threshold_pct !== null && $voted > 0) {
            foreach ($rows as $row) {
                if ($row['is_minority']) {
                    continue;
                }
                $distance = ($row['votes'] - $thresholdVotes) / $voted * 100;
                if (abs($distance) <= $margin) {
                    $races[] = ['type' => 'threshold', 'unit_code' => $unit->code, 'list_id' => $row['list_id'], 'name' => $row['name'], 'margin_pct' => round($distance, 2)];
                }
            }
        }

        $sorted = collect($rows)->sortByDesc('votes')->values();
        if ($sorted->count() >= 2 && $sorted[0]['votes'] > 0) {
            $gap = $sorted[0]['votes_pct'] - $sorted[1]['votes_pct'];
            if ($gap <= $margin) {
                $races[] = ['type' => 'first_second', 'unit_code' => $unit->code, 'list_ids' => [$sorted[0]['list_id'], $sorted[1]['list_id']], 'names' => [$sorted[0]['name'], $sorted[1]['name']], 'margin_pct' => round($gap, 2)];
            }
        }

        return $races;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function winnerRow(ElectionUnit $unit, array $rows, float $processed, array $result): array
    {
        $sorted = collect($rows)->sortByDesc('votes')->values();
        $first = $sorted[0] ?? null;
        $second = $sorted[1] ?? null;

        return [
            'unit_code' => $unit->code,
            'unit_name' => $unit->name,
            'processed' => $processed,
            'leader' => $first ? ['list_id' => $first['list_id'], 'name' => $first['name'], 'votes' => $first['votes'], 'votes_pct' => $first['votes_pct'], 'seats' => $first['seats']] : null,
            'margin_pct' => ($first && $second) ? round($first['votes_pct'] - $second['votes_pct'], 2) : null,
            'winner' => $result['winner'] ?? null,
            'runoff' => $result['runoff'] ?? [],
        ];
    }

    /** @return array<string, mixed> */
    private function protocolRow(PollingStation $station, ?Protocol $p): array
    {
        $row = $this->stationRow($station) + ['status' => null];
        if ($p === null) {
            return $row;
        }

        // array_merge, not `+`: the union operator keeps the null `status` already in $row.
        return array_merge($row, [
            'status' => $p->status->value,
            'revision' => $p->revision,
            'recount_requested' => $p->recount_requested,
            'registered_voters_protocol' => $p->registered_voters,
            'ballots_received' => $p->ballots_received,
            'ballots_unused' => $p->ballots_unused,
            'voters_voted' => $p->voters_voted,
            'turnout_pct' => $p->turnoutPct(),
            'ballots_in_box' => $p->ballots_in_box,
            'ballots_valid' => $p->ballots_valid,
            'ballots_invalid' => $p->ballots_invalid,
            'deviation' => $p->deviation,
            'errors' => $p->validation_errors,
            'verified_at' => $p->verified_at?->toIso8601String(),
            'items' => $p->items->map(fn ($i) => [
                'list_id' => $i->electoral_list_id,
                'votes' => $i->votes,
                'votes_pct' => $p->ballots_valid > 0 ? round($i->votes / $p->ballots_valid * 100, 2) : 0.0,
            ])->values()->all(),
            'scans' => $p->scans->map(fn ($s) => $s->url())->all(),
        ]);
    }

    /** @return array<string, mixed> */
    private function stationRow(PollingStation $s): array
    {
        return [
            'station_id' => $s->publicId(),
            'number' => $s->number,
            'name' => $s->name,
            'address' => $s->address,
            'municipality_code' => $s->municipality->code,
            'district_code' => $s->municipality->district->code,
            'registered_voters' => $s->registered_voters,
            'accessible' => $s->accessible,
            'is_diaspora' => $s->is_diaspora,
            'country' => $s->country,
            'lat' => $s->lat,
            'lng' => $s->lng,
        ];
    }

    /**
     * Municipalities in play: everything attached to a unit plus anything that has stations.
     *
     * @param Collection<int, ElectionUnit> $units
     * @param Collection<int, PollingStation> $stations
     * @return Collection<int, Municipality>
     */
    private function municipalitiesOf(Collection $units, Collection $stations): Collection
    {
        $ids = $units->flatMap(fn ($u) => $u->municipalities->pluck('id'))
            ->merge($stations->pluck('municipality_id'))
            ->unique()->values();

        return Municipality::query()->with('district')->whereIn('id', $ids)
            ->orderBy('sort_order')->orderBy('code')->get();
    }

    /**
     * @param Collection<int, ElectionUnit> $units
     * @return array<int, array<int, string>>
     */
    private function unitCodesByMunicipality(Collection $units): array
    {
        $map = [];
        foreach ($units as $u) {
            foreach ($u->municipalities as $m) {
                $map[$m->id][] = $u->code;
            }
        }

        return $map;
    }

    private function municipalityFile(string $kind, Municipality $m): string
    {
        $d = $m->district->code;

        return "{$d}/{$kind}-{$d}-{$m->code}.json";
    }
}
