#!/bin/sh
set -eu
umask 077

repertoire=/backups
retention_minutes=10080
: "${BACKUP_AGE_RECIPIENT:?BACKUP_AGE_RECIPIENT doit contenir la cle publique age de sauvegarde}"

mkdir -p "$repertoire"

horodatage=$(date -u +%Y%m%dT%H%M%SZ)
sauvegarde_claire=$(mktemp "/tmp/benevole-jambville-$horodatage.dump.XXXXXX")
sauvegarde_temp="$repertoire/benevole-jambville-$horodatage.dump.age.tmp"
sauvegarde_finale="$repertoire/benevole-jambville-$horodatage.dump.age"

nettoyer() {
    rm -f "$sauvegarde_claire" "$sauvegarde_temp"
}
trap nettoyer EXIT INT TERM

printf '{"event":"backup_started","timestamp":"%s"}\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"

pg_dump --format=custom --no-owner --no-acl \
    --host=database --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" \
    --file="$sauvegarde_claire"
age --encrypt \
    --recipient "$BACKUP_AGE_RECIPIENT" \
    --output "$sauvegarde_temp" \
    "$sauvegarde_claire"
chmod 0600 "$sauvegarde_temp"
mv "$sauvegarde_temp" "$sauvegarde_finale"
rm -f "$sauvegarde_claire"
trap - EXIT INT TERM

find "$repertoire" -type f -name 'benevole-jambville-*.dump.age' \
    -mmin "+$retention_minutes" -delete

printf '{"event":"backup_succeeded","timestamp":"%s"}\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
