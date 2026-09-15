# Deploy — IZBORI.RS

Produkcijska topologija iz [IZBORNI-SISTEM.md §3](IZBORNI-SISTEM.md): zatvorena zona
(Laravel + Filament + PostgreSQL + worker) koja jednosmerno gura statičke JSON
snapshot-ove na javni sloj (object storage + CDN). Javni frontend nikad ne razgovara
sa Laravel-om — na izbornoj noći origin može da padne, sajt i dalje prikazuje
poslednji objavljen snapshot.

```
[OIK/RIK operateri] ──HTTPS──▶ admin.izbori.rs  (nginx → php-fpm, samo VPN/allow-list)
                                    │ PostgreSQL · queue worker · scheduler
                                    │ izbori:publish  →  public/data/… (lokalni disk)
                                    │                 →  rsync / S3 sync
                                    ▼
                          CDN / object storage  ◀──HTTPS── građani, mediji, posmatrači
                          /data/index.json, /data/{izbor}/config.json     (no-cache)
                          /data/{izbor}/{MMDDHHmm}/{izvor}/…              (immutable)
```

## 1. Preduslovi

| Komponenta | Verzija | Napomena |
|---|---|---|
| PHP | 8.2+ | ekstenzije: `pdo_pgsql`, `intl`, `mbstring`, `gd`, `zip`, `bcmath` |
| PostgreSQL | 15+ | jedna baza, jedan korisnik; SQLite je samo za lokalni razvoj |
| Node | 22 | samo za build (`npm run build`), ne treba u runtime-u |
| nginx | 1.24+ | dva vhost-a: admin (privatni) i javni (statika) |
| Supervisor / systemd | — | `queue:work` + `schedule:work` |

## 2. .env za produkciju

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://admin.izbori.rs          # URL admin origina (Filament, upload skenova)
APP_LOCALE=sr

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=izbori
DB_USERNAME=izbori
DB_PASSWORD=…

ADMIN_PATH=rik-<nasumično>               # nikad /admin u produkciji
SEED_ADMIN_EMAIL=…                       # promeni lozinku odmah posle prvog logina

SNAPSHOT_DISK=snapshots                  # lokalni public/data (vidi §4 za S3)
SNAPSHOT_PUBLIC_URL=https://izbori.rs/data   # odakle SPA čita — CDN, ne origin
SNAPSHOT_KEEP_VERSIONS=0                 # 0 = čuvaj sve verzije (auditabilnost)
PUBLISH_INTERVAL_MINUTES=2

QUEUE_CONNECTION=database                # ili redis
CACHE_STORE=database
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
```

Nikad ne komituj `.env`; nova polja idu u `.env.example` bez vrednosti.

## 3. Prvo podizanje

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force                 # admin nalog + podešavanja
php artisan storage:link                    # skenovi zapisnika (storage/app/public)
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan filament:optimize

# registar (CSV iz RIK-a → okruzi, opštine, biračka mesta)
php artisan izbori:import-stations parlament-2026 /path/biracka-mesta.csv
# liste, kandidati, rokovi → kroz admin
php artisan izbori:publish parlament-2026 --source=registry
```

Posle svake promene `.env`/config fajlova: `php artisan config:cache` (config je keširan).

## 4. Objava snapshot-ova

Publisher piše na disk `snapshots` (`config/filesystems.php`, podrazumevano
`public/data`). Dve opcije za javni sloj:

**A. Isti server + CDN ispred** (jednostavno, dovoljno za većinu):
javni vhost servira `public/data` kao statiku, CDN (Cloudflare/BunnyCDN) kešira.

**B. Object storage** (preporučeno za izbornu noć): u `config/filesystems.php`
promeni `snapshots` disk na `s3` driver (S3/MinIO/R2), `SNAPSHOT_PUBLIC_URL` na
CDN domen. `putAtomic()` radi write-then-rename i na S3 (upload objekta je atomski).

Cache pravila su ugovor sa CDN-om (§5). Ako CDN ne poštuje `Cache-Control` iz origina,
podesi mu pravila ručno: `*/config.json` i `index.json` = **no-cache**, sve ostalo
pod `/data/*/[0-9]*/` = **immutable, 1 godina**.

## 5. nginx

### Javni vhost (izbori.rs) — samo statika

```nginx
server {
    listen 443 ssl http2;
    server_name izbori.rs;
    root /var/www/izbori/public;

    # SPA shell: sve rute koje nisu fajl idu na index.html (build iz Laravel-a),
    # ili na php-fpm ako želiš da Laravel servira shell (route 'spa').
    location / {
        try_files $uri /index.php?$query_string;
    }

    # Pointer fajlovi — NIKAD keširani
    location ~ ^/data/(index\.json|[^/]+/config\.json)$ {
        add_header Cache-Control "no-cache, no-store, must-revalidate" always;
        add_header Access-Control-Allow-Origin "*" always;
        types { application/json json; }
        default_type application/json;
    }

    # Snapshot fajlovi — immutable (folder se nikad ne menja posle objave)
    location ~ ^/data/[^/]+/[0-9]{8,10}/ {
        add_header Cache-Control "public, max-age=31536000, immutable" always;
        add_header Access-Control-Allow-Origin "*" always;
        gzip_static on;
        types { application/json json; }
        default_type application/json;
    }

    # Skenirani zapisnici (storage:link → public/storage)
    location /storage/ {
        add_header Cache-Control "public, max-age=86400" always;
    }

    # Admin NE postoji na javnom vhost-u
    location ~ ^/(rik-[a-z0-9]+|livewire|filament) { return 404; }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
}
```

