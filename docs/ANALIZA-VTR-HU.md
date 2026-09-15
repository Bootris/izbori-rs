# Analiza: `vtr.valasztas.hu/ogy2026` — reverse engineering

> Rezultat skeniranja žive aplikacije (Nemzeti Választási Iroda, Mađarska,
> parlamentarni izbori 2026). Verzija aplikacije: `1.68.314`.

---

## 1. Čemu platforma služi — potvrda

**VTR = Választás Tájékoztató Rendszer** (Sistem za informisanje o izborima).

Tvoja pretpostavka je **tačna, ali nepotpuna**. Platforma nije *sistem za brojanje*
— ona je **javni prikaz rezultata brojanja u realnom vremenu**. Razlika je ključna
za arhitekturu koju gradiš:

| Sloj | Šta radi | Da li je javno |
|---|---|---|
| **Unos** (zatvoreni sistem) | Biračke odbore na 10.000+ biračkih mesta unose zapisnik; OIK/RIK operateri unose u interni sistem | Ne |
| **Obrada** (batch engine) | Validacija, agregacija po nivoima, D'Hondt raspodela mandata | Ne |
| **Publikacija** (ovo što vidiš) | Statički JSON snapshot-ovi na CDN-u + SPA koji ih čita | **Da** |

Platforma pokriva **ceo životni ciklus izbora**, ne samo izbornu noć:

1. **Pre izbora** (`ver` izvor) — registar: izborne jedinice, biračka mesta,
   birački spiskovi (brojevi), kandidati, liste, podnosioci lista, rokovi,
   diplomatsko-konzularna predstavništva, pretraga „gde glasam".
2. **Na dan izbora** (`napkozi` izvor) — izlaznost u 7 vremenskih preseka
   (07:00, 09:00, 11:00, 13:00, 15:00, 17:00, 19:00), na nivou države, okruga,
   izborne jedinice i opštine.
3. **Izborna noć i posle** (`szavossz` izvor) — rezultati po biračkom mestu →
   opštini → izbornoj jedinici → državi; raspodela mandata; sastav parlamenta;
   „tesne trke"; D'Hondt matrica; skenirani zapisnici.

**Zašto se rezultat dobije za par sati:** ne zato što je brojanje brzo, nego zato
što je *pipeline* razdvojen. Unos je distribuiran (svako biračko mesto unosi svoj
zapisnik), a agregacija je čisto računska operacija nad već unetim brojevima.
Javni sajt ne računa ništa — on samo prikazuje fajlove koje je backend već
izračunao i objavio.

---

## 2. Tehnološki stack (utvrđen skeniranjem)

### Frontend
```
Vite (build)                    → /ogy2026/static/assets/index-base-<hash>.js  (899 KB)
                                → /ogy2026/static/assets/vendor-<hash>.js
                                → /ogy2026/static/assets/index-base-<hash>.css
React                           → o.createElement pozivi u bundle-u
Redux Toolkit Query             → createApi({ reducerPath:"api", baseQuery: fetchBaseQuery({baseUrl}) })
react-router                    → client-side rute, history API
Bootstrap 5                     → klase: d-md-flex, form-select, list-group, rounded-12…
react-i18next                   → hu/en, zaseban chunk en-<hash>.js
Google Maps JS API              → "google-map-script", "google-maps-wrapper" (pretraga biračkih mesta)
TopoJSON                        → Telep-Topo-*.json, Szavkor-Topo-*.json, OevkPoligonok.json
Inter Variable font             → lokalno hostovan .ttf
Google Tag Manager / GA4        → gtm.js, gtag/js, g/collect
```

**Kritično zapažanje:** frontend je **100% statički**. Nema server-side rendering-a,
nema aplikativnog API-ja. Sve što aplikacija zna dolazi iz statičkih `.json` fajlova.

### Podaci
```
Base URL:   https://vtr.valasztas.hu/ogy2026/data
Slike:      https://static.valasztas.hu/dyn/jkvimage/2026/OGY   (skenirani zapisnici)
Vesti:      https://static.valasztas.hu/dyn/kiemelt_hirek
Test okruženje: static-teszt.valasztas.hu  (paralelno, isti oblik)
```

---

## 3. Versioning model — **najvažniji deo za kopiranje**

Ovo je srce arhitekture. Ceo sistem stoji na jednom fajlu:

