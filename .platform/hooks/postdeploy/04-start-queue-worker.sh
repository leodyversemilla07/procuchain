#!/usr/bin/env bash
# Install and start the Laravel queue worker systemd service
set -e

APP_DIR="/var/app/current"
SERVICE_FILE="/etc/systemd/system/laravel-queue-worker.service"

echo "POSTDEPLOY: Setting up queue worker service..."

# Install the service unit if it doesn't exist or if the bundle has a newer version
if [ -f "$APP_DIR/.platform/worker/laravel-queue-worker.service" ]; then
  cp "$APP_DIR/.platform/worker/laravel-queue-worker.service" "$SERVICE_FILE"
  echo "POSTDEPLOY: Service unit installed"
else
  echo "POSTDEPLOY: WARNING — .platform/worker/laravel-queue-worker.service not found in bundle"
  exit 1
fi

systemctl daemon-reload
systemctl enable laravel-queue-worker

# Restart (or start for the first time) the queue worker
systemctl restart laravel-queue-worker && echo "POSTDEPLOY: Queue worker started" \
  || echo "POSTDEPLOY: WARNING — queue worker start failed"
