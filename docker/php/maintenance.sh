#!/bin/sh
set -eu

while true; do
    php bin/console app:comptes:purger-desactives --env=prod --no-debug

    if [ "$(date +%m-%d)" = "10-10" ]; then
        php bin/console app:donnees:purger-campagne-precedente --env=prod --no-debug
    fi

    if [ "${MAINTENANCE_ONCE:-0}" = "1" ]; then
        exit 0
    fi

    sleep 86400
done