### Bez rewrite pravila (čist object storage)

Blok `location /` gore postoji da bi svaka putanja vratila `index.html`, jer SPA
rutira kroz putanju. Ako se javni sloj servira sa object storage-a koji to ne
ume, uključi rutiranje kroz fragment URL-a u `window.__IZBORI__`:

```js
window.__IZBORI__ = {
    dataUrl: 'data',      // relativno, pa radi i u podfolderu
    hashRouting: true,    // /izlaznost postaje /#/izlaznost
    pollSeconds: 60
};
```

Sve ostalo ostaje isto: isti bundle, isti snapshot fajlovi, ista pravila keširanja.

`gzip_static on` očekuje `.json.gz` pored fajlova — generiši ih posle objave
(`find public/data -name '*.json' -newer … -exec gzip -k9 {} +`) ili prepusti CDN-u.

### Admin vhost (admin.izbori.rs) — samo iz zatvorene mreže

```nginx
server {
    listen 443 ssl http2;
    server_name admin.izbori.rs;
    root /var/www/izbori/public;

    allow 10.0.0.0/8;      # VPN / mreža RIK-a i OIK-ova
    deny all;

    client_max_body_size 20m;   # skenovi zapisnika (FileUpload maxSize 10 MB)

    location / { try_files $uri /index.php?$query_string; }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
}
```

## 6. Worker i scheduler

Automatska objava (`routes/console.php`) radi samo dok je izbor u statusu
**Brojanje** (rezultati) ili **Glasanje** (izlaznost). Bez ova dva procesa ništa se
ne objavljuje automatski — ručna objava iz admina radi i bez njih.

```ini
; /etc/supervisor/conf.d/izbori.conf
[program:izbori-queue]
command=php /var/www/izbori/artisan queue:work --tries=1 --timeout=300 --sleep=3
user=www-data
autostart=true
autorestart=true
stopwaitsecs=320

[program:izbori-schedule]
command=php /var/www/izbori/artisan schedule:work
user=www-data
autostart=true
autorestart=true
```

`PublishSnapshotJob` je `ShouldBeUnique` po (izbor, izvor) — dva istovremena
objavljivanja istog izvora se ne mogu preklopiti.

## 7. Izborna noć — kontrolna lista

1. `izbori:publish … --source=registry` objavljen dan ranije; status izbora = **Glasanje**.
2. U 20:00 status → **Brojanje** (Admin → Izbori). Od tog trenutka scheduler
   republikuje rezultate na svakih `PUBLISH_INTERVAL_MINUTES`.
3. Dashboard: „Sa odstupanjem" mora da teži nuli — svaki flagged zapisnik čeka
   intervenciju OIK-a i **ne ulazi u zbir**, ali se javno vidi u `flagged.json`.
4. Kad RIK utvrdi konačne rezultate: status → **Konačni rezultati**, poslednja
   ručna objava sva tri izvora. Automatska objava se time gasi.
5. `SNAPSHOT_KEEP_VERSIONS=0` — sve verzije ostaju; ~300 snapshot-ova × ~5 MB gzip
   po izbornoj noći je trivijalno.

## 8. Integritet i auditabilnost

Svaki snapshot ima `manifest.json` sa SHA-256 svakog fajla i hash-om koji
uključuje hash prethodne verzije (lanac). Bilo ko može da proveri kopiju sa CDN-a:

```bash
scripts/verify-snapshot.sh /mnt/cdn-mirror/parlament-2026/12132145/results
```

Preporuka: po objavi konačnih rezultata, hash poslednjeg manifesta objaviti i van
sistema (službeni glasnik, saopštenje, potpisan PDF) — time je lanac usidren.

## 9. Backup

- PostgreSQL: `pg_dump` na sat tokom izborne noći, dnevno inače; `wal-g`/`pgBackRest`
  za PITR ako je moguće.
- `public/data` (ili S3 bucket): versioning uključen; snapshot-ovi su immutable pa je
  `rsync --ignore-existing` dovoljan za ogledalo.
- `storage/app/public/scans`: skenirani zapisnici — trajni dokaz, backup obavezan.

## 10. Bezbednost (sažetak iz spec §10)

- Admin origin nije dostupan sa interneta (VPN/allow-list); `ADMIN_PATH` nasumičan.
- Javni sloj je read-only statika: nema formi, kolačića, autentikacije.
- Uloge: `admin` (RIK), `verifier` (OIK — verifikuje samo svoju opštinu), `operator`
  (unos samo za svoju opštinu). Verifikacija menja status i upisuje audit zapis
  (`protocol_revisions`: ko, kada, sa koje vrednosti na koju).
- Bez third-party skripti na javnom sajtu; fontovi i analitika self-hosted.
