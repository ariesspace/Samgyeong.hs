FROM php:8.3-fpm-alpine

RUN apk add --no-cache sqlite-dev \
    && docker-php-ext-install pdo_sqlite

RUN { \
        echo 'upload_max_filesize=50M'; \
        echo 'post_max_size=50M'; \
        echo 'max_file_uploads=20'; \
    } > /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

RUN mkdir -p /var/www/html/storage/data /var/www/html/storage/uploads \
    && chown -R www-data:www-data /var/www/html/storage
