# IZBORI.RS

Platforma za objavu izbornih rezultata u realnom vremenu — zapisnici po biračkom
mestu, D'Hondt raspodela mandata, skenirani zapisnici, immutable snapshot-ovi i
javni sajt koji radi i kad backend padne. Po uzoru na mađarski VTR, prilagođeno
srpskom izbornom sistemu (parlamentarni, pokrajinski, lokalni, predsednički).

- Specifikacija: [docs/IZBORNI-SISTEM.md](docs/IZBORNI-SISTEM.md)
- Analiza uzora (vtr.valasztas.hu): [docs/ANALIZA-VTR-HU.md](docs/ANALIZA-VTR-HU.md)
- Ugovor podataka za javni sajt: [docs/DATA-CONTRACT.md](docs/DATA-CONTRACT.md)

## Brzi start

```bash
./start.sh              # http://127.0.0.1:8000  — sajt, admin i demo podaci
./start.sh --dev        # + hot reload, queue worker, scheduler
./start.sh --fresh      # sve ispočetka
```

Skripta sama odradi `composer install`, `npm install`, migracije, seed
administratora, demo izbor (fiktivne liste, ~900 biračkih mesta, generisani
zapisnici), objavu sva tri snapshot izvora i `npm run build`.

| | |
|---|---|
| Sajt | `http://127.0.0.1:8000/` |
| Podaci | `http://127.0.0.1:8000/data/index.json` |
| Admin | `http://127.0.0.1:8000/admin` (`ADMIN_PATH` u `.env`) |
| Admin nalog | `admin@example.com` / `password` (iz `SEED_ADMIN_*`) |
| OIK nalog (demo) | `oik.nis@example.com` / `password` — verifikator, samo Niš – Medijana |
| Operater (demo) | `operater.nis@example.com` / `password` — samo unos, cela opština |
| Kontrolor BM (demo) | `kontrolor.nis@example.com` / `password` — upisuje samo BM 001, ostatak opštine čita |

> ⚠ Promeni lozinke i postavi nasumičan `ADMIN_PATH` pre bilo kakvog javnog deploy-a.

## Kako radi

```
Biračko mesto / OIK  →  Filament admin (unos + K1–K7 kontrole + verifikacija)
                              │
                              ▼
                        PostgreSQL / SQLite  ──►  izbori:publish  ──►  public/data/{izbor}/{MMDDHHmm}/{izvor}/*.json
                                                                       + manifest.json (SHA-256 lanac)
                                                                       + config.json (atomski switch)
                                                                              │
                                                                              ▼
                                                              React SPA čita samo statičke JSON fajlove
```

1. **Unos.** Kontrolor biračkog mesta (samo svoje BM) ili operater/OIK (cela
   opština) unosi presek izlaznosti, zapisnik biračkog odbora i sken. Kontrolne
   sume K1–K7 se računaju uživo; zapisnik sa greškom se čuva kao *sa
   odstupanjem*, javno se vidi, ali ne ulazi u zbir.
2. **Verifikacija.** OIK verifikuje; tek verifikovani zapisnik ulazi u agregate.
   Verifikovan zapisnik se ne menja direktno: OIK ga „vraća na ispravku" uz razlog,
   pa se posle izmene ponovo verifikuje. Sve se pamti u istoriji (ko, kada, sa
   koje na koju vrednost).
3. **Objava.** `izbori:publish` (ručno iz admina ili scheduler na 2 min tokom
   brojanja) generiše kompletan set fajlova u novi, nepromenljiv folder, upiše
   manifest sa hash-om svakog fajla vezanim za prethodnu objavu, pa tek na kraju
   prebaci `config.json`. Rezultati se objavljuju samo u statusu „Brojanje" ili
   „Konačni rezultati" i tek posle zatvaranja biračkih mesta (20:00 na dan
   glasanja); vraćanje statusa unazad skida rezultate sa sajta. Prekid u bilo
   kojoj tački ne ostavlja sajt u nekonzistentnom stanju.
4. **Prikaz.** SPA polluje `config.json`; kad se verzija promeni, povlači nove
   fajlove. Na svakom agregatu stoji `processed` — procenat obrađenih biračkih
   mesta.

## Admin panel

| Grupa | Šta se radi |
|---|---|
| **Zapisnici** | Unos i verifikacija zapisnika (živi K1–K7, skenirani PDF/slika, poništenje, istorija izmena); izlaznost po presecima |
| **Izbori** | Izbori (tip, datum, pravila: mandati, cenzus, manjinski koeficijent, krugovi), izborne jedinice, rokovi, podnosioci, izborne liste i kandidati |
| **Teritorija** | Okruzi, opštine, biračka mesta (ili CSV uvoz: `izbori:import-stations`) |
| **Objava** | Istorija snapshot-ova (verzija, hash, veličina, trajanje), ručna objava |
| **Sistem** | Korisnici i uloge (admin / verifikator / operater + opština, kontrolor + dodeljena biračka mesta), podešavanja sajta |

## Komande

```bash
php artisan izbori:demo --publish                       # fiktivni parlamentarni izbori + objava
php artisan izbori:publish parlament-2026-demo --all    # registry + turnout + results
php artisan izbori:publish <slug> --source=results
php artisan izbori:import-stations <slug> stations.csv  # district_code,district_name,municipality_code,municipality_name,number,name,address,registered_voters,accessible,is_diaspora,country,lat,lng
php artisan schedule:work                               # automatska objava dok je status "brojanje"
php artisan test                                        # 26 testova (D'Hondt, K1–K7, workflow, admin, objava)
```

## Struktura podataka (skraćeno)

`elections` → `election_units` ⇄ `municipalities` → `polling_stations` → `protocols`
(+ `protocol_items` po listi, `protocol_scans`, `protocol_revisions`) ·
`submitters` → `electoral_lists` → `candidates` · `turnout_snapshots` · `deadlines`
· `allocations` + `allocation_seats` · `snapshots` · `settings` · `users`.
Detalji u [docs/IZBORNI-SISTEM.md](docs/IZBORNI-SISTEM.md) §2 i migraciji
`database/migrations/2026_09_15_000001_create_election_tables.php`.

## Produkcija (kratko)

- `DB_CONNECTION=pgsql`, `QUEUE_CONNECTION=database` + `php artisan queue:work`,
  `php artisan schedule:run` u cron-u svakog minuta.
- `public/data/` iza CDN-a: sve osim `config.json` i `index.json` je
  `Cache-Control: public, max-age=31536000, immutable`; ta dva su `no-cache`.
  Vidi `docs/DEPLOY.md`.
- Nasumičan `ADMIN_PATH`, prave `SEED_ADMIN_*` vrednosti, `APP_DEBUG=false`.
