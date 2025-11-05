#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="/var/www/html"
cd "${ROOT_DIR}"

echo "Initializing application..."

# Create necessary directories
mkdir -p var/cache var/log var/data

# Create fresh database for test environment
if [ "${APP_ENV:-prod}" = "test" ]; then
    echo "Setting up test database..."
    rm -f var/data/webhooks.db
fi

# Set permissions before migrations
chown -R www-data:www-data var/
chmod -R 775 var/

# Run database migrations as www-data
echo "Running database migrations..."
su -s /bin/bash www-data -c "php bin/console doctrine:migrations:migrate --no-interaction --env=${APP_ENV:-prod}"

# Ensure permissions are correct after migrations
chown -R www-data:www-data var/
chmod -R 775 var/

echo "Starting Apache..."
exec apache2-foreground
