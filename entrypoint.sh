#!/bin/sh

echo "Linking storage..."
php artisan storage:link || true

echo "Running migrations..."
php artisan migrate --force

echo "Clearing caches..."
php artisan config:clear
php artisan cache:clear

echo "Seeding database..."
php artisan db:seed --force

echo "Starting server..."
apache2-foreground