#!/bin/sh
set -eu

backup_once() {
    timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
    target="/backups/mysql-${timestamp}.sql.gz"
    temporary="/backups/.mysql-${timestamp}.sql"
    compressed="${target}.tmp"

    rm -f "$temporary" "$compressed"
    if ! mysqldump --host="$DB_HOST" --user="$DB_USERNAME" --password="$DB_PASSWORD" \
        --no-tablespaces --single-transaction --routines --events --databases "$DB_DATABASE" > "$temporary" \
        || ! gzip -c "$temporary" > "$compressed" \
        || ! gzip -t "$compressed" \
        || ! mv "$compressed" "$target"; then
        rm -f "$temporary" "$compressed"
        return 1
    fi
    rm -f "$temporary"
    find /backups -type f -name 'mysql-*.sql.gz' -mtime +7 -delete
}

mkdir -p /backups
backup_once

while true; do
    sleep 86400
    backup_once
done
