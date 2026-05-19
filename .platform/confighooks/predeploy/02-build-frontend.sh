#!/usr/bin/env bash
# Build frontend assets on the EB instance during deployment.
# This runs as a predeploy hook in /var/app/staging/.
# After all predeploy hooks succeed, EB promotes staging → current.
set -e

STAGING_DIR="/var/app/staging"

echo "PREDEPLOY: Installing Node.js and building frontend assets..."

# Check if Node.js is already installed
if ! command -v node &> /dev/null; then
  echo "PREDEPLOY: Installing Node.js 20.x..."
  curl -fsSL https://rpm.nodesource.com/setup_20.x | bash - &>/dev/null
  yum install -y nodejs &>/dev/null
fi

echo "PREDEPLOY: Node version: $(node -v)"
echo "PREDEPLOY: npm version: $(npm -v)"

cd "$STAGING_DIR"

# Install dependencies
echo "PREDEPLOY: Running npm install..."
npm install --production=false 2>&1 | tail -3

# Build assets
echo "PREDEPLOY: Running npm run build..."
npm run build 2>&1 | tail -5

# Verify the build output exists
if [ ! -f "$STAGING_DIR/public/build/manifest.json" ]; then
  echo "PREDEPLOY: ERROR — Vite manifest not found after build!"
  ls -la "$STAGING_DIR/public/build/" 2>/dev/null || echo "PREDEPLOY: public/build/ directory missing"
  exit 1
fi

echo "PREDEPLOY: Vite manifest verified — $(ls -1 "$STAGING_DIR/public/build/assets/" | wc -l) asset files"

# Copy pdf.js worker to public/ (react-pdf "Option 2: Copy worker to public directory")
echo "PREDEPLOY: Copying pdf.js worker to public/..."
cp "$STAGING_DIR/node_modules/pdfjs-dist/build/pdf.worker.min.mjs" "$STAGING_DIR/public/pdf.worker.min.mjs"
echo "PREDEPLOY: pdf.js worker copied ($(wc -c < "$STAGING_DIR/public/pdf.worker.min.mjs") bytes)"

echo "PREDEPLOY: Frontend assets built successfully"
