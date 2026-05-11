#!/usr/bin/env bash
# Build frontend assets with Vite during pre-deploy phase
# This runs BEFORE the app goes live, so it's within the deployment timeout
# MUST complete quickly — no npm install here, that's done in 01-install-deps.sh

set -e

APP_DIR=/var/app/staging
cd "$APP_DIR"

echo "Installing npm dependencies..."
npm install --no-audit --no-fund

echo "Building frontend assets..."
npm run build

echo "Frontend build complete — assets in public/build/"
