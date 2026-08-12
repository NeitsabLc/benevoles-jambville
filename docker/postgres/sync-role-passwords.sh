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
SELECT format('ALTER ROLE %I PASSWORD %L', :'app_user', :'app_password') \gexec
SELECT format('ALTER ROLE %I PASSWORD %L', :'migrator_user', :'migrator_password') \gexec
SELECT format('ALTER ROLE %I PASSWORD %L', :'backup_user', :'backup_password') \gexec
SELECT format('ALTER ROLE %I PASSWORD %L', :'admin_user', :'admin_password') \gexec
SQL

echo "Les mots de passe des rôles applicatif, migrateur, sauvegarde et administratif ont été synchronisés."
