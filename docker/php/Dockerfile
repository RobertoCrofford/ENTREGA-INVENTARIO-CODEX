FROM composer:2 AS composer_dependencies

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts

FROM node:22-alpine AS frontend_assets

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci

COPY resources ./resources
COPY vite.config.js ./

RUN npm run build

FROM php:8.4-fpm-alpine

WORKDIR /var/www/html

RUN apk add --no-cache icu-libs libzip oniguruma \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev oniguruma-dev \
    && docker-php-ext-install -j1 bcmath intl pdo_mysql zip \
    && apk del .build-deps

COPY --from=composer_dependencies /app/vendor ./vendor
COPY . .
COPY --from=frontend_assets /app/public/build ./public/build
COPY docker/php/php.ini /usr/local/etc/php/conf.d/inventory.ini
COPY docker/php/entrypoint.sh /usr/local/bin/inventory-entrypoint

RUN php artisan package:discover --ansi \
    && chmod +x /usr/local/bin/inventory-entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["inventory-entrypoint"]
CMD ["php-fpm"]
