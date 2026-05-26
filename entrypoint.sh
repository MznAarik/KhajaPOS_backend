#!/bin/sh

set -e

echo "Clearing old caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear

echo "Linking storage..."
php artisan storage:link || true

echo "Running migrations..."
php artisan migrate --force

echo "Optimizing app..."
php artisan config:cache
php artisan route:cache

echo "Starting server..."
apache2-foreground