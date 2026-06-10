#!/bin/bash
# Aggressively set client_max_body_size to 200M
# 1. Remove ALL existing client_max_body_size directives
# 2. Add our own to nginx.conf http block
# 3. Also set in a conf.d file for good measure

set -e

STAGING_NGINX_CONF="/var/proxy/staging/nginx/nginx.conf"
CONF_D_DIR="/var/proxy/staging/nginx/conf.d"

echo "Removing ALL existing client_max_body_size directives..."

# Remove from nginx.conf
sed -i '/client_max_body_size/d' "$STAGING_NGINX_CONF"

# Remove from all conf.d files
for conf in "$CONF_D_DIR"/*.conf; do
    if [ -f "$conf" ]; then
        sed -i '/client_max_body_size/d' "$conf"
    fi
done

# Add our own to nginx.conf http block
# Find the http block and add after the opening brace
sed -i '/http {/a\    client_max_body_size 200M;' "$STAGING_NGINX_CONF"

# Also create a conf.d file with the setting
cat > "$CONF_D_DIR/upload-limits.conf" << 'EOFCONF'
# Override upload limits
client_max_body_size 200M;
proxy_read_timeout 300s;
proxy_send_timeout 300s;
EOFCONF

echo "Set client_max_body_size to 200M in staging nginx config"
nginx -t -c /var/proxy/staging/nginx/nginx.conf
