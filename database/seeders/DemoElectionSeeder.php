<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AllocationMethod;
use App\Enums\ElectionStatus;
use App\Enums\ElectionType;
use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\SubmitterType;
use App\Enums\UserRole;
use App\Models\Candidate;
use App\Models\Deadline;
use App\Models\District;
use App\Models\Election;
use App\Models\ElectionUnit;
use App\Models\ElectoralList;
use App\Models\Incident;
use App\Models\Municipality;
use App\Models\PollingStation;
use App\Models\Protocol;
use App\Models\Submitter;
use App\Models\TurnoutSnapshot;
use App\Models\User;
use App\Services\Protocols\ProtocolService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * A fictional parliamentary election for local development. Districts and
 * municipalities are real administrative units (a subset); every list,
 * submitter and candidate is invented and labelled "(demo)".
 */
class DemoElectionSeeder extends Seeder
{
    public const SLUG = 'parlament-2026-demo';

    /** district code => [name, [municipality code => name, …]] — codes are demo identifiers */
    private const TERRITORY = [
        '00' => ['Grad Beograd', ['0001' => 'Stari grad', '0002' => 'Vračar', '0003' => 'Novi Beograd', '0004' => 'Zemun', '0005' => 'Palilula', '0006' => 'Zvezdara', '0007' => 'Voždovac', '0008' => 'Čukarica', '0009' => 'Rakovica', '0010' => 'Savski venac']],
        '06' => ['Južnobački', ['0601' => 'Novi Sad', '0602' => 'Bačka Palanka', '0603' => 'Bački Petrovac', '0604' => 'Beočin', '0605' => 'Bečej', '0606' => 'Vrbas', '0607' => 'Žabalj', '0608' => 'Srbobran', '0609' => 'Sremski Karlovci', '0610' => 'Temerin', '0611' => 'Titel']],
        '07' => ['Sremski', ['0701' => 'Sremska Mitrovica', '0702' => 'Ruma', '0703' => 'Inđija', '0704' => 'Stara Pazova', '0705' => 'Šid', '0706' => 'Pećinci', '0707' => 'Irig']],
        '12' => ['Šumadijski', ['1201' => 'Kragujevac', '1202' => 'Aranđelovac', '1203' => 'Batočina', '1204' => 'Knić', '1205' => 'Lapovo', '1206' => 'Rača', '1207' => 'Topola']],
        '16' => ['Zlatiborski', ['1601' => 'Užice', '1602' => 'Čajetina', '1603' => 'Bajina Bašta', '1604' => 'Kosjerić', '1605' => 'Nova Varoš', '1606' => 'Požega', '1607' => 'Priboj', '1608' => 'Prijepolje', '1609' => 'Sjenica', '1610' => 'Arilje']],
        '20' => ['Nišavski', ['2001' => 'Niš – Medijana', '2002' => 'Niš – Palilula', '2003' => 'Niš – Pantelej', '2004' => 'Niš – Crveni krst', '2005' => 'Niška Banja', '2006' => 'Aleksinac', '2007' => 'Gadžin Han', '2008' => 'Doljevac', '2009' => 'Merošina', '2010' => 'Ražanj', '2011' => 'Svrljig']],
        '23' => ['Jablanički', ['2301' => 'Leskovac', '2302' => 'Bojnik', '2303' => 'Lebane', '2304' => 'Medveđa', '2305' => 'Vlasotince', '2306' => 'Crna Trava']],
        '99' => ['Inostranstvo (DKP)', ['9901' => 'Diplomatsko-konzularna predstavništva']],
    ];

    /** [name, short, color, minority, weight] */
    private const LISTS = [
        ['Građanska alternativa (demo)', 'GA', '#1d4ed8', false, 31],
        ['Narodni blok (demo)', 'NB', '#b91c1c', false, 24],
        ['Zeleni horizont (demo)', 'ZH', '#15803d', false, 12],
        ['Socijalna pravda (demo)', 'SP', '#c2410c', false, 9],
        ['Liberalni centar (demo)', 'LC', '#7c3aed', false, 7],
        ['Ruralna Srbija (demo)', 'RS', '#a16207', false, 5],
        ['Studentski pokret (demo)', 'STP', '#0e7490', false, 3.2],
        ['Pokret za sever (demo)', 'PZS', '#4338ca', false, 2.8],
        ['Mađarska zajednica (demo)', 'MZ', '#be185d', true, 2.6],
        ['Bošnjačka lista (demo)', 'BL', '#065f46', true, 1.9],
        ['Romska inicijativa (demo)', 'RI', '#9333ea', true, 0.5],
    ];

