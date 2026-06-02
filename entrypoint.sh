#!/bin/sh
set -e

echo "Starting Laravel container..."

# Wait for DB
if [ "$DB_CONNECTION" = "mysql" ] || [ "$DB_CONNECTION" = "pgsql" ]; then
  echo "Waiting for database..."
    sleep 5
    fi

    echo "Clearing caches..."
    php artisan config:clear || true
    php artisan cache:clear || true
    php artisan route:clear || true

    # Fresh migration takes priority
    if [ "$RUN_FRESH_MIGRATIONS" = "true" ]; then
      echo "Running fresh migrations..."
        php artisan migrate:fresh --force

          if [ "$RUN_SEEDERS" = "true" ]; then
              echo "Seeding database..."
                  php artisan db:seed --force
                    fi

                    elif [ "$RUN_MIGRATIONS" = "true" ]; then
                      echo "Running migrations..."
                        php artisan migrate --force

                          if [ "$RUN_SEEDERS" = "true" ]; then
                              echo "Seeding database..."
                                  php artisan db:seed --force
                                    fi
                                    fi

                                    # Passport setup (only if explicitly enabled)
                                    if [ "$RUN_PASSPORT_INSTALL" = "true" ]; then
                                      echo "Generating Passport keys..."
                                        php artisan passport:keys --force

                                          echo "Creating personal access client..."
                                            php artisan passport:client --personal --name="KhajaPOS Personal Access Client"
                                            fi

                                            echo "Optimizing Laravel..."
                                            php artisan config:cache
                                            php artisan route:cache

                                            echo "Starting Apache..."
                                            exec apache2-foreground