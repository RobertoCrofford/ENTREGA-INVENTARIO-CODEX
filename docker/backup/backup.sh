#!/bin/sh
set -eu

backup_once() {
    timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
    target="/backups/mysql-${timestamp}.sql.gz"

    mysqldump --host="$DB_HOST" --user="$DB_USERNAME" --password="$DB_PASSWORD" \
        --single-transaction --routines --events --databases "$DB_DATABASE" | gzip > "$target"
    gzip -t "$target"
    find /backups -type f -name 'mysql-*.sql.gz' -mtime +7 -delete
}

mkdir -p /backups
backup_once

while true; do
    sleep 86400
    backup_once
done
