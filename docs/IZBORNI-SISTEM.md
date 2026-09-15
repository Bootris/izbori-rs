# IZBORI.RS — tehnička specifikacija

Sistem za objavu izbornih rezultata u realnom vremenu, po uzoru na mađarski VTR,
prilagođen srpskom izbornom sistemu i projektovan **generički** — jedan engine
za parlamentarne, lokalne i predsedničke izbore.

---

## 1. Srpski izborni sistem — domenska pravila

> Izvori: [RIK — O izborima za narodne poslanike](https://www.rik.parlament.gov.rs/tekst/sr/77/o-izborima-za-narodne-poslanike.php),
> [Zakon o izboru narodnih poslanika](https://www.rik.parlament.gov.rs/extfile/sr/325867/BOS_6.%20Zakon%20o%20izboru%20narodnih%20poslanika.pdf),
> [Zakon o lokalnim izborima](https://www.paragraf.rs/propisi/zakon_o_lokalnim_izborima.html)

### 1.1 Parlamentarni (Narodna skupština)

| Parametar | Vrednost |
|---|---|
| Broj mandata | **250** |
| Izborne jedinice | **1** — cela Republika Srbija |
| Sistem | Proporcionalni, **zatvorene liste** |
| Cenzus | **3 %** glasova birača koji su glasali |
| Manjinske liste | Učestvuju u raspodeli i ispod 3 % — **količnici se uvećavaju za 35 % (× 1,35)** |
| Metod raspodele | **D'Hondt** (sistem najvećeg količnika) |
| Dodela kandidatima | Redom sa liste, počev od prvog |
| Rodna kvota | Min. 40 % manje zastupljenog pola, po grupama od pet |

### 1.2 Lokalni (skupštine gradova i opština)

| Parametar | Vrednost |
|---|---|
| Jedinica | **145** jedinica lokalne samouprave |
| Broj odbornika | **19–90**, zavisi od broja stanovnika |
| Izborne jedinice | 1 po JLS |
| Sistem / cenzus / metod | Isto kao parlamentarni (proporcionalno, 3 %, D'Hondt, manjinski × 1,35) |

### 1.3 Pokrajinski (Skupština AP Vojvodine)

120 poslanika, proporcionalno, D'Hondt — tretira se kao „parlamentarni" tip sa
teritorijalnim opsegom = Vojvodina.

### 1.4 Predsednički

| Parametar | Vrednost |
|---|---|
| Sistem | Većinski, dva kruga |
| Prvi krug | Pobeđuje kandidat sa **> 50 %** glasova birača koji su glasali |
| Drugi krug | Dva najbolja, prosta većina, 15 dana kasnije |
| Mandati | Nema raspodele — pobednik nosi sve |

### 1.5 Orijentacione veličine (za kapacitetno planiranje)

- ~6,5 miliona birača u Jedinstvenom biračkom spisku
- ~8.200 biračkih mesta
- ~29 upravnih okruga, 145 JLS, ~4.700 naseljenih mesta
- Nivoi izborne administracije: **RIK** → **OIK/GIK** (145) → **birački odbor** (~8.200)

---

## 2. Generički domenski model

Ključna odluka: **izbor je konfigurabilan entitet**, a ne hardkodovan tip.
Sve razlike između parlamentarnih, lokalnih i predsedničkih izbora svode se na
podatke u tabeli `elections`, ne na granane `if`-ove u kodu.

```
Election
├── type            : parliamentary | local | provincial | presidential
├── seats           : 250 | 19..90 | 120 | null
├── allocation      : dhondt | majority_runoff
├── threshold_pct   : 3.00 | null
├── minority_coef   : 1.35 | null
├── rounds          : 1 | 2
└── scope           : Jedna ili više ElectionUnit (RS / AP Vojvodina / JLS)
```

Time isti kod servira:
- parlamentarne: 1 `ElectionUnit` (RS), 250 mandata, D'Hondt
- lokalne 2026: 145 `ElectionUnit` (po JLS), svaka sa svojim brojem mandata
- predsedničke: 1 `ElectionUnit`, `allocation=majority_runoff`, `rounds=2`

### 2.1 Teritorijalna hijerarhija

```
Country (RS)
 └── District        (29 upravnih okruga)   — samo za prikaz/mape
      └── Municipality (145 JLS)            — nivo OIK/GIK
           └── PollingStation (~8.200)      — nivo biračkog odbora
Diaspora                                    — DKP u inostranstvu
```

`ElectionUnit` je **ortogonalan** na ovu hijerarhiju: za parlamentarne izbore
jedna jedinica pokriva sve opštine; za lokalne, jedinica = jedna opština.
Veza je `election_unit_municipality` (many-to-many).

### 2.2 ERD

```
elections ──< election_units ──< election_unit_municipality >── municipalities
    │                                                              │
    │                                                              └──< polling_stations
    │
    ├──< submitters (podnosioci: stranke, koalicije, grupe građana)
    │        │
    │        └──< lists (izborne liste)  ──< candidates
    │                                          (redni_broj, ime, godište, zanimanje, pol)
    │
    ├──< protocols          (zapisnici biračkih odbora)  ──< protocol_items (glasovi po listi)
    │        │
    │        └──< protocol_scans   (skenirani PDF/JPG)
    │
    ├──< turnout_snapshots  (izlaznost po vremenskim presecima)
    │
    ├──< allocations        (izračunata raspodela mandata)
    │        └──< allocation_seats (mandat → lista → kandidat)
    │
    └──< snapshots          (objavljene verzije: ver / napkozi / szavossz)
```

---

## 3. Arhitektura

```
┌──────────────────── ZATVORENA ZONA ─────────────────────┐
│                                                          │
│  Birački odbor / OIK                                     │
│         │ unos zapisnika                                 │
│         ▼                                                │
│  ┌──────────────────┐     ┌──────────────────────────┐  │
│  │ Filament admin   │────▶│  PostgreSQL              │  │
│  │ (unos + kontrola)│     │  (izvor istine)          │  │
│  └──────────────────┘     └───────────┬──────────────┘  │
│                                        │                 │
│                            ┌───────────▼──────────────┐  │
│                            │ Laravel Queue (Horizon)  │  │
│                            │  · ValidateProtocol      │  │
│                            │  · AggregateResults      │  │
│                            │  · AllocateSeats (D'Hondt)│ │
│                            │  · PublishSnapshot       │  │
│                            └───────────┬──────────────┘  │
└────────────────────────────────────────┼─────────────────┘
                                         │ rsync / S3 PUT
                                         ▼
                            ┌────────────────────────────┐
                            │  Object storage + CDN      │
                            │  /data/{version}/{source}/ │
                            │  /data/config.json         │  ← jedini nekeširan
                            └────────────┬───────────────┘
                                         │ HTTPS GET
                                         ▼
                            ┌────────────────────────────┐
                            │  React SPA (Vite)          │
                            │  RTK Query + polling       │
                            └────────────────────────────┘
```

**Pravilo bez izuzetka:** javni frontend nikada ne razgovara sa Laravel-om.
Samo sa CDN-om. Na izbornoj noći backend može da padne, a sajt i dalje radi —
prikazivaće poslednji objavljen snapshot.

---

## 4. Pipeline objave

```
1. Unos zapisnika      →  protocols.status = 'entered'
2. Validacija          →  kontrolne sume; ako ne prolaze → 'flagged', ne ulazi u zbir
3. Verifikacija OIK    →  'verified'  ← tek sada ulazi u agregaciju
4. Agregacija          →  materijalizovani zbirovi po opštini / jedinici / državi
5. Raspodela mandata   →  D'Hondt nad verifikovanim zbirom
6. Generisanje JSON-a  →  svi fajlovi u /data/{MMDDHHmm}/szavossz/
7. Upload na CDN       →  immutable, Cache-Control: max-age=31536000, immutable
8. Atomski switch      →  config.json { "szavossz": "09151843" }
```

Koraci 4–8 se izvršavaju u jednoj `PublishSnapshot` komandi. Prekid u bilo kojoj
tački ne ostavlja sistem u nekonzistentnom stanju jer se `config.json`
menja tek na kraju.

**Frekvencija:** svakih 60–120 sekundi tokom izborne noći (cron/scheduler).

---

## 5. Kontrolne sume zapisnika

Iz mađarskog modela preuzimamo `szl_elteres` (odstupanje). Za srpski zapisnik
biračkog odbora, obavezne provere pre nego što zapisnik uđe u zbir:

```
K1  primljeni_listici = neupotrebljeni + upotrebljeni
K2  upotrebljeni_listici = broj_biraca_koji_su_glasali        (po izvodu)
K3  listici_u_kutiji = vazeci + nevazeci
K4  odstupanje = listici_u_kutiji − upotrebljeni_listici      → mora biti 0
K5  Σ glasovi_po_listama = vazeci_listici
K6  broj_biraca_koji_su_glasali ≤ upisano_biraca
K7  vazeci ≥ 0, nevazeci ≥ 0, sve stavke ≥ 0
```

`odstupanje ≠ 0` → status `flagged`, obavezna intervencija OIK, **ne ulazi u zbir**,
ali se **vidi javno** (kolona „zapisnici sa odstupanjem"). Transparentnost o
problemima je jača garancija poverenja od ćutanja.

---

## 6. D'Hondt sa manjinskim koeficijentom

```
ULAZ:  vazeci_glasovi po listi, ukupno_glasalo, seats, threshold_pct=3, minority_coef=1.35

1.  prag = ukupno_glasalo × 0.03
2.  kvalifikovane = liste gde (glasovi ≥ prag) ILI (is_minority = true)
3.  za svaku kvalifikovanu listu i svaki delilac d = 1..seats:
        kolicnik = glasovi / d
        ako je lista manjinska I glasovi < prag:
            kolicnik = kolicnik × 1.35
4.  sortiraj sve količnike opadajuće; uzmi prvih `seats`
5.  broj mandata liste = broj njenih količnika u prvih `seats`
6.  razrešenje izjednačenja: veći ukupan broj glasova; ako i dalje — žreb (evidentira se)
7.  mandati se dodeljuju kandidatima redom sa liste (1, 2, 3, …)
```

Implementacija u `App\Services\Allocation\DHondtAllocator` (vidi backend).

---

## 7. Snapshot fajlovi — srpski ekvivalenti

| Mađarski | IZBORI.RS | Izvor | Sadržaj |
|---|---|---|---|
| `config.json` | `config.json` | — | `{registry, turnout, results}` |
| `Valleir.json` | `election.json` | registry | Opis izbora |
| `Kodtablak.json` | `codebooks.json` | registry | Svi šifarnici, ravna tabela |
| `Megyek.json` | `districts.json` | registry | 29 okruga |
| `OevkAdatok.json` | `units.json` | registry | Izborne jedinice |
| `Telepulesek.json` | `municipalities.json` | registry | 145 JLS |
| `Szavazokorok-*.json` | `{dist}/stations-{dist}-{muni}.json` | registry | Biračka mesta |
| `Szervezetek.json` | `submitters.json` | registry | Podnosioci |
| `ListakEsJeloltek.json` | `lists.json` | registry | Liste + kandidati |
| `Hataridok.json` | `deadlines.json` | registry | Rokovi |
| `ReszvetelOrszag.json` | `turnout-country.json` | turnout | Izlaznost, državni nivo |
| `ReszvetelMegye.json` | `turnout-districts.json` | turnout | Po okruzima |
| `ReszvetelTelep-*.json` | `{dist}/turnout-{dist}-{muni}.json` | turnout | Po opštini |
| `ListasJkv.json` | `results-country.json` | results | Zbirni rezultati |
| `Patko.json` | `composition.json` | results | Sastav skupštine |
| `DHondtMatrix.json` | `dhondt-matrix.json` | results | Tabela količnika |
| `SzavkorJkv-*.json` | `{dist}/protocols-{dist}-{muni}.json` | results | **Zapisnici po BM** |
| `SzeredmTelep-*.json` | `{dist}/results-{dist}-{muni}.json` | results | Zbir po opštini |
| `OevkElsok.json` | `winners.json` | results | Pobednici (lokalni/predsednički) |
| `SzorosVerseny.json` | `close-races.json` | results | Tesne trke |
| `publicated.json` | `scans.json` | results | Skenirani zapisnici |

### Envelope (obavezan, svaki fajl)
```json
{
  "meta": {
    "generated":   "2026-09-15T18:43:00+02:00",
    "electionDate":"2026-04-12",
    "electionId":  1,
    "electionType":"parliamentary",
    "source":      "results",
    "version":     "09151843",
    "processed":   87.34
  },
  "list": [ … ]
}
```
`processed` (= mađarski `feldar`) — procenat obrađenih biračkih mesta. Mora
postojati na **svakom** agregatu, jer bez njega broj bez konteksta obmanjuje.

---

## 8. Rute frontenda

```
/                                       Naslovna — presek rezultata + izlaznost
/skupstina                              Sastav skupštine (potkovica)
/liste                                  Izborne liste + rezultati
/liste/:listId                          Detalj liste: kandidati, mandati, geo-raspodela
/kandidati/:candidateId                 Detalj kandidata
/podnosioci                             Podnosioci lista
/podnosioci/:submitterId                Detalj podnosioca
/izlaznost                              Izlaznost po presecima + mapa
/teritorija                             Pregled po okruzima
/teritorija/:districtId                 Okrug → opštine
/teritorija/:districtId/:municipalityId Opština → biračka mesta
/biracko-mesto/:stationId               Zapisnik + skenirani dokument
/mandati                                D'Hondt matrica — korak po korak
/zapisnici                              Pretraga zapisnika, filter „sa odstupanjem"
/gde-glasam                             Pretraga biračkog mesta po adresi
/rokovi                                 Izborni kalendar
/o-podacima                             Metodologija, otvoreni podaci, API
```

**Jezici:** `sr-Cyrl` (podrazumevan), `sr-Latn`, `en`, `hu` (Vojvodina), `sq`.
Transliteracija ćirilica↔latinica se radi na klijentu — ne dupliraju se fajlovi.

---

## 9. Kapacitetno planiranje

| Metrika | Procena |
|---|---|
| Zapisnika po izbornoj noći | ~8.200 (parl.) / ~8.200 × 2 (parl. + lok. isti dan) |
| Veličina `protocols-{dist}-{muni}.json` | 15–60 KB (prosečna opština: 56 BM) |
| Ukupno po snapshot-u | ~25 MB nekomprimovano, ~4 MB gzip |
| Broj snapshot-ova izborne noći | ~300 (na 2 min, 10 sati) |
| Ukupan storage po ciklusu | ~7 GB — trivijalno |
| Vršni saobraćaj | 100k–500k istovremenih korisnika → **samo CDN**, origin ~0 rps |
| Vreme generisanja snapshot-a | Cilj < 20 s za kompletan set |

**Zaključak:** origin infrastruktura je mala (jedan Postgres + par worker-a).
Sav teret nosi CDN. Ovo je jedini razlog zašto mađarski sistem izdrži izbornu noć.

---

## 10. Bezbednost i poverenje

1. **Razdvojene mreže.** Sistem za unos nije dostupan sa interneta; publikacija
   ide jednosmerno (push na CDN), nikada pull.
2. **Immutable snapshot-ovi + hash lanac.** Svaki snapshot dobija SHA-256
   `manifest.json`; svaki manifest sadrži hash prethodnog → dokaziv redosled.
3. **Skenirani zapisnici javno.** Za svaki zapisnik se objavljuje potpisan
   skenirani original. Ovo je najjača mera protiv osporavanja rezultata.
4. **Audit log na svaku izmenu zapisnika** — ko, kada, sa koje vrednosti na koju.
   Javno dostupan broj izmena po zapisniku.
5. **Otvoreni podaci.** Svi snapshot-ovi ostaju trajno dostupni + CSV eksport.
   Treće strane (CRTA, CeSID, mediji) mogu nezavisno da reprodukuju raspodelu.
6. **Read-only javni sloj.** Nema forme, nema autentikacije, nema kolačića na
   javnom sajtu → napadna površina praktično nula.
7. **Bez third-party skripti.** Self-hosted analitika, self-hosted fontovi,
   MapLibre umesto Google Maps.