    private const FIRST = ['Milan', 'Jelena', 'Nikola', 'Ana', 'Marko', 'Ivana', 'Stefan', 'Marija', 'Petar', 'Milica', 'Luka', 'Teodora', 'Aleksandar', 'Katarina', 'Vuk', 'Sofija', 'Đorđe', 'Nina', 'Filip', 'Tamara'];

    private const LAST = ['Petrović', 'Jovanović', 'Nikolić', 'Marković', 'Đorđević', 'Stojanović', 'Ilić', 'Stanković', 'Pavlović', 'Milošević', 'Kovačević', 'Lazić', 'Živković', 'Popović', 'Radović', 'Vasić', 'Todorović', 'Simić', 'Ristić', 'Kostić'];

    private const OCCUPATIONS = ['pravnik', 'ekonomista', 'inženjer', 'lekar', 'profesor', 'preduzetnik', 'novinar', 'poljoprivrednik', 'arhitekta', 'učitelj', 'programer', 'sociolog'];

    public function run(): void
    {
        mt_srand(2026);

        $election = Election::create([
            'slug' => self::SLUG,
            'name' => 'Izbori za narodne poslanike 2026 (demo podaci)',
            'type' => ElectionType::Parliamentary,
            // Last Sunday: the demo is a "counting" night, and results can only be
            // published after the polls closed on election day (Election::resultsPublishable).
            'election_date' => now(config('izbori.polls.timezone'))->previous(Carbon::SUNDAY)->toDateString(),
            'round' => 1,
            'rounds' => 1,
            'allocation' => AllocationMethod::DHondt,
            'seats' => 250,
            'threshold_pct' => 3.0,
            'minority_coef' => 1.35,
            'status' => ElectionStatus::Counting,
            'description' => 'Fiktivni podaci za razvoj i demonstraciju. Liste, podnosioci i kandidati su izmišljeni.',
        ]);

        $unit = ElectionUnit::create(['election_id' => $election->id, 'code' => 'RS', 'name' => 'Republika Srbija', 'seats' => 250]);

        $municipalities = $this->seedTerritory();
        $unit->municipalities()->sync($municipalities->pluck('id'));

        $stations = $this->seedStations($election, $municipalities);
        $lists = $this->seedLists($election, $unit);
        $this->seedDeadlines($election);
        $this->seedTurnout($election, $municipalities, $stations);

        $admin = User::query()->where('role', UserRole::Admin)->first() ?? User::factory()->create(['role' => UserRole::Admin]);
        $this->seedProtocols($election, $stations, $lists, $admin);
        $this->seedStaff($municipalities);
        $this->seedIncidents($election, $stations, $admin);
    }

    /** @return \Illuminate\Support\Collection<int, Municipality> */
    private function seedTerritory()
    {
        $municipalities = collect();
        $sort = 0;
        foreach (self::TERRITORY as $code => [$name, $munis]) {
            $district = District::firstOrCreate(['code' => $code], ['name' => $name, 'sort_order' => $sort++]);
            $mSort = 0;
            foreach ($munis as $mCode => $mName) {
                $municipalities->push(Municipality::firstOrCreate(
                    ['code' => $mCode],
                    ['district_id' => $district->id, 'name' => $mName, 'sort_order' => $mSort++],
                ));
            }
        }

        return $municipalities;
    }

