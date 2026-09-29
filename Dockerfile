FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --classmap-authoritative

# Regenerar el autoload con el código de la app ya presente (el classmap
# autoritativo no busca en disco, así que debe incluir las clases de app/)
COPY app app
COPY database database
COPY routes routes
RUN composer dump-autoload --optimize --classmap-authoritative --no-interaction --no-scripts

# vite necesita artisan para generar las rutas de wayfinder
FROM php:8.4-cli-alpine AS frontend

# nodejs>=22: mínimo para Vite; hoy resuelve Node 24 en Alpine 3.24,
# y acepta majors futuros en vez de romper el build al actualizar la base
RUN apk add --no-cache "nodejs>=22" npm \
    # vite.config.ts invoca "php8.4" (binario del host de desarrollo); alias local
    && ln -sf "$(command -v php)" /usr/local/bin/php8.4

WORKDIR /app
COPY --from=vendor /app/vendor /app/vendor
COPY app app
COPY bootstrap bootstrap
COPY config config
COPY database database
COPY public public
COPY resources resources
COPY routes routes
COPY artisan composer.json composer.lock package.json package-lock.json .npmrc vite.config.ts tsconfig.json components.json .env.example ./
RUN cp .env.example .env \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache

RUN npm ci
RUN npm run build

FROM dunglas/frankenphp:1-php8.4-alpine

RUN install-php-extensions \
    intl \
    bcmath \
    opcache \
    pdo_pgsql

RUN printf 'opcache.enable=1\nopcache.validate_timestamps=0\nopcache.jit=tracing\nopcache.jit_buffer_size=64M\n' \
    > /usr/local/etc/php/conf.d/zz-prod-opcache.ini

WORKDIR /app

COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY --from=vendor /app/vendor /app/vendor
COPY --from=frontend /app/public/build /app/public/build
COPY . /app

RUN mkdir -p /app/storage/framework/cache/data /app/storage/framework/sessions /app/storage/framework/testing /app/storage/framework/views /app/storage/logs /app/storage/app \
    && chown -R www-data:www-data /app/storage /app/database /app/bootstrap/cache \
    && chmod +x /app/docker/entrypoint.sh /app/docker/worker-entrypoint.sh

ENV SERVER_NAME=:8000

ENTRYPOINT ["/app/docker/entrypoint.sh"]
