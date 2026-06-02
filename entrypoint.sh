#!/bin/sh
set -e

echo "Starting Laravel container..."

# Wait for DB (optional but useful on cloud)
if [ "$DB_CONNECTION" = "mysql" ] || [ "$DB_CONNECTION" = "pgsql" ]; then
  echo "Waiting for database..."
  sleep 5
fi

echo "Clearing cached config..."
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true

# Run migrations (IMPORTANT: no migrate:fresh)
if [ "$RUN_MIGRATIONS" = "true" ]; then
  echo "Running migrations..."
  php artisan migrate --force
fi

# Seed only if explicitly enabled (DO NOT auto seed in production normally)
if [ "$RUN_SEEDERS" = "true" ]; then
  echo "Seeding database..."
  php artisan db:seed --force
fi

# Passport setup ONLY if explicitly enabled (danger zone)
if [ "$RUN_PASSPORT_INSTALL" = "true" ]; then
  echo "Installing Passport..."
  php artisan passport:keys --force || true
  php artisan passport:client --personal --force || true
fi

echo "Optimizing Laravel..."
php artisan config:cache
php artisan route:cache

echo "Starting Apache..."
apache2-foreground