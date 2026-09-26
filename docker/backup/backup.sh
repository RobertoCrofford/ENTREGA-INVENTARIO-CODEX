#!/bin/sh
set -eu

status_file="/backups/backup-status"

write_status() {
    outcome="$1"
    archive="${2:-}"
    temporary_status="${status_file}.tmp"

    {
        printf 'status=%s\n' "$outcome"
        printf 'finished_at=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
        printf 'file=%s\n' "$archive"
    } > "$temporary_status"
    mv "$temporary_status" "$status_file"
}

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
        write_status "failed"
        return 1
    fi
    rm -f "$temporary"
    find /backups -type f -name 'mysql-*.sql.gz' -mtime +7 -delete
    write_status "ok" "$target"
}

health_check() {
    [ -r "$status_file" ] || exit 1
    # shellcheck disable=SC1090
    . "$status_file"
    [ "${status:-}" = "ok" ] || exit 1
    [ -n "${file:-}" ] && [ -f "$file" ] || exit 1
    gzip -t "$file"
    now="$(date +%s)"
    modified="$(stat -c %Y "$file")"
    [ $((now - modified)) -le 93600 ]
}

mkdir -p /backups
mkdir -p /backup-requests
chown 82:82 /backup-requests
chmod 0770 /backup-requests

if [ "${1:-}" = "--health" ]; then
    health_check
    exit 0
fi

backup_once || true

while true; do
    elapsed=0
    while [ "$elapsed" -lt 86400 ]; do
        if [ -f /backup-requests/manual-backup-request ]; then
            rm -f /backup-requests/manual-backup-request
            backup_once || true
        fi
        sleep 60
        elapsed=$((elapsed + 60))
    done
    backup_once || true
done
