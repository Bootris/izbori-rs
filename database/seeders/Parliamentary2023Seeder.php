<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AllocationMethod;
use App\Enums\ElectionStatus;
use App\Enums\ElectionType;
use App\Enums\ProtocolStatus;
use App\Enums\SubmitterType;
use App\Enums\UserRole;
use App\Models\Allocation;
use App\Models\Candidate;
use App\Models\Deadline;
use App\Models\Election;
use App\Models\ElectionUnit;
use App\Models\ElectoralList;
use App\Models\Municipality;
use App\Models\PollingStation;
use App\Models\Protocol;
use App\Models\ProtocolItem;
use App\Models\Submitter;
use App\Models\User;
use App\Services\Allocation\AllocatorFactory;
use App\Services\Validation\ProtocolValidator;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\Apportion;
use Database\Seeders\Support\Rng;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Izbori za narodne poslanike 17. 12. 2023: stvarne liste i zvanični
 * republički zbirovi, sintetički zapisnici po biračkim mestima koji se
 * sabiraju tačno u te zbirove (šta je stvarno, a šta procena — vidi
 * data/parliamentary_2023.php).
 *
 *   php artisan db:seed --class=Parliamentary2023Seeder                    # SEED_STAGE=final
 *   SEED_STAGE=counting php artisan db:seed --class=Parliamentary2023Seeder  # izborna noć
 */
final class Parliamentary2023Seeder extends Seeder
{
    public const SLUG = 'parlamentarni-2023';

    private const CHUNK = 1000;

    /** @var array<string, mixed> */
    private array $data;

    private Rng $rng;

    private Generator $faker;

    private string $stage;

    private CarbonImmutable $pollsClose;

    public function run(): void
    {
        $this->data = require database_path('seeders/data/parliamentary_2023.php');
        $this->stage = (string) config('izbori.seed_stage', 'final');
        if (! in_array($this->stage, ['final', 'counting'], true)) {
            throw new InvalidArgumentException("SEED_STAGE mora biti 'final' ili 'counting', dobijeno '{$this->stage}'.");
        }

        $this->rng = new Rng(20231217);
        $this->faker = FakerFactory::create('sr_Latn_RS');
        $this->pollsClose = CarbonImmutable::parse($this->data['election']['election_date'].' 20:00:00');

        $this->call([DatabaseSeeder::class, TerritorySeeder::class, UserSeeder::class]);

        Election::query()->where('slug', self::SLUG)->delete(); // FK cascade removes everything below it

        $election = $this->createElection();
        $unit = ElectionUnit::create([
            'election_id' => $election->id,
            'code' => $this->data['unit']['code'],
            'name' => $this->data['unit']['name'],
            'seats' => $election->seats,
        ]);

        $municipalities = Municipality::query()->with('district')->get()->keyBy('code');
        $unit->municipalities()->attach($municipalities->pluck('id')->all());

        $stations = $this->createStations($election, $municipalities);
        $lists = $this->createLists($election, $unit);
        $this->createCandidates($lists, $stations);
        $this->createDeadlines($election);
        $staff = $this->staff();

        ['counts' => $counts, 'voted' => $votedByMunicipality] = $this->createProtocols($election, $unit, $stations, $lists, $staff);
        $this->createTurnout($election, $stations, $votedByMunicipality, $staff);
        $seats = $this->createAllocation($election, $unit, $lists);

        $this->report($stations, $counts, $lists, $seats);
    }

    private function createElection(): Election
    {
        $config = $this->data['election'];
        $defaults = ElectionType::Parliamentary->defaults();

        return Election::create([
            'slug' => self::SLUG,
            'name' => $config['name'],
            'type' => ElectionType::Parliamentary,
            'election_date' => $config['election_date'],
            'round' => 1,
            'rounds' => $defaults['rounds'],
            'allocation' => AllocationMethod::from($defaults['allocation']),
            'seats' => $defaults['seats'],
            'threshold_pct' => $defaults['threshold_pct'],
            'minority_coef' => $defaults['minority_coef'],
            'status' => $this->stage === 'final' ? ElectionStatus::Final : ElectionStatus::Counting,
            'description' => $config['description'],
        ]);
    }

