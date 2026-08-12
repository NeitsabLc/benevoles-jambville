#!/bin/sh
set -eu

for variable in \
    POSTGRES_APP_USER POSTGRES_APP_PASSWORD \
    POSTGRES_MIGRATOR_USER POSTGRES_MIGRATOR_PASSWORD \
    POSTGRES_BACKUP_USER POSTGRES_BACKUP_PASSWORD \
    POSTGRES_HEALTHCHECK_USER POSTGRES_HEALTHCHECK_PASSWORD
do
    case "$variable" in
        POSTGRES_APP_USER) valeur=${POSTGRES_APP_USER:-} ;;
        POSTGRES_APP_PASSWORD) valeur=${POSTGRES_APP_PASSWORD:-} ;;
        POSTGRES_MIGRATOR_USER) valeur=${POSTGRES_MIGRATOR_USER:-} ;;
        POSTGRES_MIGRATOR_PASSWORD) valeur=${POSTGRES_MIGRATOR_PASSWORD:-} ;;
        POSTGRES_BACKUP_USER) valeur=${POSTGRES_BACKUP_USER:-} ;;
        POSTGRES_BACKUP_PASSWORD) valeur=${POSTGRES_BACKUP_PASSWORD:-} ;;
        POSTGRES_HEALTHCHECK_USER) valeur=${POSTGRES_HEALTHCHECK_USER:-} ;;
        POSTGRES_HEALTHCHECK_PASSWORD) valeur=${POSTGRES_HEALTHCHECK_PASSWORD:-} ;;
    esac
    if [ -z "$valeur" ]; then
        echo "$variable doit etre defini avant la bascule des roles." >&2
        exit 2
    fi
done

if [ "$POSTGRES_USER" = "$POSTGRES_APP_USER" ] \
    || [ "$POSTGRES_USER" = "$POSTGRES_MIGRATOR_USER" ] \
    || [ "$POSTGRES_USER" = "$POSTGRES_BACKUP_USER" ] \
    || [ "$POSTGRES_APP_USER" = "$POSTGRES_MIGRATOR_USER" ] \
    || [ "$POSTGRES_APP_USER" = "$POSTGRES_BACKUP_USER" ] \
    || [ "$POSTGRES_MIGRATOR_USER" = "$POSTGRES_BACKUP_USER" ] \
    || [ "$POSTGRES_USER" = "$POSTGRES_HEALTHCHECK_USER" ] \
    || [ "$POSTGRES_APP_USER" = "$POSTGRES_HEALTHCHECK_USER" ] \
    || [ "$POSTGRES_MIGRATOR_USER" = "$POSTGRES_HEALTHCHECK_USER" ] \
    || [ "$POSTGRES_BACKUP_USER" = "$POSTGRES_HEALTHCHECK_USER" ]; then
    echo 'Les roles PostgreSQL doivent etre distincts.' >&2
    exit 2
fi

verify_login() {
    role=$1
    password=$2

    actual_role=$(PGPASSWORD="$password" psql \
        --host=127.0.0.1 \
        --username="$role" \
        --dbname="$POSTGRES_DB" \
        --tuples-only \
        --no-align \
        --command='SELECT current_user')

    if [ "$actual_role" != "$role" ]; then
        echo "La connexion attendue avec le role $role n'a pas ete verifiee." >&2
        exit 1
    fi
}

PGPASSWORD="$POSTGRES_PASSWORD" psql \
    --host=127.0.0.1 \
    --username="$POSTGRES_USER" \
    --dbname="$POSTGRES_DB" \
    --set=ON_ERROR_STOP=1 \
    --set=admin_user="$POSTGRES_HEALTHCHECK_USER" \
    --set=admin_password="$POSTGRES_HEALTHCHECK_PASSWORD" <<-'SQL'
SELECT format('CREATE ROLE %I WITH LOGIN SUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION BYPASSRLS PASSWORD %L', :'admin_user', :'admin_password')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = :'admin_user') \gexec
SELECT format('ALTER ROLE %I WITH LOGIN SUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION BYPASSRLS PASSWORD %L', :'admin_user', :'admin_password') \gexec
SQL

verify_login "$POSTGRES_APP_USER" "$POSTGRES_APP_PASSWORD"
verify_login "$POSTGRES_MIGRATOR_USER" "$POSTGRES_MIGRATOR_PASSWORD"
verify_login "$POSTGRES_BACKUP_USER" "$POSTGRES_BACKUP_PASSWORD"
verify_login "$POSTGRES_HEALTHCHECK_USER" "$POSTGRES_HEALTHCHECK_PASSWORD"

PGPASSWORD="$POSTGRES_PASSWORD" psql \
    --host=127.0.0.1 \
    --username="$POSTGRES_USER" \
    --dbname="$POSTGRES_DB" \
    --single-transaction \
    --set=ON_ERROR_STOP=1 \
    --set=database_name="$POSTGRES_DB" \
    --set=bootstrap_user="$POSTGRES_USER" \
    --set=app_user="$POSTGRES_APP_USER" \
    --set=migrator_user="$POSTGRES_MIGRATOR_USER" \
    --set=backup_user="$POSTGRES_BACKUP_USER" \
    --set=admin_user="$POSTGRES_HEALTHCHECK_USER" \
    --set=admin_password="$POSTGRES_HEALTHCHECK_PASSWORD" <<-'SQL'
