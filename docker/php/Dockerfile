ARG PHP_VERSION=8.4.27
ARG NGINX_VERSION=1.30.6-alpine-slim
# À renseigner uniquement après vérification de l’image officielle linux/amd64.
ARG PHP_IMAGE_DIGEST=sha256:64aff665308e23ba8804a6c43f75837e9bcdcc31735fc0f74af8899fe5304b07
ARG COMPOSER_IMAGE_DIGEST=sha256:4d71c3c2109c61d5415544264b59ad4087e4c5b7244481723664138fd36d5040
ARG NGINX_IMAGE_DIGEST=sha256:3028fc1419f99b95c1c69f844bbaf49dc1b881750c51b497fc721ac4f02a14e6

FROM composer:2@${COMPOSER_IMAGE_DIGEST} AS composer-bin

FROM php:${PHP_VERSION}-fpm@${PHP_IMAGE_DIGEST} AS php-base

COPY --chmod=0755 docker/php/verify-php-security.sh /usr/local/bin/verify-php-security
RUN verify-php-security

ARG OS_PACKAGE_REFRESH=manual

RUN test -n "$OS_PACKAGE_REFRESH" \
    && apt-get update \
    && apt-get upgrade -y \
    && apt-get install -y --no-install-recommends libicu-dev libpq-dev libzip-dev \
    && docker-php-ext-install intl opcache pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-benevole-jambville.ini
WORKDIR /var/www/app

FROM php-base AS php-development

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
COPY docker/php/entrypoint.sh /usr/local/bin/benevole-jambville-entrypoint
RUN chmod 0755 /usr/local/bin/benevole-jambville-entrypoint

ENTRYPOINT ["benevole-jambville-entrypoint"]
CMD ["php-fpm"]

FROM php-base AS app-builder

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
COPY app/composer.json app/composer.lock app/symfony.lock ./
RUN composer install \
        --no-dev \
        --no-autoloader \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --no-progress

COPY app/ ./

RUN : > .env \
    && composer dump-autoload \
        --no-dev \
        --classmap-authoritative \
        --no-scripts \
        --no-interaction \
    && APP_ENV=prod \
       APP_SECRET=build-only-not-used-at-runtime \
       DATABASE_HOST=database \
       DATABASE_NAME=benevole_jambville \
       DATABASE_TEST_NAME=benevole_jambville_test \
       DATABASE_USER=benevole_jambville_app \
       DATABASE_PASSWORD=build-only-not-used-at-runtime \
       DEFAULT_URI=http://localhost:8081 \
       MAILER_DSN=null://null \
       MAILER_FROM=no-reply@example.com \
       TRUSTED_HOST_PATTERN='^(localhost|127[.]0[.]0[.]1)$' \
       TRUSTED_PROXIES=127.0.0.1 \
       php bin/console importmap:install --no-interaction \
    && APP_ENV=prod \
       APP_SECRET=build-only-not-used-at-runtime \
       DATABASE_HOST=database \
       DATABASE_NAME=benevole_jambville \
       DATABASE_TEST_NAME=benevole_jambville_test \
       DATABASE_USER=benevole_jambville_app \
       DATABASE_PASSWORD=build-only-not-used-at-runtime \
       DEFAULT_URI=http://localhost:8081 \
       MAILER_DSN=null://null \
       MAILER_FROM=no-reply@example.com \
       TRUSTED_HOST_PATTERN='^(localhost|127[.]0[.]0[.]1)$' \
       TRUSTED_PROXIES=127.0.0.1 \
       php bin/console asset-map:compile --env=prod --no-debug --no-interaction \
    && rm -rf var/cache/* var/log/* \
    && install -d -o www-data -g www-data -m 0770 var/cache var/log

FROM php-base AS php-production

COPY docker/php/maintenance.sh /usr/local/bin/benevole-jambville-maintenance
COPY --from=app-builder --chown=www-data:www-data /var/www/app /var/www/app
RUN chmod 0755 /usr/local/bin/benevole-jambville-maintenance

USER www-data
ENTRYPOINT ["docker-php-entrypoint"]
CMD ["php-fpm"]

FROM nginx:${NGINX_VERSION}@${NGINX_IMAGE_DIGEST} AS nginx-production

ARG OS_PACKAGE_REFRESH=2026-10-21

RUN test -n "$OS_PACKAGE_REFRESH" \
    && apk upgrade --no-cache

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app-builder /var/www/app/public /var/www/app/public
