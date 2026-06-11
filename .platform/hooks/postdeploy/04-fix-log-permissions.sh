#!/usr/bin/env bash
# Fix storage permissions so queue workers (webapp) can always write logs
# even after SSM commands (run as root) create files in storage/
set -e

APP_DIR="/var/app/current"
cd "$APP_DIR"

echo "POSTDEPLOY: Fixing storage permissions..."
chown -R webapp:webapp storage/
chmod -R 775 storage/
echo "POSTDEPLOY: Storage permissions fixed"
