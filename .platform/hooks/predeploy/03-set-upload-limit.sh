#!/bin/bash
# Override client_max_body_size in staging nginx.conf
# Runs AFTER platform configures nginx (during Infra-EmbeddedPostBuild)
# Runs BEFORE application flip

set -e

STAGING_NGINX="/var/proxy/staging/nginx/nginx.conf"

echo "=== BEFORE: Checking existing client_max_body_size ==="
grep -n 'client_max_body_size' "$STAGING_NGINX" || echo "NOT FOUND in nginx.conf"
for f in /var/proxy/staging/nginx/conf.d/*.conf; do
    [ -f "$f" ] && (grep -n 'client_max_body_size' "$f" || echo "NOT FOUND in $f")
done

echo "=== Removing ALL client_max_body_size directives ==="
# Remove from nginx.conf (case insensitive, various formats)
sed -i '/client_max_body_size/d' "$STAGING_NGINX"
# Remove from all conf.d files
for f in /var/proxy/staging/nginx/conf.d/*.conf; do
    [ -f "$f" ] && sed -i '/client_max_body_size/d' "$f"
done
# Also check conf.d/elasticbeanstalk/
for f in /var/proxy/staging/nginx/conf.d/elasticbeanstalk/*.conf; do
    [ -f "$f" ] && sed -i '/client_max_body_size/d' "$f"
done

echo "=== Adding client_max_body_size 200M to http block ==="
# Add to http block - try multiple patterns
if grep -q '^http {' "$STAGING_NGINX"; then
    sed -i '/^http {/a\    client_max_body_size 200M;' "$STAGING_NGINX"
elif grep -q 'http\s*{' "$STAGING_NGINX"; then
    sed -i '/http\s*{/a\    client_max_body_size 200M;' "$STAGING_NGINX"
else
    # Fallback: add at top level
    sed -i '1i client_max_body_size 200M;' "$STAGING_NGINX"
fi

echo "=== AFTER: Verifying ==="
grep -n 'client_max_body_size' "$STAGING_NGINX" || echo "STILL NOT FOUND in nginx.conf!"

echo "=== Testing nginx config ==="
nginx -t -c /var/proxy/staging/nginx/nginx.conf
echo "SUCCESS: Staging nginx config updated and validated!"
