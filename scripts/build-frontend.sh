#!/usr/bin/env bash
# Build frontend assets locally before deploying to Elastic Beanstalk
# This avoids the npm install/build timeout on the EB instance

set -e

echo "Installing npm dependencies..."
npm install --no-audit --no-fund

echo "Building frontend assets..."
npm run build

echo "Frontend build complete — assets in public/build/"
echo "Commit these files before deploying to EB."
