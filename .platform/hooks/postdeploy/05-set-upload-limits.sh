#!/bin/bash
# Override nginx client_max_body_size to 200M
# This runs after platform configures nginx, so we can safely modify nginx.conf

set -e

NGINX_CONF="/etc/nginx/nginx.conf"

# Replace existing client_max_body_size directive
if grep -q 'client_max_body_size' "$NGINX_CONF"; then
    sed -i 's/client_max_body_size [0-9]\+[KMGT]*;/client_max_body_size 200M;/' "$NGINX_CONF"
    echo "Updated existing client_max_body_size to 200M in nginx.conf"
else
    # Add to http block if not present
    sed -i '/http {/a\    client_max_body_size 200M;' "$NGINX_CONF"
    echo "Added client_max_body_size 200M to http block in nginx.conf"
fi

# Test and reload nginx
nginx -t && systemctl reload nginx
echo "Nginx reloaded successfully"
