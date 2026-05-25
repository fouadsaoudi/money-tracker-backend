#!/bin/sh
set -e

cd /var/www/html

composer install --no-interaction
php artisan key:generate --force
php artisan storage:link || true

echo "Waiting for MySQL at ${DB_HOST:-db}:${DB_PORT:-3306}..."
until mysqladmin ping -h"${DB_HOST:-db}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME:-root}" -p"${DB_PASSWORD}" --silent; do
  sleep 2
done

echo "Running migrations (with retry)..."
until php artisan migrate --force; do
  echo "Migration failed, retrying in 3s..."
  sleep 3
done

php artisan optimize

exec php artisan serve --host=0.0.0.0 --port=8080
