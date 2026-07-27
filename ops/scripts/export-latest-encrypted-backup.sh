#!/usr/bin/env bash

set -Eeuo pipefail

ARCHIVE_DIR="/var/www/lycee-pagnidibsom-logiciel/app-source/storage/app/private/lpp-gestion-scolaire"
ARCHIVE="$(find "$ARCHIVE_DIR" -maxdepth 1 -type f -name '*.zip' -printf '%T@ %p\n' \
    | sort -nr \
    | head -n 1 \
    | cut -d' ' -f2-)"

if [[ -z "$ARCHIVE" || ! -s "$ARCHIVE" ]]; then
    echo "Aucune archive chiffree disponible." >&2
    exit 1
fi

if ! zipinfo -v "$ARCHIVE" | grep -q 'file security status:.*encrypted'; then
    echo "La derniere archive n est pas chiffree." >&2
    exit 1
fi

cat "$ARCHIVE"
