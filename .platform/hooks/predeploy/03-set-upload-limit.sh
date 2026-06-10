#!/bin/bash
# Predeploy hook: Override nginx client_max_body_size in staging config
# Runs AFTER platform configures nginx, BEFORE platform copies staging -> /etc/nginx

set -e

STAGING_NGINX="/var/proxy/staging/nginx/nginx.conf"
TARGET_SIZE="52M"

echo "=== Setting client_max_body_size to ${TARGET_SIZE} in staging nginx ==="

if [[ ! -f "$STAGING_NGINX" ]]; then
    echo "ERROR: Staging nginx config not found at $STAGING_NGINX"
    exit 1
fi

echo "--- Before: grep client_max_body_size ---"
grep -n "client_max_body_size" "$STAGING_NGINX" || echo "No existing client_max_body_size found"

# Replace existing client_max_body_size directive in http block
# The pattern handles various formats: "client_max_body_size 10M;", "client_max_body_size 10M ;", etc.
sed -i "s/client_max_body_size [0-9]*[KMGT]*;/client_max_body_size ${TARGET_SIZE};/g" "$STAGING_NGINX"

echo "--- After: grep client_max_body_size ---"
grep -n "client_max_body_size" "$STAGING_NGINX" || echo "ERROR: client_max_body_size not found after sed!"

# Also ensure proxy buffering is set for large uploads
# Find the http block and add proxy buffering settings if not present
if ! grep -q "proxy_request_buffering" "$STAGING_NGINX"; then
    echo "Adding proxy buffering settings..."
    sed -i "/client_max_body_size ${TARGET_SIZE};/a \    proxy_request_buffering off;\n    proxy_buffering off;" "$STAGING_NGINX"
fi

echo "=== Staging nginx config updated successfully ==="