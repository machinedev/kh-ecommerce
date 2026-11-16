FROM composer:2 AS vendor
WORKDIR /app
COPY backend/composer.json ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --ignore-platform-reqs

FROM php:8.3-fpm-alpine

RUN apk add --no-cache libxml2-dev oniguruma-dev \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-enable pdo_mysql

WORKDIR /var/www

COPY --from=vendor /app/vendor /var/www/vendor
COPY backend/ /var/www/

RUN mkdir -p /var/www/uploads \
    && chown -R www-data:www-data /var/www/uploads

EXPOSE 9000

CMD ["php-fpm"]
