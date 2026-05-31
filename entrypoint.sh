#!/bin/sh

set -e

echo "Clearing old caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "Running migrations..."
    php artisan migrate:fresh --force
fi

echo "Seeding database..."
php artisan db:seed --force

echo "Generating app key..."
php artisan key:generate

echo "Generating passport keys..."
php artisan passport:keys || true

echo "Adding passport client..."
php artisan passport:client || true

echo "Adding passport personal access client..."
php artisan passport:client --name="Laravel Personal Access Client" --personal || true

echo "Linking storage..."
php artisan storage:link || true

echo "Optimizing app..."
php artisan config:cache
php artisan route:cache

echo "Starting server..."
apache2-foreground
