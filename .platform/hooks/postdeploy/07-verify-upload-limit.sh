#!/bin/bash
# Verify the ACTIVE nginx config after deployment

echo "=== VERIFICATION: Checking ACTIVE nginx config at /etc/nginx/nginx.conf ==="
grep -n 'client_max_body_size' /etc/nginx/nginx.conf || echo "NOT FOUND in /etc/nginx/nginx.conf"

echo "=== Checking conf.d files ==="
for f in /etc/nginx/conf.d/*.conf; do
    [ -f "$f" ] && (grep -n 'client_max_body_size' "$f" || echo "NOT FOUND in $f")
done

for f in /etc/nginx/conf.d/elasticbeanstalk/*.conf; do
    [ -f "$f" ] && (grep -n 'client_max_body_size' "$f" || echo "NOT FOUND in $f")
done

echo "=== Checking server/location blocks in nginx.conf ==="
grep -A5 -B5 'client_max_body_size' /etc/nginx/nginx.conf || echo "No client_max_body_size in nginx.conf"

echo "=== Checking nginx version and config test ==="
nginx -t
echo "Verification complete!"
