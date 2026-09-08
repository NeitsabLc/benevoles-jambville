#!/bin/sh
set -eu

psql -v ON_ERROR_STOP=1 \
    --username "$POSTGRES_USER" \
    --dbname "$POSTGRES_DB" \
    --set=app_user="$POSTGRES_APP_USER" \
    --set=app_password="$POSTGRES_APP_PASSWORD" \
    --set=migrator_user="$POSTGRES_MIGRATOR_USER" \
    --set=migrator_password="$POSTGRES_MIGRATOR_PASSWORD" \
    --set=backup_user="$POSTGRES_BACKUP_USER" \
    --set=backup_password="$POSTGRES_BACKUP_PASSWORD" \
    --set=admin_user="$POSTGRES_HEALTHCHECK_USER" \
    --set=admin_password="$POSTGRES_HEALTHCHECK_PASSWORD" <<-'SQL'
SELECT format('CREATE ROLE %I LOGIN PASSWORD %L NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS', :'app_user', :'app_password')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = :'app_user') \gexec

SELECT format('CREATE ROLE %I LOGIN PASSWORD %L NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS', :'migrator_user', :'migrator_password')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = :'migrator_user') \gexec

SELECT format('CREATE ROLE %I LOGIN PASSWORD %L NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS', :'backup_user', :'backup_password')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = :'backup_user') \gexec

SELECT format('CREATE ROLE %I LOGIN PASSWORD %L SUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION BYPASSRLS', :'admin_user', :'admin_password')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = :'admin_user') \gexec
SQL