    /** @return \Illuminate\Support\Collection<int, PollingStation> */
    private function seedStations(Election $election, $municipalities)
    {
        $stations = collect();
        foreach ($municipalities as $m) {
            $isDiaspora = $m->district->code === '99';
            $count = $isDiaspora ? 25 : mt_rand(6, 22);
            for ($i = 1; $i <= $count; $i++) {
                $stations->push(PollingStation::create([
                    'election_id' => $election->id,
                    'municipality_id' => $m->id,
                    'number' => str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                    'name' => $isDiaspora ? 'Ambasada / konzulat '.$i : ($i % 3 === 0 ? 'Osnovna škola br. '.$i : ($i % 3 === 1 ? 'Mesna zajednica '.$i : 'Dom kulture '.$i)),
                    'address' => $isDiaspora ? null : 'Ulica '.mt_rand(1, 120).', '.$m->name,
                    'registered_voters' => $isDiaspora ? mt_rand(150, 900) : mt_rand(350, 1900),
                    'accessible' => mt_rand(0, 100) < 60,
                    'is_diaspora' => $isDiaspora,
                    'country' => $isDiaspora ? ['Austrija', 'Nemačka', 'Švajcarska', 'Francuska', 'SAD', 'Kanada', 'Australija'][$i % 7] : null,
                ]));
            }
        }

        return $stations;
    }

    /** @return \Illuminate\Support\Collection<int, ElectoralList> */
    private function seedLists(Election $election, ElectionUnit $unit)
    {
        $lists = collect();
        foreach (self::LISTS as $i => [$name, $short, $color, $minority, $weight]) {
            $submitter = Submitter::create([
                'election_id' => $election->id,
                'name' => $name,
                'short_name' => $short,
                'type' => $i % 4 === 1 ? SubmitterType::Coalition : ($i === 6 ? SubmitterType::CitizenGroup : SubmitterType::Party),
                'is_minority' => $minority,
                'color' => $color,
            ]);
            $list = ElectoralList::create([
                'election_id' => $election->id,
                'election_unit_id' => $unit->id,
                'submitter_id' => $submitter->id,
                'number' => $i + 1,
                'name' => $name,
                'short_name' => $short,
                'holder_name' => $this->personName($i * 7),
                'is_minority' => $minority,
                'color' => $color,
            ]);
            $list->setAttribute('demo_weight', $weight);

            $candidateCount = $minority ? 25 : 120;
            for ($p = 1; $p <= $candidateCount; $p++) {
                Candidate::create([
                    'electoral_list_id' => $list->id,
                    'position' => $p,
                    'full_name' => $p === 1 ? $list->holder_name : $this->personName($i * 131 + $p),
                    'birth_year' => mt_rand(1958, 2002),
                    'occupation' => self::OCCUPATIONS[($i + $p) % count(self::OCCUPATIONS)],
                    'residence' => ['Beograd', 'Novi Sad', 'Niš', 'Kragujevac', 'Užice', 'Leskovac', 'Subotica'][($i * 3 + $p) % 7],
                    'gender' => ($p % 5 === 2 || $p % 5 === 4) ? 'F' : 'M',
                ]);
            }
            $lists->push($list);
        }

        return $lists;
    }

    private function seedDeadlines(Election $election): void
    {
        $day = $election->election_date;
        foreach ([
            [-60, 'Raspisivanje izbora', 'Odluka predsednika Republike o raspisivanju izbora.', 'ZINP čl. 30'],
            [-45, 'Rok za podnošenje izbornih lista', 'Do 20.00 časova.', 'ZINP čl. 56'],
            [-30, 'Proglašenje izbornih lista i utvrđivanje zbirne liste', null, 'ZINP čl. 70'],
            [-15, 'Zaključenje biračkog spiska', null, 'Zakon o jedinstvenom biračkom spisku'],
            [-2, 'Predizborna tišina', 'Zabrana izborne promocije 48 h pre glasanja.', 'ZINP čl. 130'],
            [0, 'Dan glasanja', 'Biračka mesta otvorena 07.00–20.00.', 'ZINP čl. 84'],
            [4, 'Rok za utvrđivanje ukupnih rezultata', 'RIK utvrđuje rezultate u roku od 96 časova.', 'ZINP čl. 112'],
        ] as $sort => [$offset, $title, $desc, $basis]) {
            Deadline::create([
                'election_id' => $election->id,
                'date' => $day->copy()->addDays($offset),
                'title' => $title,
                'description' => $desc,
                'legal_basis' => $basis,
                'sort_order' => $sort,
            ]);
        }
    }

