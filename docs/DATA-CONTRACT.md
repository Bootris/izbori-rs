# Ugovor podataka: objavljeni JSON snapshot-ovi

Javni frontend čita **samo** ove fajlove (nikad bazu ni Laravel API). Generiše ih
`App\Services\Snapshots\SnapshotBuilder`, piše `SnapshotPublisher`.

```
/data/index.json                                   ← nekeširan; koje izbore prikazati
/data/{election}/config.json                       ← nekeširan; pointer na verzije
/data/{election}/{version}/{source}/…              ← immutable, Cache-Control: immutable
/data/{election}/{version}/{source}/manifest.json  ← SHA-256 svakog fajla + hash lanac
```

`version` = `MMDDHHmm` (mesec, dan, sat, minut objave). `source` ∈ `registry | turnout | results | incidents`.

## index.json
```json
{ "generated": "ISO-8601", "default": "parlament-2026-demo",
  "site": { "name", "publisher", "notice", "contact_email", "methodology_url",
            "links": { "legislation_url", "observers_url", "nominators_url", "forms_url", "commission_url", "news_url" } },
  "elections": [ { "slug", "name", "type", "election_date", "status", "round",
                   "sources": { "registry": "09150224", "turnout": "…|null", "results": "…|null", "incidents": "…|null" } } ] }
```
`site.links.*` su spoljni linkovi stranice „Informacije" (podešavanja u adminu); `null` = link se ne prikazuje.
Fajlovi objavljeni pre uvođenja izvora `incidents` nemaju taj ključ, klijent ga tretira kao `null`.

## config.json
```json
{ "election": "slug", "registry": "09150224", "turnout": "09150224", "results": "09150310", "incidents": "09150312", "updated": "ISO" }
```
Klijent pollinguje ovaj fajl (npr. na 60 s); kad se broj promeni, ponovo učitava fajlove tog izvora.

## Envelope (svaki fajl)
```json
{ "meta": { "generated", "electionDate", "electionId", "electionSlug", "electionType",
            "round", "source", "version", "processed": 83.45 | null },
  "list": [ … ]  |  "data": { … } }
```
`processed` = % verifikovanih biračkih mesta u opsegu fajla (null za registar/izlaznost).

## registry/
| Fajl | Ključ | Sadržaj |
|---|---|---|
| `election.json` | data | `{id, slug, name, type, election_date, round, rounds, allocation, seats, threshold_pct, minority_coef, status, description, counts:{units,districts,municipalities,stations,registered_voters,lists,candidates}}` |
| `codebooks.json` | list | `[{table, code, label}]` — ELECTION_TYPE, ELECTION_STATUS, ALLOCATION, PROTOCOL_STATUS, SUBMITTER_TYPE, TURNOUT_CUTOFF, CONTROL_SUM |
| `districts.json` | list | `[{code, name, municipalities, stations, registered_voters}]` |
| `municipalities.json` | list | `[{code, name, district_code, unit_codes[], stations, registered_voters}]` |
| `units.json` | list | `[{code, name, seats, municipality_codes[], stations, registered_voters}]` |
| `submitters.json` | list | `[{id, name, short_name, type, is_minority, color}]` |
| `lists.json` | list | `[{id, unit_code, number, name, short_name, holder_name, is_minority, color, submitter_id, candidates:[{position, full_name, birth_year, occupation, residence, gender}]}]` |
| `deadlines.json` | list | `[{date, title, description, legal_basis}]` |
| `{d}/stations-{d}-{m}.json` | list | `[Station]` — `{station_id:"d-m-number", number, name, address, municipality_code, district_code, registered_voters, accessible, is_diaspora, country, lat, lng}` |

## turnout/
| Fajl | Ključ | Sadržaj |
|---|---|---|
| `turnout-country.json` | list | po preseku: `{cutoff, registered_voters, voters_voted, turnout_pct, municipalities_reported, municipalities_total}` |
| `turnout-districts.json` | list | `[{district_code, registered_voters, cutoffs:[isti oblik kao gore]}]` |
| `turnout-municipalities.json` | list | `[{code, name, district_code, registered_voters, cutoffs:[{cutoff, voters_voted, turnout_pct}]}]` |
| `{d}/turnout-{d}-{m}.json` | list | po biračkom mestu (samo ako postoje unosi po BM) |

