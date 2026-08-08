ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git libicu-dev libpq-dev libzip-dev unzip \
    && docker-php-ext-install intl opcache pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-benevole-jambville.ini
WORKDIR /var/www/app
CMD ["php-fpm"]

