#!/usr/bin/env bash
#
# Nezavisna provera objavljenog snapshot-a: da li svaki fajl odgovara SHA-256 iz
# manifest.json i da li hash lanac vodi do prethodne verzije.
# Ne koristi Laravel — može da se pokrene nad kopijom /data sa CDN-a.
#
#   scripts/verify-snapshot.sh public/data/parlament-2026-demo/09150224/results
#   scripts/verify-snapshot.sh /mnt/cdn-mirror/parlament-2026/12132145/results
#
set -euo pipefail

DIR="${1:?putanja do foldera snapshot-a, npr. public/data/IZBOR/VERZIJA/results}"
MANIFEST="$DIR/manifest.json"
[ -f "$MANIFEST" ] || { echo "Nema manifest.json u $DIR"; exit 2; }

command -v jq >/dev/null || { echo "Potreban je jq (apt install jq)"; exit 2; }

fail=0
while IFS=$'\t' read -r file expected; do
    if [ ! -f "$DIR/$file" ]; then
        echo "✘ nedostaje: $file"; fail=1; continue
    fi
    actual=$(sha256sum "$DIR/$file" | cut -d' ' -f1)
    if [ "$actual" = "$expected" ]; then
        echo "✔ $file"
    else
        echo "✘ $file (očekivano $expected, dobijeno $actual)"; fail=1
    fi
done < <(jq -r '.files | to_entries[] | "\(.key)\t\(.value)"' "$MANIFEST")

# Lanac: hash = sha256( previous_hash + json(files) ) — isto kao SnapshotPublisher::publish()
previous=$(jq -r '.previous_hash // ""' "$MANIFEST")
files_json=$(jq -c '.files' "$MANIFEST")
computed=$(printf '%s%s' "$previous" "$files_json" | sha256sum | cut -d' ' -f1)
declared=$(jq -r '.hash' "$MANIFEST")

if [ "$computed" = "$declared" ]; then
    echo "✔ hash lanca: $declared"
else
    echo "✘ hash lanca ne odgovara (manifest: $declared, izračunato: $computed)"; fail=1
fi

[ "$fail" -eq 0 ] && echo "✅ Snapshot je netaknut." || { echo "❌ Snapshot je izmenjen ili nepotpun."; exit 1; }
