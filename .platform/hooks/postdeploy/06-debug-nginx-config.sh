#!/bin/bash
# Debug: show what's in staging nginx.conf for client_max_body_size

STAGING_NGINX_CONF="/var/proxy/staging/nginx/nginx.conf"

echo "=== client_max_body_size in staging nginx.conf ==="
grep -n 'client_max_body_size' "$STAGING_NGINX_CONF" || echo "NOT FOUND"

echo "=== client_max_body_size in conf.d ==="
grep -rn 'client_max_body_size' /var/proxy/staging/nginx/conf.d/ || echo "NOT FOUND in conf.d"

echo "=== Full http block context around client_max_body_size ==="
grep -B5 -A5 'client_max_body_size' "$STAGING_NGINX_CONF" || echo "NOT FOUND"