    private function seedTurnout(Election $election, $municipalities, $stations): void
    {
        $curve = ['07:00' => 0.0, '09:00' => 0.06, '11:00' => 0.19, '13:00' => 0.33, '15:00' => 0.43, '17:00' => 0.52, '19:00' => 0.58];
        $registeredByMunicipality = $stations->groupBy('municipality_id')->map(fn ($g) => (int) $g->sum('registered_voters'));

        foreach ($municipalities as $m) {
            $registered = $registeredByMunicipality[$m->id] ?? 0;
            $noise = mt_rand(85, 115) / 100;
            foreach ($curve as $cutoff => $share) {
                TurnoutSnapshot::create([
                    'election_id' => $election->id,
                    'municipality_id' => $m->id,
                    'cutoff' => $cutoff,
                    'voters_voted' => (int) round($registered * $share * $noise),
                ]);
            }
        }
    }

    private function seedProtocols(Election $election, $stations, $lists, User $admin): void
    {
        $service = app(ProtocolService::class);
        $weights = $lists->map(fn ($l) => (float) $l->getAttribute('demo_weight'))->all();

        foreach ($stations as $station) {
            $roll = mt_rand(1, 100);
            if ($roll > 92) {
                continue;                                  // not entered yet (~8 %)
            }
            $flagged = $roll > 87;                         // ~5 % with a deviation

            $registered = $station->registered_voters;
            $voted = (int) round($registered * mt_rand(48, 68) / 100);
            $received = $registered + 20;
            $inBox = $flagged ? $voted - mt_rand(1, 4) : $voted;
            $invalid = (int) round($inBox * mt_rand(12, 30) / 1000);
            $valid = $inBox - $invalid;

            $votes = $this->splitVotes($valid, $weights, $station->municipality->district->code);
            $items = [];
            foreach ($lists as $i => $list) {
                $items[$list->id] = $votes[$i];
            }

            $protocol = new Protocol(['election_id' => $election->id, 'polling_station_id' => $station->id, 'round' => 1]);
            $protocol = $service->save($protocol, [
                'registered_voters' => $registered,
                'ballots_received' => $received,
                'ballots_unused' => $received - $voted,
                'voters_voted' => $voted,
                'ballots_in_box' => $inBox,
                'ballots_valid' => $valid,
                'ballots_invalid' => $invalid,
            ], $items, $admin);

            if (! $flagged && mt_rand(1, 100) <= 96) {
                $service->verify($protocol, $admin);
            }
        }
    }

    /**
     * Split valid votes across lists by weight with regional noise; integers that sum exactly.
     *
     * @param array<int, float> $weights
     * @return array<int, int>
     */
    private function splitVotes(int $valid, array $weights, string $districtCode): array
    {
        $adjusted = [];
        foreach ($weights as $i => $w) {
            $regional = match (true) {
                $districtCode === '06' && $i === 8 => 6.0,     // Hungarian minority list in Južnobački
                $districtCode === '16' && $i === 9 => 5.0,     // Bosniak list in Zlatiborski
                $districtCode === '99' && $i === 0 => 1.8,     // diaspora leans to list 1
                default => 1.0,
            };
            $adjusted[$i] = $w * $regional * mt_rand(70, 130) / 100;
        }
        $sum = array_sum($adjusted);
        $votes = array_map(fn ($a) => (int) floor($a / $sum * $valid), $adjusted);
        $rest = $valid - array_sum($votes);
        for ($i = 0; $rest > 0; $i = ($i + 1) % count($votes), $rest--) {
            $votes[$i]++;
        }

        return $votes;
    }

