#!/usr/bin/env bash
# Ensure the integrity.violations blockchain stream exists.
# This stream is required for the permanent audit trail (Requirement #6).
# The stream creation is idempotent — safe to run on every deploy.
set -e

APP_DIR="/var/app/current"
cd "$APP_DIR"

echo "POSTDEPLOY: Ensuring integrity.violations stream exists..."

# Check if the stream already exists by trying to read from it.
# If it doesn't exist, create it.
php artisan tinker --execute '
use App\Services\Manager;
use App\Enums\StreamEnums;
$manager = app(Manager::class);
$stream = StreamEnums::INTEGRITY_VIOLATIONS->value;
try {
    $manager->getstreaminfo($stream);
    echo "Stream {$stream} already exists\n";
} catch (\Exception $e) {
    echo "Creating stream {$stream}...\n";
    $manager->create("stream", $stream, true);
    $manager->subscribe($stream, true);
    echo "Stream {$stream} created and subscribed\n";
}
' 2>&1 && echo "POSTDEPLOY: Integrity stream check complete" \
  || echo "POSTDEPLOY: WARNING — integrity stream setup failed (non-fatal)"