## results/
| Fajl | Ključ | Sadržaj |
|---|---|---|
| `results-summary.json` | data | `{round, …Totals, units:[UnitSummary]}` |
| `results-unit-{code}.json` | data | `UnitSummary + {matrix:{list_id:{divisor:quotient}}, seat_order:[{seat_no,list_id,divisor,quotient}], seat_rows:[Seat]}` |
| `composition.json` | data | `{seats_total, seats_allocated, seats_empty, by_list:[{name, short_name, color, is_minority, seats, votes, list_ids[], seats_pct}], seats:[Seat + unit_code]}` |
| `winners.json` | list | `[{unit_code, unit_name, processed, leader:{list_id,name,votes,votes_pct,seats}, margin_pct, winner, runoff[]}]` |
| `close-races.json` | list | `[{type:"threshold", unit_code, list_id, name, margin_pct} \| {type:"first_second", unit_code, list_ids[], names[], margin_pct}]` |
| `results-districts.json` | list | `[{district_code, name, unit_code, …Totals, lists:[ListRow]}]` — zbir opština okruga; `lists` je prazna kad okrug pokriva više izbornih jedinica |
| `flagged.json` | list | `[{station_id, station_name, municipality_code, municipality_name, district_code, deviation, errors:{K4:"…"}, revision}]` |
| `{d}/results-{d}-{m}.json` | data | `{code, name, district_code, unit_code, …Totals, lists:[ListRow]}` |
| `{d}/protocols-{d}-{m}.json` | list | `[Station + {status: null \| "entered"\|"flagged"\|"verified"\|"annulled", revision, recount_requested, registered_voters_protocol, ballots_received, ballots_unused, voters_voted, turnout_pct, ballots_in_box, ballots_valid, ballots_invalid, deviation, errors, verified_at, items:[{list_id, votes, votes_pct}], scans:[url]}]` |

## incidents/
| Fajl | Ključ | Sadržaj |
|---|---|---|
| `incidents.json` | list | `[{id, station_id, station_number, station_name, municipality_code, municipality_name, district_code, category, severity, status, description, occurred_at, reported_at, resolved_at, resolution}]`, najnovija prijava prva |

Prijave problema sa biračkih mesta. Objavljuju se **samo** prijave koje je admin (RIK) označio
kao javne (`incidents.is_public`); ko je prijavio i interne beleške nikad ne izlaze iz admina.
`reported_at` je vreme servera u trenutku prijave (nepromenljivo), `occurred_at` vreme
događaja po navodu prijavioca. `category` ∈ `voting_interrupted | materials | board_dispute |
voter_roll | intimidation | observers | facility | other`, `severity` ∈ `low | medium | high |
critical`, `status` ∈ `open | in_review | resolved | dismissed`; `resolution` je `null` dok
prijava nije zatvorena. `processed` je `null`. Scheduler objavljuje ovaj izvor dok je izbor u
statusu glasanje ili brojanje, ali samo kad se neka prijava promenila od prethodne objave.

**Totals** = `{stations_total, stations_verified, stations_entered, stations_flagged, processed, registered_voters_all, registered_voters, voters_voted, turnout_pct, ballots_in_box, ballots_valid, ballots_invalid, invalid_pct}`

**ListRow** = `{list_id, number, name, short_name, holder_name, is_minority, color, submitter_id, votes, votes_pct}`; u `UnitSummary.lists` još `seats`, `qualified`.

**UnitSummary** = `{code, name, seats, …Totals, lists:[ListRow+], allocation:{threshold_votes, notes[], winner, runoff[]}}`

**Seat** = `{seat_no, list_id, list_number, list_short_name, color, divisor, quotient, candidate:{position, full_name, birth_year, occupation, residence}|null}`

Napomena: za parlamentarne izbore postoji jedna jedinica (`RS`); za lokalne po jedna po opštini.