    /** A handful of invented election-day reports: some public and closed, one still open. */
    private function seedIncidents(Election $election, $stations, User $admin): void
    {
        $day = $election->election_date->copy();
        $reporter = User::query()->where('email', 'operater.nis@example.com')->first() ?? $admin;
        $byMunicipality = $stations->groupBy('municipality_id');
        $pick = fn (string $name, int $n) => $byMunicipality->get(Municipality::query()->where('name', $name)->value('id'), collect())->get($n);

        foreach ([
            ['Niš – Medijana', 2, '07:42', IncidentCategory::Facility, IncidentSeverity::Low, IncidentStatus::Resolved, true,
                'Nestanak struje u prostoriji biračkog mesta u trajanju od oko 15 minuta. Glasanje nastavljeno uz rezervno osvetljenje, kutija sve vreme pod nadzorom biračkog odbora.',
                'Napajanje vraćeno u 07.57. Biračko mesto radi bez prekida.'],
            ['Niš – Medijana', 5, '11:18', IncidentCategory::VoterRoll, IncidentSeverity::Medium, IncidentStatus::Resolved, true,
                'Dva birača sa važećim ličnim kartama nisu pronađena u izvodu iz biračkog spiska za ovo biračko mesto.',
                'Provera u OIK: birači upisani na susedno biračko mesto broj 4, upućeni tamo.'],
            ['Novi Sad', 1, '13:05', IncidentCategory::Observers, IncidentSeverity::High, IncidentStatus::Dismissed, true,
                'Predsednik biračkog odbora zatražio da posmatrač napusti prostoriju zbog fotografisanja glasačke kutije.',
                'Posmatrač upozoren, ostao na biračkom mestu. Fotografisanje nije ponovljeno.'],
            ['Kragujevac', 0, '15:31', IncidentCategory::Materials, IncidentSeverity::Critical, IncidentStatus::InReview, true,
                'Pri kontroli utvrđeno da je pečat biračkog odbora oštećen; listići overeni posle 15.00 nemaju čitljiv otisak.',
                null],
            ['Niš – Medijana', 7, '16:12', IncidentCategory::BoardDispute, IncidentSeverity::Medium, IncidentStatus::Open, false,
                'Član biračkog odbora iz proširenog sastava odbija da potpiše kontrolni list, tvrdi da kutija nije bila prazna pri otvaranju.',
                null],
        ] as [$municipality, $n, $time, $category, $severity, $status, $public, $description, $resolution]) {
            $station = $pick($municipality, $n);
            if ($station === null) {
                continue;
            }
            $occurred = $day->copy()->setTimeFromTimeString($time);
            Incident::create([
                'election_id' => $election->id,
                'polling_station_id' => $station->id,
                'municipality_id' => $station->municipality_id,
                'category' => $category,
                'severity' => $severity,
                'status' => $status,
                'description' => $description,
                'occurred_at' => $occurred,
                'reported_at' => $occurred->copy()->addMinutes(mt_rand(2, 9))->addSeconds(mt_rand(0, 59)),
                'reported_by' => $reporter->id,
                'reviewed_by' => $status === IncidentStatus::Open ? null : $admin->id,
                'reviewed_at' => $status === IncidentStatus::Open ? null : $occurred->copy()->addMinutes(12),
                'resolved_by' => $status->isClosed() ? $admin->id : null,
                'resolved_at' => $status->isClosed() ? $occurred->copy()->addMinutes(mt_rand(15, 70)) : null,
                'resolution' => $resolution,
                'is_public' => $public,
            ]);
        }
    }

    private function seedStaff($municipalities): void
    {
        $niš = $municipalities->firstWhere('name', 'Niš – Medijana');
        User::updateOrCreate(['email' => 'oik.nis@example.com'], [
            'name' => 'OIK Niš – Medijana', 'password' => 'password', 'role' => UserRole::Verifier, 'municipality_id' => $niš?->id,
        ]);
        User::updateOrCreate(['email' => 'operater.nis@example.com'], [
            'name' => 'Operater Niš – Medijana', 'password' => 'password', 'role' => UserRole::Operator, 'municipality_id' => $niš?->id,
        ]);

        // A polling-station controller: writes one station, reads the rest of the municipality.
        $controller = User::updateOrCreate(['email' => 'kontrolor.nis@example.com'], [
            'name' => 'Kontrolor BM 1 Niš – Medijana', 'password' => 'password', 'role' => UserRole::Controller, 'municipality_id' => $niš?->id,
        ]);
        $station = $niš ? PollingStation::query()->where('municipality_id', $niš->id)->orderBy('number')->first() : null;
        $controller->pollingStations()->sync($station ? [$station->id] : []);
    }

    private function personName(int $seed): string
    {
        return self::FIRST[$seed % count(self::FIRST)].' '.self::LAST[intdiv($seed, 3) % count(self::LAST)];
    }
}
