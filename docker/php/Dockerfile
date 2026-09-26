FROM php:8.3-fpm-alpine

RUN docker-php-ext-install pdo_mysql opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
