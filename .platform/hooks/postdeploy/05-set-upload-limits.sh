#!/bin/bash
# Override nginx client_max_body_size to 200M in STAGING config
# Platform copies staging to /etc/nginx AFTER postdeploy hooks run

set -e

STAGING_NGINX_CONF="/var/proxy/staging/nginx/nginx.conf"

# Replace existing client_max_body_size directive in staging config
if grep -q 'client_max_body_size' "$STAGING_NGINX_CONF"; then
    sed -i 's/client_max_body_size [0-9]\+[KMGT]*;/client_max_body_size 200M;/' "$STAGING_NGINX_CONF"
    echo "Updated existing client_max_body_size to 200M in staging nginx.conf"
else
    # Add to http block if not present
    sed -i '/http {/a\    client_max_body_size 200M;' "$STAGING_NGINX_CONF"
    echo "Added client_max_body_size 200M to http block in staging nginx.conf"
fi

# Also check if there's a default.conf or site config with client_max_body_size
for conf_file in /var/proxy/staging/nginx/conf.d/*.conf; do
    if grep -q 'client_max_body_size' "$conf_file"; then
        echo "Found client_max_body_size in $conf_file, overriding..."
        sed -i 's/client_max_body_size [0-9]\+[KMGT]*;/client_max_body_size 200M;/' "$conf_file"
    fi
done

echo "Staging nginx config updated. Platform will copy to /etc/nginx and reload."
