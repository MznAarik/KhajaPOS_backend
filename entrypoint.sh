#!/bin/sh

set -e

echo "Clearing old caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear

echo "Linking storage (safe)..."
php artisan storage:link || true

echo "Running migrations..."
php artisan migrate --force

echo "Generating Passport keys..."
php artisan passport:keys --force

echo "Optimizing app..."
php artisan config:cache
php artisan route:cache

echo "Starting server..."
apache2-foreground