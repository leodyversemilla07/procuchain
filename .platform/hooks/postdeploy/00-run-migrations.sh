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

# Diagnostic: verify audit_logs table exists and the query works
echo "POSTDEPLOY: Running audit_logs diagnostic..."
php artisan tinker --execute="
if (!\Illuminate\Support\Facades\Schema::hasTable('audit_logs')) {
    echo 'DIAG: audit_logs table DOES NOT EXIST';
} else {
    echo 'DIAG: audit_logs table exists, columns: ' . implode(', ', \Illuminate\Support\Facades\Schema::getColumnListing('audit_logs'));
    echo 'DIAG: Row count: ' . \Illuminate\Support\Facades\DB::table('audit_logs')->count();
    try {
        \$logs = \App\Models\AuditLog::with('actor:id,name,email')->latest('created_at')->paginate(50);
        echo 'DIAG: Query SUCCESS - ' . \$logs->total() . ' logs found';
    } catch (\Exception \$e) {
        echo 'DIAG: Query FAILED - ' . get_class(\$e) . ': ' . \$e->getMessage();
    }
}
" 2>&1 || echo "POSTDEPLOY: Diagnostic skipped (non-critical)"

# Also check Laravel error logs
if [ -f "$APP_DIR/storage/logs/laravel.log" ]; then
    echo "POSTDEPLOY: Recent Laravel errors:"
    tail -5 "$APP_DIR/storage/logs/laravel.log" 2>/dev/null || true
fi

# Clear config cache after migration (new env vars, etc.)
php artisan config:cache 2>/dev/null && echo "POSTDEPLOY: Config re-cached after migration"
