#!/usr/bin/env bash
# Run Laravel database migrations automatically after each deployment.
# This ensures new tables/columns (e.g., audit_logs) are created without
# requiring manual SSH access to the EC2 instance.

set -e

APP_DIR="/var/app/current"

echo "POSTDEPLOY: Running database migrations..."

cd "$APP_DIR"

# Run migrations (force to skip production confirmation prompt)
php artisan migrate --force 2>&1 && echo "POSTDEPLOY: Migrations completed successfully" || {
    echo "POSTDEPLOY WARNING: Migrations failed — check logs for details"
    # Don't fail the entire deployment if migrations fail
    # (the app may still work with existing schema)
    exit 0
}

# Clear config cache after migration (new env vars, etc.)
php artisan config:cache 2>/dev/null && echo "POSTDEPLOY: Config re-cached after migration"
