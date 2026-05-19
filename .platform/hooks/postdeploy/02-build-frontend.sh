#!/usr/bin/env bash
# Ensure Vite build artifacts exist (needed for config-only updates that skip predeploy)
set -e

APP_DIR="/var/app/current"
BUILD_DIR="$APP_DIR/public/build"
MANIFEST="$BUILD_DIR/.vite/manifest.json"

# If manifest already exists, skip build
if [ -f "$MANIFEST" ]; then
  echo "CONFIG-POSTDEPLOY: Vite build artifacts already exist, skipping build"
  exit 0
fi

echo "CONFIG-POSTDEPLOY: Vite manifest missing, building frontend..."
cd "$APP_DIR"

# Install Node dependencies if needed
if [ ! -d "node_modules" ]; then
  npm install --production=false 2>&1 || npm install 2>&1
fi

# Build frontend
npm run build 2>&1

if [ -f "$MANIFEST" ]; then
  echo "CONFIG-POSTDEPLOY: Vite build successful"
else
  echo "CONFIG-POSTDEPLOY: WARNING — Vite build failed, manifest not found"
fi