    /**
     * Registered voters per municipality (scaled to the official total), split
     * into stations of ~790 voters; diaspora stations come from the data file.
     *
     * @param  Collection<string, Municipality>  $municipalities  keyed by code
     * @return array<int, array{id:int, municipality_id:int, muni_name:string, district_code:string, number:string, registered:int, is_diaspora:bool}>
     */
    private function createStations(Election $election, Collection $municipalities): array
    {
        $territory = require database_path('seeders/data/territory.php');
        $diaspora = $this->data['diaspora_stations'];

        $weights = [];
        foreach ($territory as $district) {
            foreach ($district['municipalities'] as $code => [, $voters]) {
                if ($voters > 0) {
                    $weights[(string) $code] = $voters;
                }
            }
        }
        $domestic = $this->data['election']['registered_voters'] - array_sum(array_column($diaspora, 2));
        $registeredByMunicipality = Apportion::split($domestic, $weights);

        $now = now();
        $rows = [];
        foreach ($registeredByMunicipality as $code => $registered) {
            $municipalityId = $municipalities[$code]->id;
            $count = max(1, (int) round($registered / $this->data['voters_per_station']));
            $sizes = Apportion::split($registered, array_map(fn () => $this->rng->float(0.45, 1.55), range(1, $count)));

            foreach ($sizes as $i => $size) {
                $rows[] = [
                    'election_id' => $election->id,
                    'municipality_id' => $municipalityId,
                    'number' => str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                    'name' => $this->stationName(),
                    'address' => $this->rng->pick($this->data['streets']).' '.$this->rng->int(1, 120),
                    'registered_voters' => $size,
                    'accessible' => $this->rng->chance(0.55),
                    'is_diaspora' => false,
                    'country' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $dkp = $municipalities->first(fn (Municipality $m) => $m->district->code === '99');
        foreach ($diaspora as $i => [$city, $country, $registered]) {
            $rows[] = [
                'election_id' => $election->id,
                'municipality_id' => $dkp->id,
                'number' => str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'name' => "DKP {$city}",
                'address' => "Ambasada / konzulat Republike Srbije, {$city}",
                'registered_voters' => $registered,
                'accessible' => true,
                'is_diaspora' => true,
                'country' => $country,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table('polling_stations')->insert($chunk);
        }

        $byId = $municipalities->keyBy('id');

        return PollingStation::query()
            ->where('election_id', $election->id)
            ->orderBy('id')
            ->get(['id', 'municipality_id', 'number', 'registered_voters', 'is_diaspora'])
            ->map(fn (PollingStation $s) => [
                'id' => $s->id,
                'municipality_id' => $s->municipality_id,
                'muni_name' => $byId[$s->municipality_id]->name,
                'district_code' => $byId[$s->municipality_id]->district->code,
                'number' => $s->number,
                'registered' => $s->registered_voters,
                'is_diaspora' => $s->is_diaspora,
            ])
            ->all();
    }

    private function stationName(): string
    {
        return strtr($this->rng->pick($this->data['station_templates']), [
            '{skola}' => $this->rng->pick($this->data['school_names']),
            '{mz}' => $this->rng->pick($this->data['mz_names']),
            '{vrtic}' => $this->rng->pick($this->data['kindergarten_names']),
        ]);
    }

    /** @return array<int, array<string, mixed>>  list config + id + is_minority, keyed by list id */
    private function createLists(Election $election, ElectionUnit $unit): array
    {
        $submitters = [];
        foreach ($this->data['submitters'] as $key => $s) {
            $submitters[$key] = Submitter::create([
                'election_id' => $election->id,
                'name' => $s['name'],
                'short_name' => $s['short_name'],
                'type' => SubmitterType::from($s['type']),
                'is_minority' => $s['is_minority'],
                'color' => $s['color'],
            ]);
        }

        $lists = [];
        foreach ($this->data['lists'] as $config) {
            $submitter = $submitters[$config['submitter']];
            $list = ElectoralList::create([
                'election_id' => $election->id,
                'election_unit_id' => $unit->id,
                'submitter_id' => $submitter->id,
                'number' => $config['number'],
                'name' => $config['name'],
                'short_name' => $config['short_name'],
                'holder_name' => $config['holder_name'],
                'is_minority' => $submitter->is_minority,
                'color' => $submitter->color,
            ]);
            $lists[$list->id] = $config + ['id' => $list->id, 'is_minority' => $submitter->is_minority];
        }

        return $lists;
    }

    /**
     * Generated candidates (Serbian Latin names) honouring the gender quota:
     * at least two of each gender in every group of five. Position 1 is the
     * real list leader where the data file names one.
     *
     * @param  array<int, array<string, mixed>>  $lists
     * @param  array<int, array<string, mixed>>  $stations
     */
    private function createCandidates(array $lists, array $stations): void
    {
        $residences = array_values(array_unique(array_column(
            array_filter($stations, fn (array $s) => ! $s['is_diaspora']),
            'muni_name',
        )));

        $now = now();
        $rows = [];
        foreach ($lists as $list) {
            $pattern = [];
            for ($position = 1; $position <= $list['candidates']; $position++) {
                if (($position - 1) % 5 === 0) {
                    $pattern = $this->rng->shuffle(['M', 'M', 'F', 'F', $this->rng->pick(['M', 'F'])]);
                }

                if ($position === 1 && $list['first_candidate'] !== null) {
                    [$name, $gender] = $list['first_candidate'];
                    $pattern = [$gender, ...$this->rng->shuffle(['M', 'F', 'F', $gender === 'M' ? 'M' : 'F'])];
                    $rows[] = $this->candidateRow($list['id'], 1, $name, $gender, null, null, null, $now);

                    continue;
                }

                $gender = $pattern[($position - 1) % 5];
                $firstName = $gender === 'F' ? $this->faker->firstNameFemale() : $this->faker->firstNameMale();
                $rows[] = $this->candidateRow(
                    $list['id'],
                    $position,
                    $firstName.' '.$this->faker->lastName(),
                    $gender,
                    $this->rng->int(1952, 2001),
                    $this->rng->pick($this->data['occupations']),
                    $this->rng->pick($residences),
                    $now,
                );
            }
        }

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table('candidates')->insert($chunk);
        }
    }

    /** @return array<string, mixed> */
    private function candidateRow(int $listId, int $position, string $name, string $gender, ?int $birthYear, ?string $occupation, ?string $residence, \DateTimeInterface $now): array
    {
        return [
            'electoral_list_id' => $listId,
            'position' => $position,
            'full_name' => $name,
            'birth_year' => $birthYear,
            'occupation' => $occupation,
            'residence' => $residence,
            'gender' => $gender,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function createDeadlines(Election $election): void
    {
        foreach ($this->data['deadlines'] as $sort => [$date, $title, $description, $basis]) {
            Deadline::create([
                'election_id' => $election->id,
                'date' => $date,
                'title' => $title,
                'description' => $description,
                'legal_basis' => $basis,
                'sort_order' => $sort,
            ]);
        }
    }

    /** @return array{admin:int, by_municipality: array<int, array{operator:int, verifier:int}>} */
    private function staff(): array
    {
        $admin = (int) User::query()->where('email', config('izbori.admin.email'))->value('id');

        $byMunicipality = [];
        User::query()
            ->whereIn('role', [UserRole::Operator, UserRole::Verifier])
            ->whereNotNull('municipality_id')
            ->orderBy('id')
            ->get(['id', 'role', 'municipality_id'])
            ->each(function (User $user) use (&$byMunicipality): void {
                $key = $user->role === UserRole::Verifier ? 'verifier' : 'operator';
                $byMunicipality[$user->municipality_id][$key] ??= $user->id;
            });

        foreach ($byMunicipality as &$pair) {
            $pair += ['operator' => $admin, 'verifier' => $admin];
        }

        return ['admin' => $admin, 'by_municipality' => $byMunicipality];
    }

    /**
     * One protocol per station whose numbers satisfy K1–K7 (unless deliberately
     * flagged in the "counting" stage), plus items and the audit trail that
     * ProtocolService would have written.
     *
     * @param  array<int, array<string, mixed>>  $stations
     * @param  array<int, array<string, mixed>>  $lists
     * @param  array{admin:int, by_municipality: array<int, array{operator:int, verifier:int}>}  $staff
     * @return array{counts: array<string, int>, voted: array<int, int>}
     */
    private function createProtocols(Election $election, ElectionUnit $unit, array $stations, array $lists, array $staff): array
    {
        $votesByList = $this->distributeVotes($stations, $lists);
        $validator = new ProtocolValidator;
        $fallback = ['operator' => $staff['admin'], 'verifier' => $staff['admin']];

        $counts = ['verified' => 0, 'entered' => 0, 'flagged' => 0, 'missing' => 0];
        $votedByMunicipality = [];
        $protocolRows = [];
        $meta = [];

        foreach ($stations as $i => $station) {
            $items = [];
            $valid = 0;
            foreach ($lists as $listId => $list) {
                $items[$listId] = $votesByList[$listId][$i];
                $valid += $items[$listId];
            }
            $invalid = (int) round($valid * $this->data['invalid_ballots_pct'] / 100 * $this->rng->jitter(0.35));
            $voted = $valid + $invalid;

            if ($voted > $station['registered']) {
                throw new RuntimeException(sprintf(
                    'Biračko mesto %s / %s: %d glasalih > %d upisanih — smanji geo koeficijente u data/parliamentary_2023.php.',
                    $station['muni_name'], $station['number'], $voted, $station['registered'],
                ));
            }
            $votedByMunicipality[$station['municipality_id']] = ($votedByMunicipality[$station['municipality_id']] ?? 0) + $voted;

            $state = $this->stateFor();
            $counts[$state]++;
            if ($state === 'missing') {
                continue;
            }

            $users = $staff['by_municipality'][$station['municipality_id']] ?? $fallback;
            $enteredAt = $this->pollsClose->addMinutes(35 + $this->rng->int(0, 445));
            $corrected = $state === 'verified' && $this->rng->chance($this->data['corrected_share']);
            $updatedAt = $corrected ? $enteredAt->addMinutes($this->rng->int(5, 60)) : $enteredAt;
            $verifiedAt = $state === 'verified' ? $updatedAt->addMinutes(15 + $this->rng->int(0, 285)) : null;

            $attrs = [
                'registered_voters' => $station['registered'],
                'ballots_received' => $station['registered'],
                'ballots_unused' => $station['registered'] - $voted,
                'voters_voted' => $voted,
                'ballots_in_box' => $voted,
                'ballots_valid' => $valid,
                'ballots_invalid' => $invalid,
            ];

            $errors = [];
            $deviation = 0;
            if ($state === 'flagged') {
                if ($this->rng->chance(0.5)) {
                    $attrs['ballots_in_box'] += $this->rng->int(1, 3);                 // breaks K3 + K4
                } else {
                    $withVotes = array_keys(array_filter($items));
                    $items[$this->rng->pick($withVotes)] += $this->rng->int(1, 9);    // breaks K5
                }
                $result = $validator->validate(new Protocol($attrs), $items);
                $errors = $result->errors;
                $deviation = $result->deviation;
            }

            $protocolRows[] = $attrs + [
                'election_id' => $election->id,
                'election_unit_id' => $unit->id,
                'polling_station_id' => $station['id'],
                'round' => 1,
                'status' => ProtocolStatus::from($state)->value,
                'deviation' => $deviation,
                'validation_errors' => $errors === [] ? null : json_encode($errors, JSON_UNESCAPED_UNICODE),
                'recount_requested' => false,
                'notes' => null,
                'entered_by' => $users['operator'],
                'verified_by' => $verifiedAt === null ? null : $users['verifier'],
                'verified_at' => $verifiedAt,
                'revision' => $corrected ? 2 : 1,
                'created_at' => $enteredAt,
                'updated_at' => $verifiedAt ?? $updatedAt,
            ];
            $meta[$station['id']] = compact('items', 'attrs', 'users', 'enteredAt', 'updatedAt', 'verifiedAt', 'corrected');
        }

        foreach (array_chunk($protocolRows, self::CHUNK) as $chunk) {
            DB::table('protocols')->insert($chunk);
        }
        $protocolIds = Protocol::query()->where('election_id', $election->id)->pluck('id', 'polling_station_id');

        $itemRows = [];
        $revisionRows = [];
        foreach ($meta as $stationId => $m) {
            $protocolId = $protocolIds[$stationId];
            foreach ($m['items'] as $listId => $votes) {
                $itemRows[] = ['protocol_id' => $protocolId, 'electoral_list_id' => $listId, 'votes' => $votes, 'created_at' => $m['enteredAt'], 'updated_at' => $m['updatedAt']];
            }

            $revisionRows[] = ['protocol_id' => $protocolId, 'user_id' => $m['users']['operator'], 'action' => 'created', 'changes' => json_encode($this->createdChanges($m['attrs'], $m['items'])), 'created_at' => $m['enteredAt']];
            if ($m['corrected']) {
                $unused = $m['attrs']['ballots_unused'];
                $revisionRows[] = ['protocol_id' => $protocolId, 'user_id' => $m['users']['operator'], 'action' => 'updated', 'changes' => json_encode(['ballots_unused' => [$unused - 10, $unused]]), 'created_at' => $m['updatedAt']];
            }
            if ($m['verifiedAt'] !== null) {
                $revisionRows[] = ['protocol_id' => $protocolId, 'user_id' => $m['users']['verifier'], 'action' => 'verified', 'changes' => null, 'created_at' => $m['verifiedAt']];
            }
        }

        foreach (array_chunk($itemRows, 2 * self::CHUNK) as $chunk) {
            DB::table('protocol_items')->insert($chunk);
        }
        foreach (array_chunk($revisionRows, self::CHUNK) as $chunk) {
            DB::table('protocol_revisions')->insert($chunk);
        }

        return ['counts' => $counts, 'voted' => $votedByMunicipality];
    }

    /**
     * Each list's official national total is spread over stations in
     * proportion to registered voters × regional multiplier × noise, with
     * largest-remainder rounding so the station rows sum to the total exactly.
     *
     * @param  array<int, array<string, mixed>>  $stations
     * @param  array<int, array<string, mixed>>  $lists
     * @return array<int, array<int, int>> list id => [station index => votes]
     */
    private function distributeVotes(array $stations, array $lists): array
    {
        $result = [];
        foreach ($lists as $listId => $list) {
            $weights = [];
            foreach ($stations as $i => $station) {
                $multiplier = (float) ($list['geo']['municipalities'][$station['muni_name']]
                    ?? $list['geo']['districts'][$station['district_code']]
                    ?? $list['geo']['base']);
                $weights[$i] = $multiplier > 0 ? $station['registered'] * $multiplier * $this->rng->jitter(0.2) : 0.0;
            }
            $result[$listId] = Apportion::split((int) $list['votes'], $weights);
        }

        return $result;
    }

    private function stateFor(): string
    {
        if ($this->stage === 'final') {
            return 'verified';
        }

        $share = $this->data['counting'];
        $roll = $this->rng->float();

        return match (true) {
            $roll < $share['missing'] => 'missing',
            $roll < $share['missing'] + $share['entered'] => 'entered',
            $roll < $share['missing'] + $share['entered'] + $share['flagged'] => 'flagged',
            default => 'verified',
        };
    }

    /**
     * Same shape ProtocolService::diff() records for a new protocol.
     *
     * @param  array<string, int>  $attrs
     * @param  array<int, int>  $items
     * @return array<string, array{0:null, 1:mixed}>
     */
    private function createdChanges(array $attrs, array $items): array
    {
        $changes = [];
        foreach ($attrs + ['items' => $items] as $key => $value) {
            if ($value != null) {
                $changes[$key] = [null, $value];
            }
        }

        return $changes;
    }

    /**
     * Cumulative turnout per municipality at every cut-off, ending near the
     * final figure from the protocols.
     *
     * @param  array<int, array<string, mixed>>  $stations
     * @param  array<int, int>  $votedByMunicipality
     * @param  array{admin:int, by_municipality: array<int, array{operator:int, verifier:int}>}  $staff
     */
    private function createTurnout(Election $election, array $stations, array $votedByMunicipality, array $staff): void
    {
        $registered = [];
        foreach ($stations as $station) {
            $registered[$station['municipality_id']] = ($registered[$station['municipality_id']] ?? 0) + $station['registered'];
        }

        $rows = [];
        foreach ($votedByMunicipality as $municipalityId => $final) {
            $previous = 0;
            $enteredBy = $staff['by_municipality'][$municipalityId]['operator'] ?? $staff['admin'];
            foreach ($this->data['turnout_curve'] as $cutoff => $share) {
                $voted = (int) round($final * $share * $this->rng->jitter(0.05));
                $voted = min($registered[$municipalityId], max($previous, $voted));
                $previous = $voted;

                $at = $this->pollsClose->setTimeFromTimeString($cutoff)->addMinutes(20);
                $rows[] = [
                    'election_id' => $election->id,
                    'municipality_id' => $municipalityId,
                    'polling_station_id' => null,
                    'cutoff' => $cutoff,
                    'voters_voted' => $voted,
                    'entered_by' => $enteredBy,
                    'created_at' => $at,
                    'updated_at' => $at,
                ];
            }
        }

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table('turnout_snapshots')->insert($chunk);
        }
    }

    /**
     * Runs the real allocator over the verified protocols and persists the
     * result the way a publish would.
     *
     * @param  array<int, array<string, mixed>>  $lists
     * @return array<int, int> list id => seats
     */
    private function createAllocation(Election $election, ElectionUnit $unit, array $lists): array
    {
        $verified = Protocol::query()->where('election_id', $election->id)->verified();

        $votes = ProtocolItem::query()
            ->whereIn('protocol_id', (clone $verified)->select('id'))
            ->selectRaw('electoral_list_id, sum(votes) as votes')
            ->groupBy('electoral_list_id')
            ->pluck('votes', 'electoral_list_id');
        $totalVoted = (int) (clone $verified)->sum('voters_voted');
        $validVotes = (int) (clone $verified)->sum('ballots_valid');

        $input = [];
        foreach ($lists as $listId => $list) {
            $input[] = ['id' => $listId, 'votes' => (int) ($votes[$listId] ?? 0), 'is_minority' => $list['is_minority']];
        }

        $result = (new AllocatorFactory)->for($election)->allocate($input, $totalVoted, [
            'seats' => $unit->effectiveSeats(),
            'threshold_pct' => $election->threshold_pct,
            'minority_coef' => $election->minority_coef,
        ]);

        $allocation = Allocation::create([
            'election_id' => $election->id,
            'election_unit_id' => $unit->id,
            'round' => 1,
            'computed_at' => $this->stage === 'final' ? $this->pollsClose->addDays(4)->setTime(12, 0) : $this->pollsClose->addHours(6),
            'total_voted' => $totalVoted,
            'valid_votes' => $validVotes,
            'threshold_votes' => $result->thresholdVotes,
            'result' => $result->toArray(),
        ]);

        $candidates = Candidate::query()
            ->whereIn('electoral_list_id', array_keys($lists))
            ->get(['id', 'electoral_list_id', 'position'])
            ->groupBy('electoral_list_id')
            ->map(fn (Collection $group) => $group->pluck('id', 'position'));

        $now = now();
        $taken = [];
        $rows = [];
        foreach ($result->seatOrder as $seat) {
            $listId = $seat['list_id'];
            $taken[$listId] = ($taken[$listId] ?? 0) + 1;
            $rows[] = [
                'allocation_id' => $allocation->id,
                'electoral_list_id' => $listId,
                'candidate_id' => $candidates[$listId][$taken[$listId]] ?? null,
                'seat_no' => $seat['seat_no'],
                'divisor' => $seat['divisor'],
                'quotient' => $seat['quotient'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table('allocation_seats')->insert($chunk);
        }

        return $result->seatsByList;
    }

    /**
     * @param  array<int, array<string, mixed>>  $stations
     * @param  array<string, int>  $counts
     * @param  array<int, array<string, mixed>>  $lists
     * @param  array<int, int>  $seats
     */
    private function report(array $stations, array $counts, array $lists, array $seats): void
    {
        $expected = $this->data['expected_seats'];
        $rows = [];
        $mismatch = false;
        foreach ($lists as $listId => $list) {
            $won = $seats[$listId] ?? 0;
            $official = $expected[$list['short_name']] ?? 0;
            $mismatch = $mismatch || $won !== $official;
            $rows[] = [$list['number'], $list['short_name'], number_format((int) $list['votes'], 0, ',', '.'), $won, $official, $won === $official ? '' : '≠'];
        }

        $this->command?->table(['#', 'Lista', 'Glasova (RIK)', 'Mandata (izračunato)', 'Mandata (RIK)', ''], $rows);
        $this->command?->info(sprintf(
            'Faza "%s": %d biračkih mesta · zapisnici: %d verifikovanih, %d unetih, %d sa odstupanjem, %d neunetih.',
            $this->stage, count($stations), $counts['verified'], $counts['entered'], $counts['flagged'], $counts['missing'],
        ));

        if ($mismatch) {
            $this->command?->warn($this->stage === 'final'
                ? 'Raspodela mandata se razlikuje od zvanične — proveri glasove manjinskih lista u data/parliamentary_2023.php.'
                : 'U fazi "counting" raspodela ide samo po verifikovanim zapisnicima, pa se ne mora poklapati sa zvaničnom.');
        }
    }
}
