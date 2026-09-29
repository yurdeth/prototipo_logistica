#!/bin/sh
set -e

cd /app

# Base de datos SQLite: crear archivo si no existe (p.ej. volumen vacío)
if [ "${DB_CONNECTION}" = "sqlite" ] || [ -z "${DB_CONNECTION}" ]; then
    DB_PATH="${DB_DATABASE:-database/database.sqlite}"
    if [ ! -f "$DB_PATH" ]; then
        mkdir -p "$(dirname "$DB_PATH")"
        touch "$DB_PATH"
        echo "SQLite creado en $DB_PATH"
    fi
fi

# Archivo de entorno base: crearlo a partir del ejemplo si no existe
if [ ! -f .env ]; then
    cp .env.example .env
    echo ".env creado desde .env.example"
fi

# Clave de aplicación: generar una si no se proporcionó
if [ -z "${APP_KEY}" ]; then
    echo "APP_KEY no definida, generando una..."
    php artisan key:generate --force --no-interaction
fi

php artisan migrate --force

# Claves OAuth de Passport: el volumen de storage puede venir vacío
if [ ! -f storage/oauth-private.key ]; then
    php artisan passport:keys --no-interaction
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Proceso principal: FrankenPHP (el worker de colas es un servicio aparte)
exec frankenphp run --config /etc/frankenphp/Caddyfile
