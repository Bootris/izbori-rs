<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Teritorijalni registar Republike Srbije
|--------------------------------------------------------------------------
| Šifre okruga prate redosled iz Uredbe o upravnim okruzima (00 = Grad
| Beograd, 01–07 Vojvodina, 08–24 centralna Srbija, 25–29 KiM, 99 = DKP).
| Šifra opštine = šifra okruga + redni broj; šifre koje koristi i
| DemoElectionSeeder su iste, pa oba seedera dele isti registar.
|
| Broj uz opštinu je PROCENJEN broj upisanih birača u hiljadama (popis
| 2022); Parliamentary2023Seeder ga skalira tako da zbir bude tačno
| 6.500.666, koliko je RIK objavio za izbore 2023. Na KiM RIK 2023. nije
| organizovao glasanje, pa te opštine nemaju birače ni biračka mesta
| (ostaju u registru radi prikaza i mapa). Beograd i Niš su razbijeni na
| gradske opštine jer RIK tako objavljuje rezultate.
*/

return [
    '00' => ['name' => 'Grad Beograd', 'municipalities' => [
        '0001' => ['Stari grad', 45], '0002' => ['Vračar', 55], '0003' => ['Novi Beograd', 205], '0004' => ['Zemun', 165],
        '0005' => ['Palilula', 175], '0006' => ['Zvezdara', 155], '0007' => ['Voždovac', 160], '0008' => ['Čukarica', 170],
        '0009' => ['Rakovica', 100], '0010' => ['Savski venac', 38], '0011' => ['Barajevo', 25], '0012' => ['Grocka', 80],
        '0013' => ['Lazarevac', 54], '0014' => ['Mladenovac', 48], '0015' => ['Obrenovac', 68], '0016' => ['Sopot', 18],
        '0017' => ['Surčin', 44],
    ]],
    '01' => ['name' => 'Severnobački', 'municipalities' => [
        '0101' => ['Subotica', 125], '0102' => ['Bačka Topola', 29], '0103' => ['Mali Iđoš', 10],
    ]],
    '02' => ['name' => 'Srednjebanatski', 'municipalities' => [
        '0201' => ['Zrenjanin', 108], '0202' => ['Žitište', 14], '0203' => ['Nova Crnja', 9], '0204' => ['Novi Bečej', 20], '0205' => ['Sečanj', 11],
    ]],
    '03' => ['name' => 'Severnobanatski', 'municipalities' => [
        '0301' => ['Kikinda', 50], '0302' => ['Ada', 15], '0303' => ['Kanjiža', 23], '0304' => ['Senta', 20], '0305' => ['Čoka', 10],
        '0306' => ['Novi Kneževac', 10],
    ]],
    '04' => ['name' => 'Južnobanatski', 'municipalities' => [
        '0401' => ['Pančevo', 115], '0402' => ['Vršac', 46], '0403' => ['Alibunar', 16], '0404' => ['Bela Crkva', 15], '0405' => ['Kovačica', 22],
        '0406' => ['Kovin', 29], '0407' => ['Opovo', 9], '0408' => ['Plandište', 10],
    ]],
    '05' => ['name' => 'Zapadnobački', 'municipalities' => [
        '0501' => ['Sombor', 73], '0502' => ['Apatin', 25], '0503' => ['Kula', 36], '0504' => ['Odžaci', 25],
    ]],
    '06' => ['name' => 'Južnobački', 'municipalities' => [
        '0601' => ['Novi Sad', 350], '0602' => ['Bačka Palanka', 47], '0603' => ['Bački Petrovac', 12], '0604' => ['Beočin', 14], '0605' => ['Bečej', 31],
        '0606' => ['Vrbas', 36], '0607' => ['Žabalj', 23], '0608' => ['Srbobran', 14], '0609' => ['Sremski Karlovci', 8], '0610' => ['Temerin', 27],
        '0611' => ['Titel', 14], '0612' => ['Bač', 12],
    ]],
    '07' => ['name' => 'Sremski', 'municipalities' => [
        '0701' => ['Sremska Mitrovica', 72], '0702' => ['Ruma', 48], '0703' => ['Inđija', 42], '0704' => ['Stara Pazova', 62], '0705' => ['Šid', 28],
        '0706' => ['Pećinci', 17], '0707' => ['Irig', 9],
    ]],
    '08' => ['name' => 'Mačvanski', 'municipalities' => [
        '0801' => ['Šabac', 105], '0802' => ['Bogatić', 25], '0803' => ['Vladimirci', 15], '0804' => ['Koceljeva', 12], '0805' => ['Krupanj', 14],
        '0806' => ['Loznica', 72], '0807' => ['Ljubovija', 12], '0808' => ['Mali Zvornik', 11],
    ]],
    '09' => ['name' => 'Kolubarski', 'municipalities' => [
        '0901' => ['Valjevo', 82], '0902' => ['Lajkovac', 13], '0903' => ['Ljig', 11], '0904' => ['Mionica', 12], '0905' => ['Osečina', 11], '0906' => ['Ub', 27],
    ]],
    '10' => ['name' => 'Podunavski', 'municipalities' => [
        '1001' => ['Smederevo', 96], '1002' => ['Velika Plana', 37], '1003' => ['Smederevska Palanka', 46],
    ]],
    '11' => ['name' => 'Braničevski', 'municipalities' => [
        '1101' => ['Požarevac', 64], '1102' => ['Veliko Gradište', 16], '1103' => ['Golubac', 7], '1104' => ['Žabari', 10], '1105' => ['Žagubica', 11],
        '1106' => ['Kučevo', 13], '1107' => ['Malo Crniće', 10], '1108' => ['Petrovac na Mlavi', 27],
    ]],
    '12' => ['name' => 'Šumadijski', 'municipalities' => [
        '1201' => ['Kragujevac', 165], '1202' => ['Aranđelovac', 41], '1203' => ['Batočina', 10], '1204' => ['Knić', 12], '1205' => ['Lapovo', 7],
        '1206' => ['Rača', 10], '1207' => ['Topola', 20],
    ]],
    '13' => ['name' => 'Pomoravski', 'municipalities' => [
        '1301' => ['Jagodina', 64], '1302' => ['Despotovac', 20], '1303' => ['Paraćin', 48], '1304' => ['Rekovac', 9], '1305' => ['Svilajnac', 21],
        '1306' => ['Ćuprija', 27],
    ]],
    '14' => ['name' => 'Borski', 'municipalities' => [
        '1401' => ['Bor', 42], '1402' => ['Kladovo', 18], '1403' => ['Majdanpek', 15], '1404' => ['Negotin', 31],
    ]],
    '15' => ['name' => 'Zaječarski', 'municipalities' => [
        '1501' => ['Zaječar', 50], '1502' => ['Boljevac', 11], '1503' => ['Knjaževac', 27], '1504' => ['Sokobanja', 14],
    ]],
    '16' => ['name' => 'Zlatiborski', 'municipalities' => [
        '1601' => ['Užice', 68], '1602' => ['Čajetina', 15], '1603' => ['Bajina Bašta', 23], '1604' => ['Kosjerić', 11], '1605' => ['Nova Varoš', 14],
        '1606' => ['Požega', 26], '1607' => ['Priboj', 23], '1608' => ['Prijepolje', 33], '1609' => ['Sjenica', 24], '1610' => ['Arilje', 17],
    ]],
    '17' => ['name' => 'Moravički', 'municipalities' => [
        '1701' => ['Čačak', 103], '1702' => ['Gornji Milanovac', 39], '1703' => ['Ivanjica', 28], '1704' => ['Lučani', 18],
    ]],
    '18' => ['name' => 'Raški', 'municipalities' => [
        '1801' => ['Kraljevo', 108], '1802' => ['Vrnjačka Banja', 26], '1803' => ['Novi Pazar', 98], '1804' => ['Raška', 22], '1805' => ['Tutin', 31],
    ]],
    '19' => ['name' => 'Rasinski', 'municipalities' => [
        '1901' => ['Kruševac', 112], '1902' => ['Aleksandrovac', 24], '1903' => ['Brus', 14], '1904' => ['Varvarin', 15], '1905' => ['Trstenik', 37],
        '1906' => ['Ćićevac', 8],
    ]],
    '20' => ['name' => 'Nišavski', 'municipalities' => [
        '2001' => ['Niš – Medijana', 80], '2002' => ['Niš – Palilula', 70], '2003' => ['Niš – Pantelej', 52], '2004' => ['Niš – Crveni krst', 30],
        '2005' => ['Niška Banja', 13], '2006' => ['Aleksinac', 45], '2007' => ['Gadžin Han', 8], '2008' => ['Doljevac', 16], '2009' => ['Merošina', 12],
        '2010' => ['Ražanj', 8], '2011' => ['Svrljig', 13],
    ]],
    '21' => ['name' => 'Toplički', 'municipalities' => [
        '2101' => ['Prokuplje', 39], '2102' => ['Blace', 10], '2103' => ['Žitorađa', 14], '2104' => ['Kuršumlija', 17],
    ]],
    '22' => ['name' => 'Pirotski', 'municipalities' => [
        '2201' => ['Pirot', 50], '2202' => ['Babušnica', 11], '2203' => ['Bela Palanka', 11], '2204' => ['Dimitrovgrad', 9],
    ]],
    '23' => ['name' => 'Jablanički', 'municipalities' => [
        '2301' => ['Leskovac', 125], '2302' => ['Bojnik', 10], '2303' => ['Lebane', 19], '2304' => ['Medveđa', 8], '2305' => ['Vlasotince', 27],
        '2306' => ['Crna Trava', 2],
    ]],
    '24' => ['name' => 'Pčinjski', 'municipalities' => [
        '2401' => ['Vranje', 75], '2402' => ['Bosilegrad', 7], '2403' => ['Bujanovac', 43], '2404' => ['Vladičin Han', 19], '2405' => ['Preševo', 33],
        '2406' => ['Surdulica', 18], '2407' => ['Trgovište', 5],
    ]],
    '25' => ['name' => 'Kosovski', 'municipalities' => [
        '2501' => ['Priština', 0], '2502' => ['Podujevo', 0], '2503' => ['Obilić', 0], '2504' => ['Kosovo Polje', 0], '2505' => ['Lipljan', 0],
        '2506' => ['Glogovac', 0], '2507' => ['Uroševac', 0], '2508' => ['Kačanik', 0], '2509' => ['Štimlje', 0], '2510' => ['Štrpce', 0],
    ]],
    '26' => ['name' => 'Pećki', 'municipalities' => [
        '2601' => ['Peć', 0], '2602' => ['Istok', 0], '2603' => ['Klina', 0], '2604' => ['Dečani', 0], '2605' => ['Đakovica', 0],
    ]],
    '27' => ['name' => 'Prizrenski', 'municipalities' => [
        '2701' => ['Prizren', 0], '2702' => ['Orahovac', 0], '2703' => ['Suva Reka', 0], '2704' => ['Gora', 0],
    ]],
    '28' => ['name' => 'Kosovskomitrovački', 'municipalities' => [
        '2801' => ['Kosovska Mitrovica', 0], '2802' => ['Zvečan', 0], '2803' => ['Zubin Potok', 0], '2804' => ['Leposavić', 0], '2805' => ['Vučitrn', 0],
        '2806' => ['Srbica', 0],
    ]],
    '29' => ['name' => 'Kosovsko-pomoravski', 'municipalities' => [
        '2901' => ['Gnjilane', 0], '2902' => ['Kosovska Kamenica', 0], '2903' => ['Vitina', 0], '2904' => ['Novo Brdo', 0],
    ]],
    // Biračka mesta u inostranstvu vodi RIK direktno; birači dolaze iz liste
    // `diaspora_stations` u parliamentary_2023.php, ne odavde.
    '99' => ['name' => 'Inostranstvo (DKP)', 'municipalities' => [
        '9901' => ['Diplomatsko-konzularna predstavništva', 0],
    ]],
];
