#!/bin/sh
set -eu

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist --no-progress
fi

if [ ! -f assets/vendor/installed.php ]; then
    php bin/console importmap:install --no-interaction
fi

if [ ! -f public/assets/manifest.json ]; then
    php bin/console asset-map:compile --no-interaction
fi

install -d -o www-data -g www-data -m 0770 /var/www/app/var/cache /var/www/app/var/log
chown -R www-data:www-data /var/www/app/var/cache /var/www/app/var/log

exec "$@"