```
GET /ogy2026/data/config.json
```
```json
{ "ver": "04112100", "napkozi": "04121855", "szavossz": "05071600" }
```

Format verzije: **`MMDDHHmm`** (mesec, dan, sat, minut objave snapshot-a).

Tri nezavisna „izvora" (`source`), svaki sa sopstvenom verzijom:

| Izvor | Značenje | Sadržaj | Frekvencija osvežavanja |
|---|---|---|---|
| `ver` | *verifikovani registar* | Izborne jedinice, biračka mesta, kandidati, liste, geometrija, birački spiskovi | Retko — nekoliko puta pre izbora |
| `napkozi` | *„u toku dana"* | Izlaznost po vremenskim presecima | Svaka 2 sata na dan izbora |
| `szavossz` | *zbir glasova* | Rezultati, mandati, zapisnici | Svakih par minuta na izbornoj noći |

Svi data URL-ovi su oblika:

```
/{version}/{source}/{Fajl}.json
/{version}/{source}/{maz}/{Fajl}-{maz}-{taz}.json
```

**Zašto je ovo genijalno:**

- Snapshot je **immutable**. Jednom objavljen `05071600/` folder se nikad ne menja.
- **CDN caching je trivijalan** — svaki fajl ima `Cache-Control: immutable`, jedini
  fajl koji se ne kešira je `config.json` (nekoliko desetina bajtova).
- **Rollback je `git revert` nad jednim brojem** — ako se objavi pogrešan snapshot,
  vraćaš `config.json` na prethodnu verziju.
- **Nema baze u kritičnoj putanji.** Na izbornoj noći kad sajt ima milione poseta,
  backend ne prima nijedan zahtev. Skalira do beskonačnosti sa običnim S3+CDN.
- **Auditabilnost** — svaka objavljena verzija ostaje trajno dostupna, može se
  dokazati šta je sajt prikazivao u kom trenutku.

Klijent radi polling `config.json` (nekeširan), i kad se broj promeni — RTK Query
invalidira keš i povlači nove fajlove.

---

## 4. Envelope — jedinstven omot svakog fajla

Svaki JSON fajl ima identičnu strukturu:

```json
{
  "PvOnHeader": {
    "generated": "2026-05-07T16:00:00",
    "val_dat":   "2026-04-12",
    "vl_id":     1,
    "nvv_id":    3
  },
  "list": [ … ]      // ILI "data": { … }  za pojedinačne objekte
}
```

