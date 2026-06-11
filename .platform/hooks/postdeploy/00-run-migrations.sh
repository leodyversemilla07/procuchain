#!/usr/bin/env bash
# Run pending migrations
set -e

APP_DIR="/var/app/current"
cd "$APP_DIR"

echo "POSTDEPLOY: Running migrations..."
php artisan migrate --force 2>&1 && echo "POSTDEPLOY: Migrations complete" \
  || echo "POSTDEPLOY: WARNING — migrations failed"
