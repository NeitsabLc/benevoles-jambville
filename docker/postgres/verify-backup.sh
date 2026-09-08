#!/bin/sh
set -eu
umask 077

archive=${1:?Indiquez le chemin du fichier .dump.age a restaurer}
: "${BACKUP_AGE_IDENTITY_FILE:?BACKUP_AGE_IDENTITY_FILE doit designer la cle privee age}"
: "${POSTGRES_HOST:=database}"
: "${POSTGRES_USER:?POSTGRES_USER doit contenir le role administrateur de restauration}"
: "${POSTGRES_DB:?POSTGRES_DB doit contenir le nom de la base source}"
: "${RESTORE_DATABASE_NAME:=${POSTGRES_DB}_restore_check}"

sauvegarde_claire=$(mktemp /tmp/benevole-jambville-restore.dump.XXXXXX)

nettoyer() {
    rm -f "$sauvegarde_claire"
    dropdb --host="$POSTGRES_HOST" --username="$POSTGRES_USER" \
        --if-exists --force "$RESTORE_DATABASE_NAME" >/dev/null 2>&1 || true
}
trap nettoyer EXIT INT TERM

age --decrypt \
    --identity "$BACKUP_AGE_IDENTITY_FILE" \
    --output "$sauvegarde_claire" \
    "$archive"

dropdb --host="$POSTGRES_HOST" --username="$POSTGRES_USER" \
    --if-exists --force "$RESTORE_DATABASE_NAME"
createdb --host="$POSTGRES_HOST" --username="$POSTGRES_USER" \
    --owner="$POSTGRES_USER" "$RESTORE_DATABASE_NAME"
pg_restore --host="$POSTGRES_HOST" --username="$POSTGRES_USER" \
    --dbname="$RESTORE_DATABASE_NAME" --exit-on-error --no-owner --no-acl \
    "$sauvegarde_claire"

source_count=$(psql --host="$POSTGRES_HOST" --username="$POSTGRES_USER" \
    --dbname="$POSTGRES_DB" --tuples-only --no-align \
    --command='SELECT COUNT(*) FROM benevole_jambville.utilisateur')
restore_count=$(psql --host="$POSTGRES_HOST" --username="$POSTGRES_USER" \
    --dbname="$RESTORE_DATABASE_NAME" --tuples-only --no-align \
    --command='SELECT COUNT(*) FROM benevole_jambville.utilisateur')

if [ "$restore_count" != "$source_count" ]; then
    echo "Echec de restauration : $restore_count utilisateurs restaures, $source_count attendus." >&2
    exit 1
fi

echo "Restauration verifiee dans $RESTORE_DATABASE_NAME ($restore_count utilisateurs)."
