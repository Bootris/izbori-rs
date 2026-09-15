#!/usr/bin/env bash
#
# Provera da li backend radi (isti obrazac kao site-core).
#
#   ./check-backend.sh          → preduslovi, baza, HTTP rute, objavljeni snapshot
#   ./check-backend.sh --full   → + kompletan test suite (php artisan test)
#
# Podiže privremeni server na portu CHECK_PORT (8123), proveri rute i ugasi ga.
# Izlazni kod je 0 ako je sve u redu (pogodno za CI).
#
set -u
cd "$(dirname "$0")"

PORT="${CHECK_PORT:-8123}"
BASE="http://127.0.0.1:${PORT}"
PASS=0
FAIL=0

ok()  { printf '  \033[32m✔\033[0m %s\n' "$1"; PASS=$((PASS + 1)); }
bad() { printf '  \033[31m✘\033[0m %s\n' "$1"; FAIL=$((FAIL + 1)); }

env_value() { grep -E "^$1=" .env 2>/dev/null | head -1 | cut -d= -f2- | tr -d '"'; }

echo "== Preduslovi =="
[ -f .env ] && ok ".env postoji" || bad ".env ne postoji (pokreni ./start.sh)"
[ -d vendor ] && ok "composer zavisnosti instalirane" || bad "vendor/ ne postoji (composer install)"
[ -f public/build/manifest.json ] && ok "frontend build postoji" || bad "public/build ne postoji (npm run build)"

DB_CONN="$(env_value DB_CONNECTION)"; DB_CONN="${DB_CONN:-sqlite}"
if php -m | grep -qi "pdo_${DB_CONN}"; then
    ok "PHP pdo_${DB_CONN} ekstenzija"
else
    bad "PHP pdo_${DB_CONN} ekstenzija nedostaje"
fi
php -m | grep -qi '^intl$' && ok "PHP intl ekstenzija" || bad "PHP intl ekstenzija nedostaje (Filament)"

echo "== Baza =="
if php artisan migrate:status >/dev/null 2>&1; then
    ok "konekcija na bazu (${DB_CONN}) + migracije"
else
    bad "ne mogu da se povežem na bazu ili migracije nisu pokrenute (php artisan migrate)"
fi

echo "== Objavljeni podaci =="
ELECTION=""
if [ -f public/data/index.json ]; then
    ok "public/data/index.json postoji"
    ELECTION="$(php -r '$i=json_decode(file_get_contents("public/data/index.json"),true); echo $i["default"] ?? "";')"
    if [ -n "$ELECTION" ] && [ -f "public/data/${ELECTION}/config.json" ]; then
        ok "config.json za podrazumevani izbor: ${ELECTION}"
    else
        bad "index.json nema podrazumevani izbor sa config.json"
    fi
else
    bad "nema objavljenih snapshot-ova (php artisan izbori:demo --publish ili izbori:publish)"
fi

echo "== HTTP rute =="
php artisan serve --host=127.0.0.1 --port="$PORT" >/dev/null 2>&1 &
SERVER_PID=$!
trap 'kill "$SERVER_PID" 2>/dev/null' EXIT

for _ in $(seq 1 30); do
    curl -s -o /dev/null "$BASE/up" && break
    sleep 0.3
done

check_url() {
    local url="$1" expected="$2" code
    code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE$url")
    if [ "$code" = "$expected" ]; then
        ok "$url → $code"
    else
        bad "$url → $code (očekivano $expected)"
    fi
}

ADMIN="$(env_value ADMIN_PATH)"; ADMIN="${ADMIN:-admin}"

check_url /up 200
check_url / 200
check_url "/${ADMIN}/login" 200
check_url "/${ADMIN}" 302
if [ -n "$ELECTION" ]; then
    check_url "/${ELECTION}" 200
    check_url "/${ELECTION}/liste" 200
    check_url /data/index.json 200
    check_url "/data/${ELECTION}/config.json" 200
fi
check_url /data/ne-postoji.json 404

curl -s "$BASE/" | grep -q 'id="root"' \
    && ok "SPA shell renderuje (#root)" \
    || bad "SPA shell ne sadrži #root"

curl -s "$BASE/" | grep -q '__IZBORI__' \
    && ok "SPA shell nosi __IZBORI__ (dataUrl)" \
    || bad "SPA shell ne nosi __IZBORI__"

curl -s "$BASE/${ADMIN}/login" | grep -qi 'lozinka' \
    && ok "admin login renderuje na srpskom" \
    || bad "admin login ne renderuje prevode (sr → sr_Latn)"

if [ "${1:-}" = "--full" ]; then
    echo "== Testovi =="
    if php artisan test --compact; then
        ok "php artisan test"
    else
        bad "php artisan test (vidi ispis iznad)"
    fi
fi

echo ""
echo "Rezultat: ${PASS} OK, ${FAIL} neuspešno"
[ "$FAIL" -eq 0 ] && echo "✅ Backend radi." || echo "❌ Ima problema — vidi iznad."
exit "$FAIL"
