#!/bin/sh
set -e

cd /var/www/html

composer install --no-interaction
php artisan key:generate --force
php artisan storage:link || true
php artisan migrate --force
php artisan optimize

exec php artisan serve --host=0.0.0.0 --port=8080
