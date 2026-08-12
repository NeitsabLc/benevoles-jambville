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

assert_container_hardened() {
    service=$1
    utilisateur_attendu=$2
    memoire_attendue=$3
    nano_cpus_attendus=$4
    pids_attendus=$5
    montages_attendus=${6:-autorises}
    conteneur=$(compose ps --quiet --all "$service")

    test -n "$conteneur"
    test "$(docker inspect --format '{{.Config.User}}' "$conteneur")" = "$utilisateur_attendu"
    test "$(docker inspect --format '{{.HostConfig.ReadonlyRootfs}}' "$conteneur")" = true
    docker inspect --format '{{json .HostConfig.CapDrop}}' "$conteneur" | grep -q ALL
    docker inspect --format '{{json .HostConfig.SecurityOpt}}' "$conteneur" | grep -q 'no-new-privileges:true'
    test "$(docker inspect --format '{{.HostConfig.Memory}}' "$conteneur")" = "$memoire_attendue"
    test "$(docker inspect --format '{{.HostConfig.NanoCpus}}' "$conteneur")" = "$nano_cpus_attendus"
    test "$(docker inspect --format '{{.HostConfig.PidsLimit}}' "$conteneur")" = "$pids_attendus"
    if [ "$montages_attendus" = aucun ]; then
        test "$(docker inspect --format '{{json .Mounts}}' "$conteneur")" = '[]'
    fi
}

: "${POSTGRES_PASSWORD:?POSTGRES_PASSWORD doit etre defini}"
: "${POSTGRES_APP_PASSWORD:?POSTGRES_APP_PASSWORD doit etre defini}"
: "${POSTGRES_MIGRATOR_PASSWORD:?POSTGRES_MIGRATOR_PASSWORD doit etre defini}"
: "${POSTGRES_BACKUP_PASSWORD:?POSTGRES_BACKUP_PASSWORD doit etre defini}"
: "${POSTGRES_HEALTHCHECK_USER:=benevole_jambville_admin}"
: "${POSTGRES_HEALTHCHECK_PASSWORD:?POSTGRES_HEALTHCHECK_PASSWORD doit etre defini}"
: "${POSTGRES_DB:=benevole_jambville}"
: "${POSTGRES_USER:=benevole_jambville}"
: "${NGINX_HOST_PORT:=18082}"
: "${POSTGRES_HOST_PORT:=15436}"
: "${TRUSTED_HOST_PATTERN:=^(localhost|127[.]0[.]0[.]1)$}"
: "${TRUSTED_PROXIES:=127.0.0.1}"

repertoire_temporaire=$(mktemp -d)
export BACKUP_DIR="$repertoire_temporaire/backups"
export BACKUP_AGE_RECIPIENT=age1configuration-temporaire-remplacee-avant-sauvegarde
export NGINX_HOST_PORT POSTGRES_HOST_PORT POSTGRES_DB POSTGRES_USER
export POSTGRES_HEALTHCHECK_USER POSTGRES_HEALTHCHECK_PASSWORD
export TRUSTED_HOST_PATTERN TRUSTED_PROXIES
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
compose --profile outils build --quiet database liquibase php nginx backup

fichier_identite="$repertoire_temporaire/identity.txt"
docker run --rm --user root --entrypoint age-keygen \
    benevole-jambville-backup:local >"$fichier_identite"
BACKUP_AGE_RECIPIENT=$(docker run --rm --user root \
    --volume "$fichier_identite:/run/identity.txt:ro" \
    --entrypoint age-keygen benevole-jambville-backup:local \
    -y /run/identity.txt)
export BACKUP_AGE_RECIPIENT

compose up --detach --wait --wait-timeout 60 --no-build database
compose --profile outils run --rm \
    --env LIQUIBASE_COMMAND_USERNAME="$POSTGRES_USER" \
    --env LIQUIBASE_COMMAND_PASSWORD="$POSTGRES_PASSWORD" \
    liquibase update
