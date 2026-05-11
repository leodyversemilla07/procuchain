#!/usr/bin/env bash
# Build frontend assets with Vite after deployment
# EB PHP platform only runs composer install — we need to handle npm ourselves

set -e

cd /var/app/current

echo "Installing npm dependencies..."
npm install

echo "Building frontend assets..."
npm run build

echo "Frontend build complete"
