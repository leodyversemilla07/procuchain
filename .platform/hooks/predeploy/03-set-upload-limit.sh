#!/bin/bash
# Override client_max_body_size in staging nginx.conf
# Runs AFTER platform configures nginx (during Infra-EmbeddedPostBuild)
# Runs BEFORE application flip

set -e

STAGING_NGINX="/var/proxy/staging/nginx/nginx.conf"

echo "Checking for existing client_max_body_size..."
if grep -q 'client_max_body_size' "$STAGING_NGINX"; then
    echo "Removing existing client_max_body_size..."
    sed -i '/client_max_body_size/d' "$STAGING_NGINX"
fi

echo "Adding client_max_body_size 200M to http block..."
sed -i '/http {/a\    client_max_body_size 200M;' "$STAGING_NGINX"

# Also clean any conf.d files
for f in /var/proxy/staging/nginx/conf.d/*.conf; do
    [ -f "$f" ] && sed -i '/client_max_body_size/d' "$f"
done

echo "Verifying nginx config..."
nginx -t -c /var/proxy/staging/nginx/nginx.conf
echo "Staging nginx config updated successfully!"