SELECT format('ALTER ROLE %I WITH NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS', :'app_user') \gexec
SELECT format('ALTER ROLE %I WITH NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS', :'migrator_user') \gexec
SELECT format('ALTER ROLE %I WITH NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS', :'backup_user') \gexec
SELECT format('ALTER ROLE %I WITH LOGIN SUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION BYPASSRLS PASSWORD %L', :'admin_user', :'admin_password') \gexec

SELECT format('GRANT CONNECT ON DATABASE %I TO %I, %I, %I', :'database_name', :'app_user', :'migrator_user', :'backup_user') \gexec
SELECT format('GRANT USAGE ON SCHEMA benevole_jambville TO %I', :'app_user') \gexec
SELECT format('GRANT USAGE, CREATE ON SCHEMA benevole_jambville, public TO %I', :'migrator_user') \gexec
SELECT format('GRANT USAGE ON SCHEMA benevole_jambville, public TO %I', :'backup_user') \gexec
SELECT format('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA benevole_jambville TO %I', :'app_user') \gexec
SELECT format('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA benevole_jambville TO %I', :'app_user') \gexec
SELECT format('GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA benevole_jambville, public TO %I', :'migrator_user') \gexec
SELECT format('GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA benevole_jambville, public TO %I', :'migrator_user') \gexec
SELECT format('GRANT SELECT ON ALL TABLES IN SCHEMA benevole_jambville, public TO %I', :'backup_user') \gexec
SELECT format('GRANT SELECT ON ALL SEQUENCES IN SCHEMA benevole_jambville, public TO %I', :'backup_user') \gexec

SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA benevole_jambville GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO %I', :'migrator_user', :'app_user') \gexec
SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA benevole_jambville GRANT USAGE, SELECT ON SEQUENCES TO %I', :'migrator_user', :'app_user') \gexec
SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA benevole_jambville GRANT SELECT ON TABLES TO %I', :'migrator_user', :'backup_user') \gexec
SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA benevole_jambville GRANT SELECT ON SEQUENCES TO %I', :'migrator_user', :'backup_user') \gexec

SELECT format('ALTER ROLE %I IN DATABASE %I SET search_path TO benevole_jambville, public', :'app_user', :'database_name') \gexec
SELECT format('ALTER ROLE %I IN DATABASE %I SET search_path TO benevole_jambville, public', :'migrator_user', :'database_name') \gexec
SELECT format('ALTER ROLE %I IN DATABASE %I SET search_path TO benevole_jambville, public', :'backup_user', :'database_name') \gexec

SELECT format('ALTER DATABASE %I OWNER TO %I', :'database_name', :'migrator_user') \gexec
SELECT format('ALTER SCHEMA benevole_jambville OWNER TO %I', :'migrator_user') \gexec

SELECT format(
    'ALTER %s %I.%I OWNER TO %I',
    CASE classe.relkind
        WHEN 'S' THEN 'SEQUENCE'
        WHEN 'v' THEN 'VIEW'
        WHEN 'm' THEN 'MATERIALIZED VIEW'
        WHEN 'f' THEN 'FOREIGN TABLE'
        ELSE 'TABLE'
    END,
    espace.nspname,
    classe.relname,
    :'migrator_user'
)
FROM pg_class classe
JOIN pg_namespace espace ON espace.oid = classe.relnamespace
WHERE (
        espace.nspname = 'benevole_jambville'
        OR (espace.nspname = 'public' AND classe.relname IN ('databasechangelog', 'databasechangeloglock'))
    )
  AND classe.relkind IN ('r', 'p', 'S', 'v', 'm', 'f')
  AND pg_get_userbyid(classe.relowner) = :'bootstrap_user'
ORDER BY espace.nspname, classe.relkind, classe.relname
\gexec

SELECT format(
    'ALTER %s %I.%I(%s) OWNER TO %I',
    CASE procedure.prokind WHEN 'p' THEN 'PROCEDURE' WHEN 'a' THEN 'AGGREGATE' ELSE 'FUNCTION' END,
    espace.nspname,
    procedure.proname,
    pg_get_function_identity_arguments(procedure.oid),
    :'migrator_user'
)
FROM pg_proc procedure
JOIN pg_namespace espace ON espace.oid = procedure.pronamespace
WHERE espace.nspname = 'benevole_jambville'
  AND pg_get_userbyid(procedure.proowner) = :'bootstrap_user'
ORDER BY procedure.proname
\gexec

SELECT format(
    'ALTER %s %I.%I OWNER TO %I',
    CASE type.typtype WHEN 'd' THEN 'DOMAIN' ELSE 'TYPE' END,
    espace.nspname,
    type.typname,
    :'migrator_user'
)
FROM pg_type type
JOIN pg_namespace espace ON espace.oid = type.typnamespace
WHERE espace.nspname = 'benevole_jambville'
  AND type.typrelid = 0
  AND type.typtype IN ('d', 'e', 'm', 'r')
  AND pg_get_userbyid(type.typowner) = :'bootstrap_user'
ORDER BY type.typname
\gexec

SELECT format('REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA benevole_jambville, public FROM %I', :'bootstrap_user') \gexec
SELECT format('REVOKE ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA benevole_jambville, public FROM %I', :'bootstrap_user') \gexec
SELECT format('REVOKE ALL ON SCHEMA benevole_jambville FROM %I', :'bootstrap_user') \gexec
SELECT format('ALTER ROLE %I NOLOGIN NOCREATEDB NOCREATEROLE NOREPLICATION', :'bootstrap_user') \gexec
SQL

echo "Les objets appartiennent au migrateur et le compte d'amorcage est desactive."
