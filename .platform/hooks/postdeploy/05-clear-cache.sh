#!/usr/bin/env bash
# Clear all Laravel caches after deployment
set -e

APP_DIR="/var/app/current"
cd "$APP_DIR"

echo "POSTDEPLOY: Clearing caches..."
php artisan config:cache 2>&1
php artisan route:cache 2>&1
php artisan view:cache 2>&1
echo "POSTDEPLOY: Caches cleared and recached"
