#!/usr/bin/env bash
set -e

STAGING_DIR="/var/app/staging"

echo "PREDEPLOY: Installing Node.js and building frontend assets..."

# Ensure Node.js 24.x is installed (AL2023 has nodejs24 in its repos)
CURRENT_NODE=$(node -v 2>/dev/null || echo "none")
echo "PREDEPLOY: Current Node version: $CURRENT_NODE"

case "$CURRENT_NODE" in
  v24*) echo "PREDEPLOY: Node.js 24 already installed" ;;
  *)
    echo "PREDEPLOY: Installing Node.js 24.x..."
    dnf install -y nodejs24 nodejs24-npm >/dev/null 2>&1
    if [ -f /usr/bin/node-24 ]; then
      alternatives --set node /usr/bin/node-24 2>/dev/null || true
    fi
    ;;
esac

echo "PREDEPLOY: Node version: $(node -v)"
echo "PREDEPLOY: npm version: $(npm -v)"

cd "$STAGING_DIR"

echo "PREDEPLOY: Running npm install..."
npm install --production=false 2>&1 | tail -10

echo "PREDEPLOY: Running npm run build..."
npm run build 2>&1 | tail -10

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
