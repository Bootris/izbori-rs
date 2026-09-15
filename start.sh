#!/usr/bin/env bash
#
# IZBORI.RS — lokalno pokretanje jednom komandom.
#
#   ./start.sh              → instalira, migrira, seed-uje demo izbor, objavi
#                             snapshot-ove, build-uje front i pokrene server
#   PORT=8080 ./start.sh    → drugi port
#   ./start.sh --dev        → + Vite hot reload, queue worker i scheduler
#                             (simulacija izborne noći: rezultati se
#                             republikuju svakih PUBLISH_INTERVAL_MINUTES)
#   ./start.sh --fresh      → obriši bazu i public/data, sve ispočetka
#   ./start.sh --build      → forsiraj npm run build
#
set -e
cd "$(dirname "$0")"

PORT="${PORT:-8000}"
FRESH=0; DEV=0; BUILD=0
for arg in "$@"; do
    case "$arg" in
        --fresh) FRESH=1; BUILD=1 ;;
        --dev)   DEV=1 ;;
        --build) BUILD=1 ;;
        *) echo "Nepoznata opcija: $arg"; exit 1 ;;
    esac
done

step() { printf '\n\033[1;34m→ %s\033[0m\n' "$1"; }

# 1. .env
if [ ! -f .env ]; then
    step "Kreiram .env iz .env.example (SQLite, admin lozinka: password)"
    cp .env.example .env
    sed -i 's/^SEED_ADMIN_PASSWORD=.*/SEED_ADMIN_PASSWORD=password/' .env
    php artisan key:generate --no-interaction -q
fi

# 2. zavisnosti
[ -d vendor ]       || { step "composer install"; composer install --no-interaction; }
[ -d node_modules ] || { step "npm install"; npm install; }

# 3. baza
if grep -qE '^DB_CONNECTION=sqlite' .env; then
    touch database/database.sqlite
fi
if [ "$FRESH" = 1 ]; then
    step "migrate:fresh + seed (briše sve podatke i public/data)"
    rm -rf public/data
    php artisan migrate:fresh --seed --force --no-interaction
else
    step "migrate + seed"
    php artisan migrate --force --no-interaction
    php artisan db:seed --force --no-interaction -q
fi
php artisan storage:link >/dev/null 2>&1 || true

# 4. demo izbor + objava snapshot-ova (samo ako nema nijednog izbora)
if [ "$(php artisan izbori:count 2>/dev/null)" = "0" ]; then
    step "Nema izbora u bazi — seed-ujem demo izbor i objavljujem snapshot-ove"
    php artisan izbori:demo --publish
fi

# 5. frontend build
if [ "$BUILD" = 1 ] || [ ! -f public/build/manifest.json ]; then
    step "npm run build"
    npm run build
fi
php artisan config:clear -q

ADMIN_PATH=$(grep -E '^ADMIN_PATH=' .env | cut -d= -f2 | tr -d '"' )
ADMIN_EMAIL=$(grep -E '^SEED_ADMIN_EMAIL=' .env | cut -d= -f2 | tr -d '"')

echo ""
echo "════════════════════════════════════════════════════════"
echo "  Sajt (SPA):   http://127.0.0.1:${PORT}/"
echo "  Podaci:       http://127.0.0.1:${PORT}/data/index.json"
echo "  Admin:        http://127.0.0.1:${PORT}/${ADMIN_PATH:-admin}"
echo "                ${ADMIN_EMAIL:-admin@example.com} / (SEED_ADMIN_PASSWORD iz .env)"
[ "$DEV" = 1 ] && echo "  Dev mod:      Vite HMR + queue worker + scheduler"
echo "════════════════════════════════════════════════════════"
echo ""

if [ "$DEV" = 1 ]; then
    exec npx concurrently -k -c "#93c5fd,#c4b5fd,#fdba74,#86efac" \
        --names "server,vite,queue,cron" \
        "php artisan serve --host=127.0.0.1 --port=${PORT}" \
        "npm run dev" \
        "php artisan queue:listen --tries=1 --timeout=300" \
        "php artisan schedule:work"
fi

exec php artisan serve --host=127.0.0.1 --port="$PORT"
