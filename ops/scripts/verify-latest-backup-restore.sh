#!/usr/bin/env bash

set -Eeuo pipefail

APP_DIR="/var/www/lycee-pagnidibsom-logiciel/app-source"
ARCHIVE_DIR="$APP_DIR/storage/app/private/lpp-gestion-scolaire"
PASSWORD_FILE="/root/lpp-backup-archive-password"
LOG_FILE="$APP_DIR/storage/logs/backup-restore-check.log"
DATABASE_NAME="lpp_restore_check_$(date +%Y%m%d%H%M%S)"
TEMP_DIR="$(mktemp -d /tmp/lpp-restore-check.XXXXXX)"

notify_failure() {
    cd "$APP_DIR"
    sudo -u www-data /usr/bin/php artisan lpp:notify-backup-restore-failure >/dev/null 2>&1 || true
}

cleanup() {
    mysql -Nse "DROP DATABASE IF EXISTS \`$DATABASE_NAME\`;" >/dev/null 2>&1 || true
    rm -rf "$TEMP_DIR"
}

trap 'notify_failure' ERR
trap 'cleanup' EXIT

if [[ ! -s "$PASSWORD_FILE" ]]; then
    echo "Mot de passe d archive absent : $PASSWORD_FILE" >&2
    exit 1
fi

ARCHIVE="$(find "$ARCHIVE_DIR" -maxdepth 1 -type f -name '*.zip' -printf '%T@ %p\n' \
    | sort -nr \
    | head -n 1 \
    | cut -d' ' -f2-)"

if [[ -z "$ARCHIVE" ]]; then
    echo "Aucune archive disponible dans $ARCHIVE_DIR" >&2
    exit 1
fi

/usr/bin/php -r '
    $archive = $argv[1];
    $password = trim(file_get_contents($argv[2]));
    $destination = $argv[3];
    $zip = new ZipArchive();

    if ($zip->open($archive) !== true) {
        fwrite(STDERR, "Archive illisible.\n");
        exit(2);
    }

    $zip->setPassword($password);
    $sqlFile = null;

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = $zip->getNameIndex($index);

        if (str_ends_with($name, ".sql")) {
            $sqlFile = $name;
            break;
        }
    }

    if ($sqlFile === null || ! $zip->extractTo($destination, [$sqlFile])) {
        fwrite(STDERR, "Le dump SQL chiffre ne peut pas etre extrait.\n");
        exit(3);
    }

    echo $destination.DIRECTORY_SEPARATOR.$sqlFile;
' "$ARCHIVE" "$PASSWORD_FILE" "$TEMP_DIR" > "$TEMP_DIR/sql-path"

SQL_FILE="$(cat "$TEMP_DIR/sql-path")"

mysql -Nse "CREATE DATABASE \`$DATABASE_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql "$DATABASE_NAME" < "$SQL_FILE"

TABLE_COUNT="$(mysql -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DATABASE_NAME';")"

if [[ "$TABLE_COUNT" -lt 10 ]]; then
    echo "Restauration incomplete : seulement $TABLE_COUNT tables." >&2
    exit 1
fi

printf '%s | OK | archive=%s | tables=%s\n' \
    "$(date --iso-8601=seconds)" \
    "$(basename "$ARCHIVE")" \
    "$TABLE_COUNT" \
    | tee -a "$LOG_FILE"
