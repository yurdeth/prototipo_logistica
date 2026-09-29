#!/bin/sh
# Worker de colas: servicio aparte en docker-compose (se reinicia si muere).
set -e

cd /app

# El contenedor "app" prepara la base de datos al arrancar; esperar hasta que esté lista.
until php artisan migrate --force > /dev/null 2>&1; do
    echo "Esperando a que la base de datos esté lista..."
    sleep 3
done

exec php artisan queue:work --tries=3