- `generated` — trenutak generisanja snapshot-a (prikazuje se kao „Podaci ažurirani u…")
- `val_dat` — datum izbora
- `vl_id` / `nvv_id` — interni identifikatori izbora i izborne procedure

Kolekcije koriste ključ `list`, pojedinačni objekti ključ `data`. Konzistentno,
bez izuzetka. **Kopiraj ovo.**

---

## 5. Kompletan katalog: 45 RTK Query endpointa

### 5.1 Sistem / meta

| Endpoint | Putanja | Namena |
|---|---|---|
| `getConfig` | `/config.json` | Trenutne verzije po izvoru |
| `getDescription` | `/{ver}/ver/Valleir.json` | Opis izbora (tip, datum) |
| `getCodeTables` | `/{ver}/{res}/Kodtablak{En}.json` | Šifarnici (418 unosa) |
| `getDaytimeCodeTables` | `/{ver}/{src}/Kodtablak.json` | Šifarnici po izvoru |
| `getDeadlines` | `{ver}/ver/Hataridok.json` | Izborni rokovi |
| `getStatistics` | `/{ver}/ver/VerStat.json` | Statistika registracije |
| `getNotification` | `/{ver}/{src}/Rkesem.json` | Obaveštenja / događaji |

### 5.2 Teritorijalna struktura

| Endpoint | Putanja | Namena |
|---|---|---|
| `getCounties` | `/{ver}/ver/Megyek.json` | Okruzi (20) + birački spisak |
| `getConstituencies` | `/{ver}/ver/OevkAdatok.json` | Izborne jedinice (106) |
| `getLocalities` | `/{ver}/ver/Telepulesek.json` | Opštine (3177) |
| `getPollingStations` | `/{ver}/ver/{maz}/Szavazokorok-{maz}-{taz}.json` | Biračka mesta u opštini |
| `getForeignRepresentations` | `/{ver}/ver/Kulkepviselet.json` | DKP u inostranstvu |

### 5.3 Geometrija (mape)

| Endpoint | Putanja |
|---|---|
| `getConstituencyTopologies` | `/{ver}/ver/OevkPoligonok.json` |
| `getLocalityTopology` | `/{ver}/ver/{maz}/Telep-Topo-{maz}.json` |
| `getPollingStationTopology` | `/{ver}/ver/{maz}/Szavkor-Topo-{maz}-{taz}.json` |
| `getPollingStationBoundaries` | `/{ver}/ver/{maz}/Korzethatar-{maz}-{taz}.json` |
| `getPollingStationStreets` | `/{ver}/ver/{maz}/SzavkorKereso-{maz}-{taz}.json` |

### 5.4 Kandidati i liste

| Endpoint | Putanja |
|---|---|
| `getOrganizations` | `/{ver}/ver/Szervezetek.json` |
| `getNominatingGroups` | `/{ver}/ver/Jlcs.json` |
| `getLists` | `/{ver}/ver/ListakEsJeloltek.json` |
| `getIndividuals` | `/{ver}/ver/EgyeniJeloltek.json` |
| `getVoterCounts` | `/{ver}/ver/OsszLetszam.json` |

### 5.5 Izlaznost (dan izbora)

| Endpoint | Putanja |
|---|---|
| `getByCountry` | `/{v}/{src}/ReszvetelOrszag.json` |
| `getByCounty` | `/{v}/{src}/ReszvetelMegye.json` |
| `getByConstituencies` | `/{v}/{src}/ReszvetelOevk.json` |
| `getByLocality` | `/{v}/{src}/{maz}/ReszvetelTelep-{maz}-{taz}.json` |
| `getByForeignRepresentation` | `/{v}/szavossz/ReszvetelKulkepv.json` |

### 5.6 Rezultati

| Endpoint | Putanja | Nivo |
|---|---|---|
| `getRepresentatives` | `/{v}/szavossz/OevkJkv.json` | Izborna jedinica |
| `getWinners` | `/{v}/szavossz/OevkElsok.json` | Pobednici po IJ |
| `getCloseContest` | `/{v}/szavossz/SzorosVerseny.json` | Tesne trke |
| `getListResults` | `/{v}/szavossz/ListasJkv.json` | Državne liste |
| `getComposition` | `/{v}/szavossz/Patko.json` | Sastav parlamenta |
| `getOrganizationResults` | `/{v}/szavossz/SzervezetekEredmenye.json` | Po strankama |
| `getDHondMatrix` | `/{v}/szavossz/DHondtMatrix.json` | D'Hondt tabela |
| `getHatarszamEredmenye` | `/{v}/szavossz/HatarszamEredmenye.json` | Rezultat cenzusa |
| `getPreviousResults` | `/{v}/ver/ElozoOevkEredmenyek.json` | Prethodni ciklus |
| `getPollingStationResults` | `/{v}/szavossz/{maz}/SzavkorJkv-{maz}-{taz}.json` | **Biračko mesto** |
| `getPollingStationFinalResults` | `/{v}/szavossz/{maz}/ReszvetelTelepSzavkor-{maz}-{taz}.json` | Izlaznost po BM |
| `getPollingStationElectionResults` | `/{v}/szavossz/{maz}/SzeredmTelep-{maz}-{taz}.json` | Zbir po opštini |

### 5.7 Pismo / glasanje poštom (specifično za Mađarsku)

`getPostalBallotResults`, `getPostalBallotVotes`, `getPostalBallotAddresses`,
`getPostalBallotAddressesByResidence`, `getPostalBallotPackages`

### 5.8 Skenirani zapisnici — sloj poverenja

Odvojen mehanizam (ne RTK Query nego `queryFn`):

```
/{protocol}/{maz}/{evk}/{taz}/{sorszam}/publicated.json   → lista fajlova
https://static.valasztas.hu/dyn/jkvimage/2026/OGY/{protocol}/{maz}/{evk}/{taz}/{sorszam}/{file}
```
`protocol` ∈ `OLTR` (glasanje poštom), `KUV` (inostranstvo), i domaći tipovi.

**Ovo je najvažnija funkcija za poverenje u sistem.** Svako može da otvori skenirani,
potpisani zapisnik biračkog odbora i uporedi ga sa brojevima na sajtu.

---

## 6. Šeme podataka (uzorci iz žive aplikacije)

### Okrug (`Megyek.json`)
```json
{ "leiro": { "maz":"01", "nev":"…", "rovid_nev":"…", "nevi":"…", "nevi_en":"…",
             "megye_poligon":"…", "centrum":"…" },
  "letszam": { "indulo":19686, "honos":18032, "atjel":1638,
               "atjelInnen":1487, "kuvi":807, "osszesen":19670 } }
```

### Opština (`Telepulesek.json`)
```json
{ "leiro": { "maz":"01", "taz":"001", "megnev":"Budapest 01. kerület",
             "megnev_en":"…", "evk_lst":["01"], "szk_db":16 },
  "letszam": { … } }
```

### Biračko mesto (`Szavazokorok-01-001.json`)
```json
{ "leiro": { "sorszam":"001", "szk_nev":"…", "evk":"01", "evk_nev":"…",
             "cim":"Budapest 01", "kozter":"Úri utca 38. (…)",
             "akadaly":"I",        // pristupačnost
             "szamlKijelolt":"N",  // određeno za brojanje
             "atjKijelolt":"N",    // određeno za prijavljene birače
             "telepSzintu":"N" },
  "letszam": { "indulo":1275, "honos":1156, "atjel":0, "atjelInnen":109, "osszesen":1156 } }
```

### **Zapisnik biračkog mesta** (`SzavkorJkv-01-001.json`) — jezgro sistema
```json
{ "maz":"01", "taz":"001", "sorsz":"001", "feldar": 100,
  "egyeni_jkv": {                          // većinski deo
    "allapot":"…",                          // status obrade
    "vp_osszes": 1156,                      // ukupno birača
    "szavazott_osszesen": 1001,
    "szavazott_osszesen_szaz": 86.59,
    "vp_lakcim_szerint": …, "megjel_lakcim_szerint": …,
    "vp_atjel": …, "megjel_atjel": …,
    "szl_belyegtlen_urna": …,               // listići bez pečata u kutiji
    "szl_belyegzett_urna": …,               // listići sa pečatom
    "szl_elteres": …,                       // ODSTUPANJE (kontrolna suma!)
    "szl_ervenytelen": …, "szl_ervenytelen_szaz": …,
    "szl_ervenyes": …,   "szl_ervenyes_szaz": …,
    "ujra_szamol":"N",                      // zahtevano ponovno brojanje
    "tetelek": [ { "szavlap_sorsz":6, "ej_id":140603,
                   "szavazat":549, "szavazat_szaz":54.85 } ]
  },
  "listas_jkv": { … }                       // proporcionalni deo, ista struktura
}
```

### Rezultat izborne jedinice (`OevkJkv.json`)
```json
{ "maz":"01", "evk":"01",
  "egyeni_jkv": {
    "feldar": 100,                    // procenat obrade
    "jogeros":"…",                    // pravnosnažno
    "feldolg_norm_szk_db": 16,        // obrađenih biračkih mesta
    "norm_szk_db": 16,                // ukupno
    "feldolg_szaml_szk_db": …,
    "ujraszam_erintett":"N",
    "eredm":"…",
    "vp_belf_njben": …, "vp_atjel": …, "vp_kulkepv": …, "vp_osszes": …,
    "szavkorben_megjelent": …, "megjel_kuvi_atjel": …,
    "szavazott_osszesen": …, "szavazott_osszesen_szaz": …,
    "szl_urna_boritek": …, "szl_ervenytelen": …, "szl_ervenyes": …,
    "tetelek": [ { "szavlap_sorsz":6, "ej_id":140603,
                   "szavazat":37803, "szavazat_szaz":63.05,
                   "mandatum":1 } ]
  } }
```

### Sastav parlamenta (`Patko.json` — „patkó" = potkovica)
```json
{ "feldar":100, "mand_listas":93, "mand_evk":106, "szoszolok":…,
  "mand_ossz":199, "mand_kioszt":199, "mand_ures":0,
  "vp_njben":…, "vp_megjelent":…, "vp_osszes":…, "vp_szavazott":…,
  "eredmenyek": [ { "jlcs_kod":1010, "mand_egyeni":96, "mand_egyeni_szaz":…,
                    "mand_listas":45, "mand_listas_szaz":…,
                    "mand_ossz":141, "mand_ossz_szaz":… } ],
  "mandatumok": [ { "jlcs_kod":1010, "mand_tip":"E|L",
                    "maz":"01", "evk":"03", "ej_id":140603 } ]   // 211 mandata
}
```

### Izlaznost po presecima (`ReszvetelOrszag.json`)
```json
{ "list": [ { "jelido":"07:00", "valp": 7800000, "megj": 412000, "szavazokorok":[] } ] }
```
7 elemenata = 7 vremenskih preseka tokom dana.

### Šifarnici (`Kodtablak.json`)
```json
{ "list": [ { "tabla":"ALLAPOT", "kod":"0", "megnev":"Bejelentve" } ] }   // 418 unosa
```
Jedna ravna tabela sa `tabla` diskriminatorom — sve enumeracije na jednom mestu,
prevodive, bez hardkodovanja u frontendu. **Kopiraj ovaj obrazac.**

---

## 7. Rute frontenda

```
/ogy2026                                       Početna (sažetak rezultata)
/ogy2026/orszaggyules-osszetetele               Sastav parlamenta (potkovica)
/ogy2026/orszagos-listak                        Državne liste
/ogy2026/orszagos-listak/:jlcsKod               Detalj liste + kandidati
/ogy2026/egyeni-valasztokeruletek?filter=…      Izborne jedinice (pregled)
/ogy2026/egyeni-valasztokeruletek/:maz/:evk     Detalj izborne jedinice
/ogy2026/reszveteli-adatok                      Izlaznost
/ogy2026/jelolo-szervezetek                     Podnosioci lista
/ogy2026/jelolo-szervezetek/:szkod              Detalj podnosioca
/ogy2026/jelolo-szervezetek/jeloltek/:ejId      Detalj kandidata
/ogy2026/valasztopolgaroknak/hataridok          Rokovi
/ogy2026/valasztopolgaroknak/levelszavazas      Glasanje poštom
/ogy2026/valasztopolgaroknak/kulkepviseletek-listaja   DKP
```

Zapaziti: `/nyito` je zajednički „rozetni" ulaz sa linkovima na `/ogy2026`,
`/onk2024`, `/nemz2024`, `/ep2024` — **svaki izborni ciklus je zasebna
deployovana instanca iste aplikacije**, arhivirana zauvek.

---

## 8. Šta preuzeti, a šta ne

### ✅ Preuzeti bez razmišljanja
1. **Immutable versioned snapshot model** + `config.json` pointer.
2. **Jedinstven envelope** (`PvOnHeader` + `list`/`data`).
3. **Razdvajanje tri izvora** (registar / izlaznost / rezultati).
4. **Jedna ravna tabela šifarnika** sa `tabla` diskriminatorom.
5. **Hijerarhijsko sečenje fajlova** — `{maz}/{Fajl}-{maz}-{taz}.json`; nikad
   jedan ogroman fajl. Kod 8.200 biračkih mesta u Srbiji, fajl po opštini je ~30 KB.
6. **Skenirani zapisnici** kao javno dostupan dokaz.
7. **`feldar` (procenat obrade) na svakom agregatu** — korisnik uvek zna
   koliko je podataka stiglo.
8. **Kontrolna suma `szl_elteres`** (odstupanje) u zapisniku — automatska detekcija
   nekonzistentnih zapisnika.
9. **TopoJSON umesto GeoJSON** — 5–10× manji fajlovi za mape.
10. **Arhiviranje ciklusa** kao zasebnih deploy-eva.

### ❌ Ne preuzimati
- **Mađarski izborni sistem** — mešoviti (106 jednomandatnih + 93 lista).
  Srbija ima čist proporcionalni sistem sa jednom izbornom jedinicom.
- **`jelolo_csoport` / `jlcs` konstrukcija** — mađarska specifičnost za koalicije.
- **Glasanje poštom** — ne postoji u srpskom sistemu.
- **Mađarski nazivi polja** — koristiti srpske/engleske.
- **Google Analytics** — za javni izborni sistem koristi self-hosted (Plausible/Umami).
- **Google Maps** — koristi MapLibre GL + OpenStreetMap; nema vendor lock-in-a
  ni slanja podataka trećoj strani.
