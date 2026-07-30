#!/usr/bin/env bash

set -Eeuo pipefail

ARCHIVE_DIR="/var/www/lycee-pagnidibsom-logiciel/app-source/storage/app/private/lpp-gestion-scolaire"
MAX_AGE_SECONDS=14400
ARCHIVE="$(find "$ARCHIVE_DIR" -maxdepth 1 -type f -name '*.zip' -printf '%T@ %p\n' \
    | sort -nr \
    | head -n 1 \
    | cut -d' ' -f2-)"

if [[ -z "$ARCHIVE" || ! -s "$ARCHIVE" ]]; then
    echo "Aucune archive chiffree disponible." >&2
    exit 1
fi

ARCHIVE_AGE_SECONDS="$(($(date +%s) - $(stat -c %Y "$ARCHIVE")))"

if (( ARCHIVE_AGE_SECONDS < 0 || ARCHIVE_AGE_SECONDS > MAX_AGE_SECONDS )); then
    echo "La derniere archive est trop ancienne pour etre exportee." >&2
    exit 1
fi

if ! zipinfo -v "$ARCHIVE" | grep 'file security status:.*encrypted' >/dev/null; then
    echo "La derniere archive n est pas chiffree." >&2
    exit 1
fi

cat "$ARCHIVE"
