#!/usr/bin/env bash
# Start/restart the queue worker systemd service
set -e

echo "POSTDEPLOY: Starting queue worker..."
systemctl daemon-reload 2>/dev/null
systemctl enable laravel-queue-worker 2>/dev/null
systemctl restart laravel-queue-worker 2>/dev/null && echo "POSTDEPLOY: Queue worker started" \
  || echo "POSTDEPLOY: WARNING — queue worker start failed"
