#!/bin/sh
set -eu

printf '{"event":"maintenance_started","timestamp":"%s"}\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
php bin/console app:thematiques:desactiver-expirees --env=prod --no-debug
php bin/console app:comptes:purger-desactives --env=prod --no-debug
php bin/console app:donnees:purger-campagne-precedente --env=prod --no-debug
printf '{"event":"maintenance_succeeded","timestamp":"%s"}\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
