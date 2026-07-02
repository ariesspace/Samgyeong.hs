FROM php:8.3-fpm-alpine

RUN apk add --no-cache sqlite-dev \
    && docker-php-ext-install pdo_sqlite

WORKDIR /var/www/html

RUN mkdir -p /var/www/html/storage/data /var/www/html/storage/uploads \
    && chown -R www-data:www-data /var/www/html/storage
