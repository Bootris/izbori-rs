# CLAUDE.md

Guidance for Claude Code working in this repo. Boris's general rules live in
`/var/www/html/ai-context/core/` (scope, no silent deletes, no new deps without
asking, Conventional Commits) — they apply here too.

## What this is

**IZBORI.RS** — a real-time election results platform for Serbia, modelled on the
Hungarian VTR (`vtr.valasztas.hu`, see [docs/ANALIZA-VTR-HU.md](docs/ANALIZA-VTR-HU.md))
and built on the same skeleton as `site-core` (Laravel + Filament admin).
Full spec: [docs/IZBORNI-SISTEM.md](docs/IZBORNI-SISTEM.md). Data contract the
public site consumes: [docs/DATA-CONTRACT.md](docs/DATA-CONTRACT.md).

**Stack:** Laravel 12 · Filament v4 (admin: data entry + verification) · SQLite
locally / PostgreSQL in production · React 19 + TypeScript + RTK Query + Tailwind 4
(public SPA, built by Vite) · PHP 8.2.

## Core decisions (don't relitigate)

- **The public site never talks to Laravel.** It reads only static JSON under
  `public/data/` (a CDN in production). Backend can be down on election night and
  the site keeps serving the last published snapshot.
- **Immutable versioned snapshots.** `izbori:publish` writes
  `data/{election}/{MMDDHHmm}/{registry|turnout|results|incidents}/…` + `manifest.json`
  (SHA-256 per file, chained to the previous snapshot), then atomically switches
  `data/{election}/config.json`. Old versions are never modified or deleted by
  default (`SNAPSHOT_KEEP_VERSIONS=0`).
- **Only verified protocols count.** A protocol enters aggregates only in status
  `verified`. Control sums K1–K7 (`App\Services\Validation\ProtocolValidator`)
  run on every save; a failing protocol is stored as `flagged`, shown publicly in
  `flagged.json`, but never summed. Editing a verified protocol resets it to
  `entered`/`flagged` and bumps `revision`; every change is audited in
  `protocol_revisions`.
- **Election is data, not code.** `elections.type/allocation/seats/threshold_pct/
  minority_coef/rounds` drive everything; `election_units` are orthogonal to the
  district → municipality hierarchy (parliamentary = 1 unit, local = 1 per JLS).
- **D'Hondt per Serbian law** (`App\Services\Allocation\DHondtAllocator`): 3 %
  threshold of voters who voted; minority lists qualify regardless and, while
  below the threshold, their quotients are multiplied by 1.35; ties → more votes,
  then a recorded lottery note. Presidential = `MajorityRunoffAllocator`.
- **Filament for the admin, never hand-rolled admin UI.** Admin path is
  `config('izbori.admin_path')` from `ADMIN_PATH` — never hardcode `/admin`.
- **Roles:** `admin` (RIK — everything, publishing), `verifier` (OIK/GIK — enters
  and verifies protocols of *its* municipality), `operator` (enters only, own
  municipality). Scoping lives in `App\Support\Access`.
- **No third-party scripts on the public site.** No Google Fonts/Maps/Analytics.
- **Eloquent strict mode is on** (`AppServiceProvider`): a lazy-loaded relation throws
  locally and in tests, and is only logged in production. Eager-load in
  `getEloquentQuery()` / `resolveRecord()`; never turn the guard off to make a test pass.
- **The SPA shell is stateless.** `routes/web.php` serves it without the `web`
  middleware group (no session row, no cookies) and with a public `Cache-Control`,
  so a refresh storm is absorbed by nginx microcache / the CDN, not php-fpm.
- **Incidents are an alarm, not a log.** `IncidentService::report()` is the only
  writer; it stamps `reported_at` from the server clock and notifies every panel
  user synchronously (`IncidentReported`, Filament database notifications).
  Only `is_public` incidents reach `incidents.json`; the reporter never does.

## Layout

```
app/Enums/                 ElectionType, AllocationMethod, ElectionStatus, ProtocolStatus, SubmitterType, SnapshotSource, UserRole,
                           IncidentCategory, IncidentSeverity, IncidentStatus
app/Models/                Election, ElectionUnit, District, Municipality, PollingStation, Submitter, ElectoralList,
                           Candidate, Protocol(+Item, Scan, Revision), TurnoutSnapshot, Deadline, Allocation(+Seat), Snapshot, Setting, User, Incident
app/Services/Validation/   ProtocolValidator (K1–K7)
app/Services/Protocols/    ProtocolService — the only way a protocol is written (unit resolution, validation, audit, verify/annul)
app/Services/Allocation/   DHondtAllocator, MajorityRunoffAllocator, AllocatorFactory
app/Services/Results/      ResultsAggregator — sums verified protocols per unit / municipality / election, always with `processed`
app/Services/Incidents/    IncidentService — report (stamp + alarm everyone), review, close, publish flag
app/Notifications/         IncidentReported — the alarm, database channel formatted for the Filament bell
app/Services/Snapshots/    SnapshotBuilder (DB → file set), SnapshotPublisher (write, manifest, atomic pointer, index.json)
app/Filament/              Resources per model, ManageSettings page, ElectionOverview + OpenIncidents widgets
app/Console/Commands/      izbori:publish, izbori:demo, izbori:import-stations, izbori:count
routes/console.php         scheduler: results every PUBLISH_INTERVAL_MINUTES while status=counting, turnout while voting,
                           incidents in both phases when a report changed
routes/web.php             SPA catch-all (everything except admin path, /data, /storage, /build, /up)
resources/js/              React SPA (entry app.tsx), resources/views/app.blade.php is the shell
docs/                      spec, VTR analysis, data contract, deploy guide
```

## Commands

```bash
./start.sh                      # one command: install, migrate, seed, demo data, publish, build, serve → :8000
./start.sh --dev                # + Vite HMR, queue worker, scheduler (election-night simulation)
./start.sh --fresh              # wipe DB + public/data and redo everything
php artisan izbori:demo --publish
php artisan izbori:publish <slug> --source=results   # registry | turnout | results | incidents, or --all
php artisan izbori:import-stations <slug> stations.csv
php artisan test                # sqlite :memory:, ~50 s (seeds the demo election)
./check-backend.sh              # smoke-test routes + data files
ab -n 600 -c 60 -k http://127.0.0.1:8000/<slug>/informacije   # shell load check (see docs/DEPLOY.md §8)
```

**Admin:** `/{ADMIN_PATH}` · seeded `SEED_ADMIN_EMAIL` / `SEED_ADMIN_PASSWORD`. Demo
also seeds `oik.nis@example.com` (verifier) and `operater.nis@example.com`
(operator), both `password`, scoped to Niš – Medijana.

## Conventions

- `declare(strict_types=1)` in every PHP file; PSR-12; thin controllers, logic in
  `app/Services`.
- Every aggregate published must carry `processed` (% of stations verified).
- New snapshot file = add it in `SnapshotBuilder`, document it in
  `docs/DATA-CONTRACT.md`, add an assertion in `tests/Feature/SnapshotPublishTest.php`.
- New admin work stays inside Filament resources; respect `Access` scoping for
  anything a verifier/operator can reach.
- Demo/fixture data must be clearly fictional (lists, candidates). Districts and
  municipalities may be real administrative units.
- Tests must pass (`php artisan test`) before a change is "done"; SPA changes must
  pass `npx tsc --noEmit` and `npm run build`.
