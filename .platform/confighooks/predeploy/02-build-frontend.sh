#!/usr/bin/env bash
set -e

STAGING_DIR="/var/app/staging"

echo "PREDEPLOY: Building frontend assets..."
echo "PREDEPLOY: Node version: $(node -v)"
echo "PREDEPLOY: npm version: $(npm -v)"

cd "$STAGING_DIR"

echo "PREDEPLOY: Running npm install..."
npm install --production=false --legacy-peer-deps

echo "PREDEPLOY: Running npm run build..."
npm run build

if [ ! -f "$STAGING_DIR/public/build/manifest.json" ]; then
  echo "PREDEPLOY: ERROR — Vite manifest not found after build!"
  ls -la "$STAGING_DIR/public/build/" 2>/dev/null || echo "PREDEPLOY: public/build/ directory missing"
  exit 1
fi

echo "PREDEPLOY: Vite manifest verified — $(ls -1 "$STAGING_DIR/public/build/assets/" | wc -l) asset files"

echo "PREDEPLOY: Frontend assets built successfully"
