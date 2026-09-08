#!/bin/sh
set -eu

install -d -o www-data -g www-data -m 0770 /var/www/app/var/cache /var/www/app/var/log
chown -R www-data:www-data /var/www/app/var/cache /var/www/app/var/log

exec "$@"
