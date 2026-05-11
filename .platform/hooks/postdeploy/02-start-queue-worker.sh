#!/usr/bin/env bash
# Start Laravel queue worker on EB AL2023
# Must exit quickly — EB kills hooks that run longer than ~5 min
set -e

APP_DIR="/var/app/current"
WORKER_LOG="$APP_DIR/storage/logs/queue-worker.log"

# Ensure log directory is writable
mkdir -p "$APP_DIR/storage/logs"
chown -R webapp:webapp "$APP_DIR/storage/logs"

# Kill any existing queue worker
pkill -f "php artisan queue:work" 2>/dev/null || true
sleep 1

# Fully detach queue worker using setsid + disown
cd "$APP_DIR"
su -s /bin/bash webapp -c "setsid php artisan queue:work database --sleep=3 --tries=3 --max-time=3600 >> $WORKER_LOG 2>&1 < /dev/null &"

# Brief check — don't wait for worker, just confirm it spawned
sleep 1
if pgrep -f 'queue:work' > /dev/null 2>&1; then
    echo "Queue worker started successfully"
else
    echo "WARNING: Queue worker may not have started"
fi

# Exit immediately — don't let EB think we're still running
exit 0
