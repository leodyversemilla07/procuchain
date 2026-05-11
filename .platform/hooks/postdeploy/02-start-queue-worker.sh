#!/usr/bin/env bash
# Start Laravel queue worker on EB AL2023
set -e

APP_DIR="/var/app/current"
WORKER_LOG="$APP_DIR/storage/logs/queue-worker.log"

# Ensure log directory is writable
mkdir -p "$APP_DIR/storage/logs"
chown -R webapp:webapp "$APP_DIR/storage/logs"

# Kill any existing queue worker
pkill -f "queue:work" 2>/dev/null || true
sleep 1

# Start queue worker in background as webapp user
cd "$APP_DIR"
su -s /bin/bash webapp -c "cd $APP_DIR && nohup php artisan queue:work database --sleep=3 --tries=3 --max-time=3600 >> $WORKER_LOG 2>&1 &"

sleep 2
WORKER_PID=$(pgrep -f 'queue:work' | head -1 || echo "not-found")
echo "Queue worker started (PID: $WORKER_PID)"
