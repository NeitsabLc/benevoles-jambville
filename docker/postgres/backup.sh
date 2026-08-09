#!/bin/sh
set -eu
umask 077

repertoire=/backups
retention_minutes=10080

mkdir -p "$repertoire"

while true; do
    horodatage=$(date -u +%Y%m%dT%H%M%SZ)
    sauvegarde_temp="$repertoire/benevole-jambville-$horodatage.dump.tmp"
    sauvegarde_finale="$repertoire/benevole-jambville-$horodatage.dump"

    pg_dump --format=custom --no-owner --no-acl \
        --host=database --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" \
        --file="$sauvegarde_temp"
    mv "$sauvegarde_temp" "$sauvegarde_finale"

    find "$repertoire" -type f -name 'benevole-jambville-*.dump' \
        -mmin "+$retention_minutes" -delete

    if [ "${BACKUP_ONCE:-0}" = "1" ]; then
        exit 0
    fi

    sleep 86400
done
