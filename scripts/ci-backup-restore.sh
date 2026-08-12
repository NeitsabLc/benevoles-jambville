#!/bin/sh
set -eu

repertoire_temporaire=$(mktemp -d)
repertoire_sauvegardes="$repertoire_temporaire/backups"
fichier_identite="$repertoire_temporaire/identity.txt"
mkdir -m 0777 "$repertoire_sauvegardes"

nettoyer() {
    rm -rf "$repertoire_temporaire"
}
trap nettoyer EXIT INT TERM

docker compose -f compose.yaml -f compose.prod.yaml build backup

docker run --rm --user root --entrypoint age-keygen \
    benevole-jambville-backup:local >"$fichier_identite"
destinataire=$(docker run --rm --user root \
    --volume "$fichier_identite:/run/identity.txt:ro" \
    --entrypoint age-keygen benevole-jambville-backup:local \
    -y /run/identity.txt)

env BACKUP_DIR="$repertoire_sauvegardes" BACKUP_AGE_RECIPIENT="$destinataire" \
    docker compose -f compose.yaml -f compose.prod.yaml run --rm \
    --env BACKUP_ONCE=1 backup

archive=$(find "$repertoire_sauvegardes" -maxdepth 1 -type f \
    -name 'benevole-jambville-*.dump.age' -print -quit)
if [ -z "$archive" ]; then
    echo 'Aucune sauvegarde chiffree produite.' >&2
    exit 1
fi
if find "$repertoire_sauvegardes" -maxdepth 1 -type f \
    \( -name '*.dump' -o -name '*.tmp' \) -print -quit | grep -q .; then
    echo 'Un fichier de sauvegarde en clair ou temporaire subsiste.' >&2
    exit 1
fi

administrateur=$(docker compose exec -T database printenv POSTGRES_USER)
base_source=$(docker compose exec -T database printenv POSTGRES_DB)
mot_de_passe=${POSTGRES_PASSWORD:-}
if [ -z "$mot_de_passe" ]; then
    mot_de_passe=$(docker compose exec -T database printenv POSTGRES_PASSWORD)
fi

env BACKUP_DIR="$repertoire_sauvegardes" BACKUP_AGE_RECIPIENT="$destinataire" \
    docker compose -f compose.yaml -f compose.prod.yaml run --rm --no-deps \
    --user root \
    --env BACKUP_AGE_IDENTITY_FILE=/run/identity.txt \
    --env POSTGRES_USER="$administrateur" \
    --env POSTGRES_DB="$base_source" \
    --env PGPASSWORD="$mot_de_passe" \
    --env RESTORE_DATABASE_NAME="${base_source}_restore_check" \
    --volume "$fichier_identite:/run/identity.txt:ro" \
    --entrypoint /usr/local/bin/benevole-jambville-verify-backup \
    backup "/backups/$(basename "$archive")"
