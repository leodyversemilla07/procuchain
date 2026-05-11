#!/usr/bin/env bash
# Build frontend assets with Vite during prebuild phase
# CWD is /var/app/staging/ — the staged application source
# This runs BEFORE the app is configured and deployed

set -e

echo "Installing npm dependencies..."
npm install --no-audit --no-fund

echo "Building frontend assets..."
npm run build

echo "Frontend build complete — assets in public/build/"
