#!/bin/sh
set -eu

while true; do
    php bin/console app:thematiques:desactiver-expirees --env=prod --no-debug
    php bin/console app:comptes:purger-desactives --env=prod --no-debug
    php bin/console app:donnees:purger-campagne-precedente --env=prod --no-debug

    if [ "${MAINTENANCE_ONCE:-0}" = "1" ]; then
        exit 0
    fi

    sleep 86400
done
