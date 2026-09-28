#!/bin/sh

set -eu

ci_env_file=${CI_ENV_FILE:-.ci.env}
: >"$ci_env_file"
chmod 0600 "$ci_env_file"

cp .env.example .env
cp app/.env.example app/.env

remplacer_variable() (
    fichier=${1:?Le fichier doit etre renseigne}
    variable=${2:?La variable doit etre renseignee}
    valeur=${3-}
    fichier_temporaire=$(mktemp "${fichier}.XXXXXX")
    sed "s/^${variable}=.*/${variable}=${valeur}/" "$fichier" >"$fichier_temporaire"
    mv "$fichier_temporaire" "$fichier"
)

ajouter_variable() {
    variable=$1
    valeur=$2
    printf '%s=%s\n' "$variable" "$valeur" >>"$ci_env_file"
}

app_secret_ci=$(openssl rand -hex 32)
remplacer_variable app/.env APP_ENV prod
remplacer_variable app/.env APP_SECRET "$app_secret_ci"
chmod 0644 app/.env
for variable in \
    POSTGRES_PASSWORD \
    POSTGRES_APP_PASSWORD \
    POSTGRES_MIGRATOR_PASSWORD \
    POSTGRES_BACKUP_PASSWORD \
    POSTGRES_HEALTHCHECK_PASSWORD; do
    ajouter_variable "$variable" "$(openssl rand -hex 24)"
done

# GitHub Actions n’importe pas automatiquement les variables écrites dans un
# fichier dotenv. Les publier via GITHUB_ENV les rend disponibles aux étapes
# suivantes sans les afficher dans les journaux.
if [ -n "${GITHUB_ENV:-}" ]; then
    cat "$ci_env_file" >>"$GITHUB_ENV"
fi

printf 'Configuration CI generee dans %s (valeurs masquees).\n' "$ci_env_file"
