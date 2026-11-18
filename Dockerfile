FROM php:8.4-cli-alpine
RUN apk add --no-cache git unzip libpq-dev icu-dev oniguruma-dev \
    && docker-php-ext-install pdo_pgsql mbstring intl pcntl bcmath \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && addgroup -S -g 10001 app && adduser -S -u 10001 -G app app
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts
COPY . .
RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R app:app storage bootstrap/cache \
    && chmod -R u+rwX storage bootstrap/cache
USER app
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
