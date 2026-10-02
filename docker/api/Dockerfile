FROM composer:2 AS composer
FROM php:8.4-fpm-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libonig-dev libxml2-dev unzip \
    && docker-php-ext-install pdo_pgsql mbstring dom \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader
COPY backend/ ./
RUN mkdir -p /var/lib/php/sessions && chown www-data:www-data /var/lib/php/sessions && chmod 700 /var/lib/php/sessions
COPY docker/api/php.ini /usr/local/etc/php/conf.d/board.ini
COPY docker/api/entrypoint.sh /usr/local/bin/board-entrypoint
# Un checkout Windows ou une extraction ZIP peut apporter des CRLF.
# Normaliser avant de laisser le noyau interpréter le shebang /bin/sh.
RUN sed -i 's/\r$//' /usr/local/bin/board-entrypoint \
    && chmod +x /usr/local/bin/board-entrypoint
ENTRYPOINT ["board-entrypoint"]
CMD ["php-fpm"]
