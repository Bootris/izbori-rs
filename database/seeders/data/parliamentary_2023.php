<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Izbori za narodne poslanike, 17. decembar 2023.
|--------------------------------------------------------------------------
| Šta je stvarno, a šta sintetičko:
|
|  STVARNO   – podnosioci i nazivi lista, nosioci lista, status manjinske
|              liste, pravila (250 mandata, cenzus 3 %, D'Hondt, ×1,35),
|              ukupan broj birača (6.500.666), ukupni glasovi lista koje su
|              prešle cenzus / osvojile mandate i krupnijih lista ispod
|              cenzusa, konačna raspodela mandata (129/65/18/13/13/6/2/1/1/1/1),
|              kalendar izbornih radnji.
|  PROCENA   – redosled na listiću posle 6. mesta, glasovi najmanjih
|              manjinskih lista (usklađeni da daju stvarni broj mandata),
|              broj kandidata po listi.
|  SINTETIČKO– sve ispod republičkog nivoa: zapisnici po biračkim mestima,
|              raspodela glasova po opštinama (regionalni koeficijenti `geo`),
|              imena kandidata od 2. mesta naniže, biračka mesta, izlaznost
|              po presecima. Manje liste koje nisu obuhvaćene (~1 % glasova)
|              nisu izmišljene, pa je ukupan broj važećih listića nešto
|              manji od zvaničnog.
|
| `geo` = koeficijent kojim se množi broj birača opštine pri raspodeli
| glasova jedne liste po biračkim mestima. Prioritet: opština > okrug > base.
*/

$bgCentar = ['Vračar', 'Stari grad', 'Savski venac', 'Novi Beograd', 'Zvezdara', 'Voždovac'];
$bgOstalo = ['Zemun', 'Čukarica', 'Palilula', 'Rakovica', 'Grocka', 'Obrenovac', 'Lazarevac', 'Mladenovac', 'Surčin', 'Barajevo', 'Sopot'];
$gradovi = ['Novi Sad', 'Niš – Medijana', 'Kragujevac', 'Čačak', 'Kraljevo', 'Kruševac', 'Pančevo', 'Zrenjanin', 'Šabac', 'Valjevo', 'Užice', 'Subotica', 'Sombor'];
$sandzak = ['Novi Pazar', 'Tutin', 'Sjenica', 'Prijepolje', 'Priboj', 'Nova Varoš'];
$dolina = ['Preševo', 'Bujanovac', 'Medveđa'];
$madjari = [
    'Kanjiža' => 45, 'Senta' => 40, 'Ada' => 40, 'Bačka Topola' => 30, 'Mali Iđoš' => 25, 'Čoka' => 18,
    'Subotica' => 16, 'Bečej' => 14, 'Novi Kneževac' => 12, 'Nova Crnja' => 6, 'Srbobran' => 5, 'Temerin' => 5,
    'Žitište' => 4, 'Kikinda' => 3, 'Novi Bečej' => 3, 'Zrenjanin' => 2, 'Sombor' => 2, 'Kovačica' => 2,
    'Sečanj' => 2, 'Plandište' => 2, 'Vršac' => 1, 'Kula' => 1, 'Odžaci' => 1, 'Apatin' => 1, 'Novi Sad' => 1,
];
$vojvodina = ['01', '02', '03', '04', '05', '06', '07'];
$centar = ['08', '09', '10', '11', '12', '13', '16', '17', '18', '19'];
$jug = ['14', '15', '20', '21', '22', '23', '24'];

$districts = function (array $pairs): array {
    $out = [];
    foreach ($pairs as [$codes, $v]) {
        $out += array_fill_keys($codes, $v);
    }

    return $out;
};
$fill = fn (array $names, float $v): array => array_fill_keys($names, $v);

return [
    'election' => [
        'slug' => 'parlamentarni-2023',
        'name' => 'Izbori za narodne poslanike Narodne skupštine Republike Srbije 2023',
        'election_date' => '2023-12-17',
        'registered_voters' => 6_500_666,
        'description' => 'Vanredni parlamentarni izbori raspisani 1. novembra 2023, održani 17. decembra 2023. '
            .'Republika Srbija je jedna izborna jedinica; 250 mandata deli se D\'Hondtovim metodom '
            .'među listama sa najmanje 3 % glasova birača koji su glasali, uz manjinske liste kojima se '
            .'količnici uvećavaju za 35 %. Podaci ispod republičkog nivoa u ovoj bazi su demonstracioni.',
    ],

    'unit' => ['code' => 'RS', 'name' => 'Republika Srbija'],

    'submitters' => [
        'sns' => ['name' => 'Srpska napredna stranka sa koalicionim partnerima (SDPS, PUPS, PS, SNP, PSS)', 'short_name' => 'SNS', 'type' => 'coalition', 'is_minority' => false, 'color' => '#2f5fb3'],
        'sps' => ['name' => 'Socijalistička partija Srbije - Jedinstvena Srbija - Zeleni Srbije', 'short_name' => 'SPS-JS', 'type' => 'coalition', 'is_minority' => false, 'color' => '#d4262e'],
        'spn' => ['name' => 'Koalicija Srbija protiv nasilja (SSP, NPS, ZLF, DS, PSG, SRCE, Ekološki ustanak, Zajedno)', 'short_name' => 'SPN', 'type' => 'coalition', 'is_minority' => false, 'color' => '#8a4fc9'],
        'srs' => ['name' => 'Srpska radikalna stranka', 'short_name' => 'SRS', 'type' => 'party', 'is_minority' => false, 'color' => '#24406e'],
        'nada' => ['name' => 'Koalicija NADA (Nova demokratska stranka Srbije, Pokret obnove Kraljevine Srbije)', 'short_name' => 'NADA', 'type' => 'coalition', 'is_minority' => false, 'color' => '#b58a10'],
        'svm' => ['name' => 'Savez vojvođanskih Mađara (Vajdasági Magyar Szövetség)', 'short_name' => 'SVM', 'type' => 'party', 'is_minority' => true, 'color' => '#2e8b57'],
        'ns' => ['name' => 'Narodna stranka', 'short_name' => 'NS', 'type' => 'party', 'is_minority' => false, 'color' => '#4c9be8'],
        'mi' => ['name' => 'Grupa građana „Mi - glas iz naroda“', 'short_name' => 'MI-GIN', 'type' => 'citizen_group', 'is_minority' => false, 'color' => '#1a9bab'],
        'no' => ['name' => 'Koalicija Nacionalno okupljanje (Srpski pokret Dveri, Srpska stranka Zavetnici)', 'short_name' => 'NO', 'type' => 'coalition', 'is_minority' => false, 'color' => '#7a1f1f'],
        'sda' => ['name' => 'Stranka demokratske akcije Sandžaka', 'short_name' => 'SDA', 'type' => 'party', 'is_minority' => true, 'color' => '#1f7a3f'],
        'spp' => ['name' => 'Stranka pravde i pomirenja', 'short_name' => 'SPP', 'type' => 'party', 'is_minority' => true, 'color' => '#2fa66a'],
        'zzv' => ['name' => 'Koalicija Zajedno za Vojvodinu - Vojvođani', 'short_name' => 'ZZV', 'type' => 'coalition', 'is_minority' => true, 'color' => '#d9b400'],
        'kad' => ['name' => 'Koalicija Albanaca Preševske doline', 'short_name' => 'KAD', 'type' => 'coalition', 'is_minority' => true, 'color' => '#b3261e'],
        'rus' => ['name' => 'Ruska stranka', 'short_name' => 'RUS', 'type' => 'party', 'is_minority' => true, 'color' => '#8a5a3c'],
    ],

    'lists' => [
        [
            'number' => 1, 'submitter' => 'sns', 'short_name' => 'SNS', 'votes' => 1_783_701, 'candidates' => 250,
            'name' => 'ALEKSANDAR VUČIĆ - SRBIJA NE SME DA STANE',
            'holder_name' => 'Aleksandar Vučić', 'first_candidate' => null,
            'geo' => ['base' => 1.0,
                'districts' => $districts([[$jug, 1.2], [$centar, 1.05], [$vojvodina, 0.95], [['99'], 0.5]]),
                'municipalities' => $fill($bgOstalo, 0.9) + $fill($gradovi, 0.8) + $fill($bgCentar, 0.6)
                    + $fill(array_keys($madjari), 0.6) + $fill($sandzak, 0.5) + $fill($dolina, 0.15)],
        ],
        [
            'number' => 2, 'submitter' => 'sps', 'short_name' => 'SPS-JS', 'votes' => 249_916, 'candidates' => 250,
            'name' => 'IVICA DAČIĆ - PREMIJER SRBIJE',
            'holder_name' => 'Ivica Dačić', 'first_candidate' => ['Ivica Dačić', 'M'],
            'geo' => ['base' => 1.0,
                'districts' => $districts([[$jug, 1.8], [$centar, 1.1], [$vojvodina, 0.9], [['99'], 0.6]]),
                'municipalities' => $fill($bgOstalo, 0.8) + $fill($bgCentar, 0.6) + $fill(array_keys($madjari), 0.5)
                    + $fill($sandzak, 0.5) + $fill($dolina, 0.15)],
        ],
        [
            'number' => 3, 'submitter' => 'spn', 'short_name' => 'SPN', 'votes' => 902_450, 'candidates' => 250,
            'name' => 'SRBIJA PROTIV NASILJA - MIROSLAV MIKI ALEKSIĆ - MARINIKA TEPIĆ',
            'holder_name' => 'Miroslav Aleksić, Marinika Tepić', 'first_candidate' => null,
            'geo' => ['base' => 1.0,
                'districts' => $districts([[$jug, 0.55], [$centar, 0.8], [$vojvodina, 1.1], [['99'], 1.9]]),
                'municipalities' => ['Novi Sad' => 1.9, 'Subotica' => 1.1] + $fill($bgCentar, 2.1) + $fill($gradovi, 1.6)
                    + $fill($bgOstalo, 1.3) + $fill(array_keys($madjari), 0.5) + $fill($sandzak, 0.5) + $fill($dolina, 0.08)],
        ],
        [
            'number' => 4, 'submitter' => 'srs', 'short_name' => 'SRS', 'votes' => 60_608, 'candidates' => 250,
            'name' => 'DR VOJISLAV ŠEŠELJ - SRPSKA RADIKALNA STRANKA',
            'holder_name' => 'Vojislav Šešelj', 'first_candidate' => ['Vojislav Šešelj', 'M'],
            'geo' => ['base' => 1.0,
                'districts' => $districts([[$vojvodina, 1.25], [['99'], 0.5]]),
                'municipalities' => $fill($bgCentar, 0.7) + $fill(array_keys($madjari), 0.5) + $fill($sandzak, 0.3) + $fill($dolina, 0.05)],
        ],
        [
            'number' => 5, 'submitter' => 'nada', 'short_name' => 'NADA', 'votes' => 191_431, 'candidates' => 250,
            'name' => 'NADA ZA SRBIJU - SRPSKA KOALICIJA NADA - NOVA DEMOKRATSKA STRANKA SRBIJE - POKRET OBNOVE KRALJEVINE SRBIJE - DR MILOŠ JOVANOVIĆ',
            'holder_name' => 'Miloš Jovanović', 'first_candidate' => ['Miloš Jovanović', 'M'],
            'geo' => ['base' => 1.0,
                'districts' => $districts([[$centar, 1.35], [$jug, 0.95], [$vojvodina, 0.75], [['99'], 1.3]]),
                'municipalities' => $fill($bgCentar, 1.2) + $fill(array_keys($madjari), 0.35) + $fill($sandzak, 0.2) + $fill($dolina, 0.03)],
        ],
        [
            'number' => 6, 'submitter' => 'svm', 'short_name' => 'SVM', 'votes' => 64_175, 'candidates' => 100,
            'name' => 'SAVEZ VOJVOĐANSKIH MAĐARA - IŠTVAN PASTOR (VAJDASÁGI MAGYAR SZÖVETSÉG - PÁSZTOR ISTVÁN)',
            'holder_name' => 'Ištvan Pastor', 'first_candidate' => null,
            'geo' => ['base' => 0.01,
                'districts' => $districts([[$vojvodina, 0.3], [['99'], 0.05]]),
                'municipalities' => $madjari],
        ],
        [
            'number' => 7, 'submitter' => 'ns', 'short_name' => 'NS', 'votes' => 47_138, 'candidates' => 250,
            'name' => 'NARODNA STRANKA - VUK JEREMIĆ',
            'holder_name' => 'Vuk Jeremić', 'first_candidate' => ['Vuk Jeremić', 'M'],
            'geo' => ['base' => 1.0,
                'districts' => $districts([[$vojvodina, 0.9], [$jug, 0.8], [['99'], 1.2]]),
                'municipalities' => $fill($bgOstalo, 1.1) + $fill($bgCentar, 1.5) + $fill(array_keys($madjari), 0.5) + $fill($sandzak, 0.3) + $fill($dolina, 0.03)],
        ],
        [
            'number' => 8, 'submitter' => 'mi', 'short_name' => 'MI-GIN', 'votes' => 178_830, 'candidates' => 250,
            'name' => 'MI - GLAS IZ NARODA - PROF. DR BRANIMIR NESTOROVIĆ',
            'holder_name' => 'Branimir Nestorović', 'first_candidate' => ['Branimir Nestorović', 'M'],
            'geo' => ['base' => 1.0,
                'districts' => $districts([[$jug, 0.8], [['99'], 0.9]]),
                'municipalities' => $fill($bgOstalo, 1.2) + $fill($bgCentar, 1.3) + $fill(array_keys($madjari), 0.5) + $fill($sandzak, 0.3) + $fill($dolina, 0.03)],
        ],
        [
            'number' => 9, 'submitter' => 'no', 'short_name' => 'NO', 'votes' => 103_318, 'candidates' => 250,
            'name' => 'NACIONALNO OKUPLJANJE - DRŽAVOTVORNA SNAGA - SRPSKI POKRET DVERI - SRPSKA STRANKA ZAVETNICI - MILICA ĐURĐEVIĆ STAMENKOVSKI - BOŠKO OBRADOVIĆ',
            'holder_name' => 'Milica Đurđević Stamenkovski, Boško Obradović', 'first_candidate' => null,
            'geo' => ['base' => 1.0,
                'districts' => $districts([[$centar, 1.3], [$jug, 0.9], [$vojvodina, 0.85], [['99'], 1.3]]),
                'municipalities' => $fill(array_keys($madjari), 0.4) + $fill($sandzak, 0.15) + $fill($dolina, 0.02)],
        ],
        [
            'number' => 10, 'submitter' => 'sda', 'short_name' => 'SDA', 'votes' => 24_932, 'candidates' => 84,
            'name' => 'SDA SANDŽAKA - DR SULEJMAN UGLJANIN',
            'holder_name' => 'Sulejman Ugljanin', 'first_candidate' => ['Sulejman Ugljanin', 'M'],
            'geo' => ['base' => 0.005, 'districts' => [],
                'municipalities' => ['Tutin' => 45, 'Novi Pazar' => 30, 'Sjenica' => 30, 'Prijepolje' => 8, 'Priboj' => 3, 'Nova Varoš' => 1.5]],
        ],
        [
            'number' => 11, 'submitter' => 'spp', 'short_name' => 'SPP', 'votes' => 16_238, 'candidates' => 84,
            'name' => 'STRANKA PRAVDE I POMIRENJA - SPP - USAME ZUKORLIĆ',
            'holder_name' => 'Usame Zukorlić', 'first_candidate' => ['Usame Zukorlić', 'M'],
            'geo' => ['base' => 0.005, 'districts' => [],
                'municipalities' => ['Novi Pazar' => 40, 'Tutin' => 22, 'Sjenica' => 22, 'Prijepolje' => 6, 'Priboj' => 2, 'Nova Varoš' => 1]],
        ],
        [
            'number' => 12, 'submitter' => 'zzv', 'short_name' => 'ZZV', 'votes' => 18_976, 'candidates' => 84,
            'name' => 'ZAJEDNO ZA VOJVODINU - VOJVOĐANI',
            'holder_name' => 'Aleksandar Olenik', 'first_candidate' => null,
            'geo' => ['base' => 0.02,
                'districts' => $districts([[$vojvodina, 1.0], [['99'], 0.05]]),
                'municipalities' => ['Novi Sad' => 2.0, 'Subotica' => 1.5, 'Sombor' => 1.4, 'Zrenjanin' => 1.3, 'Kikinda' => 1.2, 'Sremska Mitrovica' => 1.1, 'Pančevo' => 1.1]],
        ],
        [
            'number' => 13, 'submitter' => 'kad', 'short_name' => 'KAD', 'votes' => 14_571, 'candidates' => 84,
            'name' => 'KOALICIJA ALBANACA DOLINE - ŠAIP KAMBERI',
            'holder_name' => 'Šaip Kamberi', 'first_candidate' => ['Šaip Kamberi', 'M'],
            'geo' => ['base' => 0.002, 'districts' => [],
                'municipalities' => ['Preševo' => 60, 'Bujanovac' => 45, 'Medveđa' => 12]],
        ],
        [
            'number' => 14, 'submitter' => 'rus', 'short_name' => 'RUS', 'votes' => 12_364, 'candidates' => 84,
            'name' => 'RUSKA STRANKA - SLOBODAN NIKOLIĆ',
            'holder_name' => 'Slobodan Nikolić', 'first_candidate' => ['Slobodan Nikolić', 'M'],
            'geo' => ['base' => 1.0,
                'districts' => $districts([[$vojvodina, 1.3], [$jug, 1.1], [['99'], 0.3]]),
                'municipalities' => $fill($bgCentar, 0.7) + $fill(array_keys($madjari), 0.5) + $fill($sandzak, 0.2) + $fill($dolina, 0.03)],
        ],
    ],

    // Stvarna raspodela mandata (RIK, Izveštaj o ukupnim rezultatima) — seeder je
    // upoređuje sa onim što izračuna DHondtAllocator nad zapisnicima.
    'expected_seats' => ['SNS' => 129, 'SPN' => 65, 'SPS-JS' => 18, 'NADA' => 13, 'MI-GIN' => 13, 'SVM' => 6, 'SDA' => 2, 'SPP' => 1, 'ZZV' => 1, 'KAD' => 1, 'RUS' => 1],

    // Sintetički parametri
    'voters_per_station' => 790,          // ~8.200 biračkih mesta za 6,5 M birača
    'invalid_ballots_pct' => 1.16,        // udeo nevažećih listića
    'turnout_curve' => ['07:00' => 0.0, '09:00' => 0.06, '11:00' => 0.24, '13:00' => 0.44, '15:00' => 0.62, '17:00' => 0.80, '19:00' => 0.95],
    'counting' => ['missing' => 0.08, 'entered' => 0.03, 'flagged' => 0.015], // udeo biračkih mesta u stanju "counting"
    'corrected_share' => 0.015,           // udeo zapisnika sa ispravkom pre verifikacije (revizija 2)

    // Biračka mesta u inostranstvu: [grad, država, broj birača]
    'diaspora_stations' => [
        ['Beč', 'Austrija', 1450], ['Berlin', 'Nemačka', 900], ['Frankfurt', 'Nemačka', 1100], ['Minhen', 'Nemačka', 950],
        ['Štutgart', 'Nemačka', 1000], ['Cirih', 'Švajcarska', 1200], ['Bazel', 'Švajcarska', 500], ['Pariz', 'Francuska', 800],
        ['London', 'Ujedinjeno Kraljevstvo', 700], ['Stokholm', 'Švedska', 400], ['Čikago', 'SAD', 750], ['Njujork', 'SAD', 500],
        ['Toronto', 'Kanada', 600], ['Sidnej', 'Australija', 450], ['Banja Luka', 'Bosna i Hercegovina', 900],
        ['Podgorica', 'Crna Gora', 650], ['Skoplje', 'Severna Makedonija', 300], ['Ljubljana', 'Slovenija', 350],
    ],

    'deadlines' => [
        ['2023-11-01', 'Raspisivanje izbora', 'Predsednik Republike raspisao izbore za narodne poslanike za 17. decembar 2023.', 'ZINP'],
        ['2023-11-02', 'Rokovnik izbornih radnji', 'RIK utvrdila rokovnik za vršenje izbornih radnji i propisala obrasce.', 'Odluka RIK'],
        ['2023-11-27', 'Rok za podnošenje izbornih lista', 'Izborne liste podnose se RIK najkasnije 20 dana pre dana glasanja, do 24 časa.', 'ZINP'],
        ['2023-12-01', 'Zaključenje biračkog spiska', 'Ministarstvo nadležno za birački spisak zaključuje spisak 15 dana pre dana glasanja.', 'Zakon o jedinstvenom biračkom spisku'],
        ['2023-12-02', 'Utvrđivanje zbirne izborne liste', 'RIK utvrđuje i objavljuje zbirnu izbornu listu; redosled = redosled proglašenja.', 'ZINP'],
        ['2023-12-06', 'Objavljivanje ukupnog broja birača', 'RIK objavljuje ukupan broj birača u Republici Srbiji i po biračkim mestima.', 'ZINP'],
        ['2023-12-07', 'Određivanje biračkih mesta', 'RIK određuje i objavljuje biračka mesta najkasnije 10 dana pre dana glasanja.', 'ZINP'],
        ['2023-12-12', 'Obaveštenje biračima', 'Rok za dostavljanje obaveštenja o danu i vremenu glasanja, sa brojem i adresom biračkog mesta.', 'ZINP'],
        ['2023-12-15', 'Izborna tišina', 'Zabrana izborne promocije od 48 časova pre dana glasanja do zatvaranja biračkih mesta.', 'ZINP'],
        ['2023-12-17', 'Dan glasanja', 'Biračka mesta otvorena od 7 do 20 časova.', 'ZINP'],
        ['2023-12-21', 'Utvrđivanje ukupnih rezultata', 'RIK utvrđuje ukupne rezultate izbora u roku od 96 časova od zatvaranja biračkih mesta.', 'ZINP'],
        ['2023-12-30', 'Ponovljeno glasanje', 'Ponovljeno glasanje na biračkim mestima na kojima je glasanje poništeno zbog nepravilnosti.', 'Rešenja RIK'],
        ['2024-02-06', 'Konstitutivna sednica Narodne skupštine', 'Potvrđivanje mandata narodnih poslanika i konstituisanje XIV saziva.', 'Ustav RS, Poslovnik NS'],
    ],

    // Generisanje naziva i adresa biračkih mesta / kandidata
    'station_templates' => [
        'OŠ „{skola}“', 'OŠ „{skola}“', 'OŠ „{skola}“', 'OŠ „{skola}“', 'Mesna zajednica {mz}', 'Mesna zajednica {mz}',
        'Dom kulture', 'Zgrada opštine', 'Gimnazija', 'Srednja tehnička škola', 'Dom zdravlja', 'Vrtić „{vrtic}“',
        'Mesna kancelarija', 'Sportska hala', 'Vatrogasni dom', 'Zadružni dom', 'Ekonomska škola', 'Dom penzionera',
    ],
    'school_names' => [
        'Vuk Karadžić', 'Sveti Sava', 'Dositej Obradović', 'Branko Radičević', 'Jovan Jovanović Zmaj', 'Đura Jakšić',
        'Nikola Tesla', 'Petar Petrović Njegoš', 'Ivo Andrić', 'Desanka Maksimović', 'Kralj Petar I', 'Stevan Sremac',
        'Milutin Milanković', 'Karađorđe', 'Vojvoda Stepa', 'Mihajlo Pupin', 'Filip Filipović', 'Laza Kostić',
        'Jovan Cvijić', 'Josif Pančić', 'Đorđe Krstić', 'Rade Končar', 'Stevan Sinđelić', 'Vasa Pelagić',
    ],
    'mz_names' => ['Centar', 'Stari grad', 'Novo naselje', 'Sever', 'Jug', 'Istok', 'Zapad', 'Kolonija', 'Železnička', 'Dunav', 'Morava', 'Sloboda', 'Bratstvo', 'Ratarska', 'Vinogradi'],
    'kindergarten_names' => ['Pčelica', 'Zvezdica', 'Leptirić', 'Bambi', 'Sunce', 'Cvrčak', 'Vrabac', 'Kolibri', 'Poletarac', 'Radost'],
    'streets' => [
        'Kralja Petra I', 'Cara Dušana', 'Svetog Save', 'Vuka Karadžića', 'Nemanjina', 'Karađorđeva', 'Njegoševa', 'Kneza Miloša',
        'Bulevar oslobođenja', 'Glavna', 'Save Kovačevića', 'Železnička', 'Školska', 'Partizanska', 'Omladinska', 'Cara Lazara',
        'Kralja Aleksandra', 'Jovana Cvijića', 'Dositejeva', 'Braće Jugovića', 'Vojvode Mišića', 'Nikole Pašića', 'Hajduk Veljkova',
        'Dunavska', 'Moravska', 'Sremska', 'Banatska', 'Šumadijska', 'Zmaj Jovina', 'Miloša Obilića',
    ],
    'occupations' => [
        'diplomirani pravnik', 'diplomirani ekonomista', 'diplomirani inženjer', 'lekar', 'profesor', 'nastavnik', 'preduzetnik',
        'poljoprivrednik', 'penzioner', 'novinar', 'vaspitač', 'arhitekta', 'stomatolog', 'programer', 'medicinska sestra',
        'student', 'mašinski tehničar', 'trgovac', 'vozač', 'farmaceut', 'politikolog', 'sociolog', 'menadžer', 'ugostitelj',
    ],
];
