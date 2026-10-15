#!/bin/sh
set -e

# Arregla permisos de las carpetas que Laravel necesita escribir.
# Se ejecuta como root al arrancar el contenedor php.
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# Ejecuta el comando original (php-fpm)
exec docker-php-entrypoint "$@"
