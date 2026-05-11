#!/usr/bin/env bash
# Register Laravel queue worker as a systemd service (AL2023)
set -e

APP_DIR="/var/app/current"
SERVICE_FILE="/etc/systemd/system/laravel-queue-worker.service"

cat > "$SERVICE_FILE" << 'EOF'
[Unit]
Description=Laravel Queue Worker
After=php-fpm.service

[Service]
Type=simple
User=webapp
Group=webapp
Restart=always
RestartSec=5
ExecStart=/usr/bin/php /var/app/current/artisan queue:work database --sleep=3 --tries=3
StandardOutput=append:/var/app/current/storage/logs/queue-worker.log
StandardError=append:/var/app/current/storage/logs/queue-worker.log

[Install]
WantedBy=multi-user.target
EOF

# Ensure log dir exists
mkdir -p "$APP_DIR/storage/logs"
chown -R webapp:webapp "$APP_DIR/storage"

# Enable and start
systemctl daemon-reload
systemctl enable laravel-queue-worker
systemctl start laravel-queue-worker

echo "Laravel queue worker service started"