compose exec --no-TTY database /usr/local/bin/finalize-role-hardening
compose --profile outils run --rm liquibase update
compose exec --no-TTY database sh -ec '
    migrator=$(PGPASSWORD="$POSTGRES_MIGRATOR_PASSWORD" psql --host=127.0.0.1 \
        --username="$POSTGRES_MIGRATOR_USER" --dbname="$POSTGRES_DB" \
        --tuples-only --no-align --command="SELECT
            current_user = '\''benevole_jambville_migrator'\'',
            NOT usesuper,
            NOT usecreatedb
        FROM pg_user WHERE usename = current_user")
    test "$migrator" = "t|t|t"
    PGPASSWORD="$POSTGRES_MIGRATOR_PASSWORD" psql --host=127.0.0.1 \
        --username="$POSTGRES_MIGRATOR_USER" --dbname="$POSTGRES_DB" \
        --set=ON_ERROR_STOP=1 \
        --command="CREATE TABLE benevole_jambville.ci_migrator_privilege_check (id integer); DROP TABLE benevole_jambville.ci_migrator_privilege_check"
'
compose up --detach --no-build php nginx

curl --fail --silent --show-error --retry 30 --retry-delay 2 --retry-all-errors \
    --output /dev/null "http://127.0.0.1:${NGINX_HOST_PORT}/connexion"
test "$(curl --silent --output /dev/null --write-out '%{http_code}' \
    --header 'Host: attaquant.example' "http://127.0.0.1:${NGINX_HOST_PORT}/connexion")" = 400
test "$(curl --silent --output /dev/null --write-out '%{http_code}' \
    --header 'Host: attaquant.example' \
    --header 'X-Forwarded-Host: localhost' \
    "http://127.0.0.1:${NGINX_HOST_PORT}/connexion")" = 400

compose exec --no-TTY php php bin/console about --env=prod --no-debug
compose exec --no-TTY php php bin/console cache:warmup --env=prod --no-debug
test "$(compose exec --no-TTY php id -un)" = www-data
compose --profile outils create maintenance backup liquibase
assert_container_hardened php www-data 536870912 1000000000 128 aucun
assert_container_hardened nginx nginx 134217728 500000000 64 aucun
assert_container_hardened database postgres 1073741824 2000000000 256
assert_container_hardened maintenance www-data 268435456 500000000 64 aucun
assert_container_hardened backup postgres 536870912 1000000000 128
assert_container_hardened liquibase liquibase:liquibase 536870912 1000000000 128

compose exec --no-TTY database sh -ec '
    hba_file=$(PGPASSWORD="$POSTGRES_HEALTHCHECK_PASSWORD" psql --host=127.0.0.1 \
        --username="$POSTGRES_HEALTHCHECK_USER" --dbname="$POSTGRES_DB" \
        --tuples-only --no-align --command="SHOW hba_file")
    if grep -Ev "^[[:space:]]*(#|$)" "$hba_file" | grep -q "[[:space:]]trust\([[:space:]]\|$\)"; then
        echo "Une regle trust subsiste dans pg_hba.conf." >&2
        exit 1
    fi
    grep -Ev "^[[:space:]]*(#|$)" "$hba_file" | grep -q "scram-sha-256"
    grep -Ev "^[[:space:]]*(#|$)" "$hba_file" | grep -Eq "host[[:space:]]+benevole_jambville[[:space:]]+benevole_jambville_app[[:space:]]+172[.]29[.]0[.]0/24[[:space:]]+scram-sha-256"
    grep -Ev "^[[:space:]]*(#|$)" "$hba_file" | grep -Eq "host[[:space:]]+benevole_jambville[[:space:]]+benevole_jambville_migrator[[:space:]]+172[.]29[.]0[.]0/24[[:space:]]+scram-sha-256"
    grep -Ev "^[[:space:]]*(#|$)" "$hba_file" | grep -Eq "host[[:space:]]+benevole_jambville[[:space:]]+benevole_jambville_backup[[:space:]]+172[.]29[.]0[.]0/24[[:space:]]+scram-sha-256"
    grep -Ev "^[[:space:]]*(#|$)" "$hba_file" | grep -Eq "host[[:space:]]+all[[:space:]]+benevole_jambville_admin[[:space:]]+172[.]29[.]0[.]0/24[[:space:]]+scram-sha-256"
    grep -Ev "^[[:space:]]*(#|$)" "$hba_file" | grep -Eq "host[[:space:]]+all[[:space:]]+all[[:space:]]+0[.]0[.]0[.]0/0[[:space:]]+reject"

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

if docker run --rm --network "${COMPOSE_PROJECT_NAME}_benevole_jambville" \
    --entrypoint psql benevole-jambville-postgres:local \
    "postgresql://role_interdit:mot-de-passe@database:5432/${POSTGRES_DB}" \
    --command='SELECT 1'; then
    echo "Le HBA accepte un role PostgreSQL non autorise." >&2
    exit 1
fi

compose run --rm --env MAINTENANCE_ONCE=1 maintenance
compose run --rm --env BACKUP_ONCE=1 backup
archive=$(find "$BACKUP_DIR" -maxdepth 1 -type f \
    -name 'benevole-jambville-*.dump.age' -size +0c -print -quit)
test -n "$archive"
if find "$BACKUP_DIR" -maxdepth 1 -type f \
    \( -name '*.dump' -o -name '*.tar.gz' -o -name '*.tmp' \) \
    -print -quit | grep -q .; then
    echo "Un fichier de sauvegarde en clair ou temporaire subsiste." >&2
    exit 1
fi

compose run --rm --no-deps --user root \
    --env BACKUP_AGE_IDENTITY_FILE=/run/identity.txt \
    --env POSTGRES_USER="$POSTGRES_HEALTHCHECK_USER" \
    --env POSTGRES_DB="$POSTGRES_DB" \
    --env PGPASSWORD="$POSTGRES_HEALTHCHECK_PASSWORD" \
    --env RESTORE_DATABASE_NAME="${POSTGRES_DB}_production_restore_check" \
    --volume "$fichier_identite:/run/identity.txt:ro" \
    --entrypoint /usr/local/bin/benevole-jambville-verify-backup \
    backup "/backups/$(basename "$archive")"

compose exec --no-TTY database sh -ec '
    case "$POSTGRES_USER" in *[!a-zA-Z0-9_]*) exit 2 ;; esac
    resultat=$(PGPASSWORD="$POSTGRES_HEALTHCHECK_PASSWORD" psql --host=127.0.0.1 \
        --username="$POSTGRES_HEALTHCHECK_USER" --dbname="$POSTGRES_DB" \
        --tuples-only --no-align --set=ON_ERROR_STOP=1 \
        --command="SELECT NOT rolcanlogin FROM pg_roles WHERE rolname = '\''$POSTGRES_USER'\''")
    test "$resultat" = t
'

curl --fail --silent --show-error --output /dev/null \
    "http://127.0.0.1:${NGINX_HOST_PORT}/connexion"
