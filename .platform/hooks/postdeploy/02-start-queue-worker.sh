#!/usr/bin/env bash
# Start Laravel queue worker via nohup (Supervisor not available by default on AL2023)
set -e

APP_DIR="/var/app/current"
WORKER_LOG="/var/log/laravel-queue-worker.log"

# Kill any existing queue worker
pkill -f "queue:work" 2>/dev/null || true
sleep 1

# Start queue worker in background as webapp user
su -s /bin/bash webapp -c "nohup php $APP_DIR/artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --queue=default >> $WORKER_LOG 2>&1 &"

echo "Queue worker started (PID: $(pgrep -f 'queue:work' | head -1))"
