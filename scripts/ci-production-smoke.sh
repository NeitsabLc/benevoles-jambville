#!/bin/sh
set -eu

case "${COMPOSE_PROJECT_NAME:-}" in
    *-ci-*|*-smoke-*) ;;
    *)
        echo 'Le smoke test refuse un projet Compose non dedie.' >&2
        exit 2
        ;;
esac

compose() {
    docker compose -f compose.yaml -f compose.prod.yaml "$@"
}

assert_hardened() {
    service=$1
    utilisateur_attendu=$2
    conteneur=$(compose ps --quiet "$service")

    test -n "$conteneur"
    test "$(docker inspect --format '{{.Config.User}}' "$conteneur")" = "$utilisateur_attendu"
    test "$(docker inspect --format '{{.HostConfig.ReadonlyRootfs}}' "$conteneur")" = true
    docker inspect --format '{{json .HostConfig.CapDrop}}' "$conteneur" | grep -q ALL
    docker inspect --format '{{json .HostConfig.SecurityOpt}}' "$conteneur" | grep -q 'no-new-privileges:true'
    test "$(docker inspect --format '{{json .Mounts}}' "$conteneur")" = '[]'
}

assert_database_hardened() {
    conteneur=$(compose ps --quiet database)

    test -n "$conteneur"
    test "$(docker inspect --format '{{.Config.User}}' "$conteneur")" = postgres
    test "$(docker inspect --format '{{.HostConfig.ReadonlyRootfs}}' "$conteneur")" = true
    docker inspect --format '{{json .HostConfig.CapDrop}}' "$conteneur" | grep -q ALL
    docker inspect --format '{{json .HostConfig.SecurityOpt}}' "$conteneur" | grep -q 'no-new-privileges:true'
}

: "${POSTGRES_PASSWORD:?POSTGRES_PASSWORD doit etre defini}"
: "${POSTGRES_APP_PASSWORD:?POSTGRES_APP_PASSWORD doit etre defini}"
: "${POSTGRES_BACKUP_PASSWORD:?POSTGRES_BACKUP_PASSWORD doit etre defini}"
: "${POSTGRES_DB:=benevole_jambville}"
: "${POSTGRES_USER:=benevole_jambville}"
: "${NGINX_HOST_PORT:=18082}"
: "${POSTGRES_HOST_PORT:=15436}"

repertoire_temporaire=$(mktemp -d)
export BACKUP_DIR="$repertoire_temporaire/backups"
export BACKUP_AGE_RECIPIENT=age1configuration-temporaire-remplacee-avant-sauvegarde
export NGINX_HOST_PORT POSTGRES_HOST_PORT POSTGRES_DB POSTGRES_USER
mkdir -m 0777 "$BACKUP_DIR"

nettoyer() {
    statut=$?
    trap - EXIT INT TERM
    if [ "$statut" -ne 0 ]; then
        compose ps >&2 || true
        compose logs --no-color --tail=200 database php nginx maintenance backup >&2 || true
    fi
    compose down --volumes --remove-orphans >/dev/null 2>&1 || true
    rm -rf "$repertoire_temporaire"
    exit "$statut"
}
trap nettoyer EXIT INT TERM

compose config --quiet
compose build --quiet php nginx backup

fichier_identite="$repertoire_temporaire/identity.txt"
docker run --rm --user root --entrypoint age-keygen \
    benevole-jambville-backup:local >"$fichier_identite"
BACKUP_AGE_RECIPIENT=$(docker run --rm --user root \
    --volume "$fichier_identite:/run/identity.txt:ro" \
    --entrypoint age-keygen benevole-jambville-backup:local \
    -y /run/identity.txt)
export BACKUP_AGE_RECIPIENT

compose up --detach --wait --wait-timeout 60 --no-build database
compose --profile outils run --rm liquibase update
compose up --detach --no-build php nginx

curl --fail --silent --show-error --retry 30 --retry-delay 2 --retry-all-errors \
    --output /dev/null "http://127.0.0.1:${NGINX_HOST_PORT}/connexion"
test "$(curl --silent --output /dev/null --write-out '%{http_code}' \
    --header 'Host: attaquant.example' "http://127.0.0.1:${NGINX_HOST_PORT}/connexion")" = 400

compose exec --no-TTY php php bin/console about --env=prod --no-debug
compose exec --no-TTY php php bin/console cache:warmup --env=prod --no-debug
test "$(compose exec --no-TTY php id -un)" = www-data
assert_hardened php www-data
assert_hardened nginx nginx
assert_database_hardened

compose exec --no-TTY database sh -ec '
    hba_file=$(psql --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" --tuples-only --no-align --command="SHOW hba_file")
    if grep -Ev "^[[:space:]]*(#|$)" "$hba_file" | grep -q "[[:space:]]trust\([[:space:]]\|$\)"; then
        echo "Une regle trust subsiste dans pg_hba.conf." >&2
        exit 1
    fi
    grep -Ev "^[[:space:]]*(#|$)" "$hba_file" | grep -q "scram-sha-256"

    app=$(PGPASSWORD="$POSTGRES_APP_PASSWORD" psql --host=127.0.0.1 \
        --username="$POSTGRES_APP_USER" --dbname="$POSTGRES_DB" \
        --tuples-only --no-align --command="SELECT
            current_user = '"'"'benevole_jambville_app'"'"',
            has_table_privilege(current_user, '"'"'benevole_jambville.utilisateur'"'"', '"'"'SELECT'"'"'),
            NOT has_schema_privilege(current_user, '"'"'benevole_jambville'"'"', '"'"'CREATE'"'"')")
    backup=$(PGPASSWORD="$POSTGRES_BACKUP_PASSWORD" psql --host=127.0.0.1 \
        --username="$POSTGRES_BACKUP_USER" --dbname="$POSTGRES_DB" \
        --tuples-only --no-align --command="SELECT
            has_table_privilege(current_user, '"'"'benevole_jambville.utilisateur'"'"', '"'"'SELECT'"'"'),
            NOT has_table_privilege(current_user, '"'"'benevole_jambville.utilisateur'"'"', '"'"'INSERT'"'"')")
    test "$app" = "t|t|t"
    test "$backup" = "t|t"
'

compose run --rm --env MAINTENANCE_ONCE=1 maintenance
compose run --rm --env BACKUP_ONCE=1 backup
archive=$(find "$BACKUP_DIR" -maxdepth 1 -type f \
    -name 'benevole-jambville-*.dump.age' -size +0c -print -quit)
test -n "$archive"

compose run --rm --no-deps --user root \
    --env BACKUP_AGE_IDENTITY_FILE=/run/identity.txt \
    --env POSTGRES_USER="$POSTGRES_USER" \
    --env POSTGRES_DB="$POSTGRES_DB" \
    --env PGPASSWORD="$POSTGRES_PASSWORD" \
    --env RESTORE_DATABASE_NAME="${POSTGRES_DB}_production_restore_check" \
    --volume "$fichier_identite:/run/identity.txt:ro" \
    --entrypoint /usr/local/bin/benevole-jambville-verify-backup \
    backup "/backups/$(basename "$archive")"

curl --fail --silent --show-error --output /dev/null \
    "http://127.0.0.1:${NGINX_HOST_PORT}/connexion"
