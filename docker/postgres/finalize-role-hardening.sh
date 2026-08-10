#!/bin/sh
set -eu

verify_login() {
    role="$1"
    password="$2"

    actual_role=$(PGPASSWORD="$password" psql \
        --host=127.0.0.1 \
        --username="$role" \
        --dbname="$POSTGRES_DB" \
        --tuples-only \
        --no-align \
        --command='SELECT current_user')

    if [ "$actual_role" != "$role" ]; then
        echo "La connexion attendue avec le rôle $role n'a pas été vérifiée." >&2
        exit 1
    fi
}

verify_login "$POSTGRES_APP_USER" "$POSTGRES_APP_PASSWORD"
verify_login "$POSTGRES_BACKUP_USER" "$POSTGRES_BACKUP_PASSWORD"

psql -v ON_ERROR_STOP=1 \
    --username "$POSTGRES_USER" \
    --dbname "$POSTGRES_DB" \
    --set=app_user="$POSTGRES_APP_USER" \
    --set=backup_user="$POSTGRES_BACKUP_USER" <<-'SQL'
SELECT format('ALTER ROLE %I WITH NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS', :'app_user') \gexec
SELECT format('ALTER ROLE %I WITH NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS', :'backup_user') \gexec
SQL

echo "Durcissement final appliqué aux rôles applicatif et sauvegarde."
