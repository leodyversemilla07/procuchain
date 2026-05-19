#!/usr/bin/env bash
# Ensure the storage symlink exists for Laravel
set -e

APP_DIR="/var/app/current"

cd "$APP_DIR"

if [ ! -L public/storage ]; then
  echo "POSTDEPLOY: Creating storage:link symlink..."
  php artisan storage:link --force 2>&1 || echo "POSTDEPLOY: WARNING — storage:link failed"
else
  echo "POSTDEPLOY: storage symlink already exists"
fi
