#!/usr/bin/env bash
set -ex

STAGING_DIR="/var/app/staging"

echo "PREDEPLOY: Building frontend assets..."
echo "PREDEPLOY: Node version: $(node -v)"
echo "PREDEPLOY: npm version: $(npm -v)"
echo "PREDEPLOY: PATH: $PATH"
echo "PREDEPLOY: which node: $(which node 2>&1)"
echo "PREDEPLOY: which npm: $(which npm 2>&1)"

cd "$STAGING_DIR"

echo "PREDEPLOY: Running npm install..."
npm install --production=false

echo "PREDEPLOY: Running npm run build..."
npm run build

if [ ! -f "$STAGING_DIR/public/build/manifest.json" ]; then
  echo "PREDEPLOY: ERROR — Vite manifest not found after build!"
  ls -la "$STAGING_DIR/public/build/" 2>/dev/null || echo "PREDEPLOY: public/build/ directory missing"
  exit 1
fi

echo "PREDEPLOY: Vite manifest verified — $(ls -1 "$STAGING_DIR/public/build/assets/" | wc -l) asset files"

echo "PREDEPLOY: Copying pdf.js worker to public/..."
cp "$STAGING_DIR/node_modules/pdfjs-dist/build/pdf.worker.min.mjs" "$STAGING_DIR/public/pdf.worker.min.mjs"
echo "PREDEPLOY: pdf.js worker copied ($(wc -c < "$STAGING_DIR/public/pdf.worker.min.mjs") bytes)"

echo "PREDEPLOY: Frontend assets built successfully"
