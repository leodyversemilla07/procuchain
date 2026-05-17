#!/bin/bash
# Post-deploy hook: inject Elastic Beanstalk env vars into Laravel's .env
# EB sets shell env vars, but Laravel reads from .env file — this bridges the gap.

ENV_FILE="/var/app/current/.env"

# Get all EB environment variables as JSON and convert to .env format
/opt/elasticbeanstalk/bin/get-config environment | python3 -c "
import sys, json
env = json.load(sys.stdin)
lines = []
for key, value in env.items():
    escaped = str(value).replace('\"', '\\\\\"')
    lines.append(f'{key}=\"{escaped}\"')
print('\n'.join(lines))
" > "$ENV_FILE"

# Ensure proper ownership and permissions
chown webapp:webapp "$ENV_FILE"
chmod 640 "$ENV_FILE"

echo "Laravel .env written from EB environment variables"

# Restart PHP-FPM to pick up the new config
systemctl restart php-fpm 2>/dev/null || systemctl restart php8.4-fpm 2>/dev/null || true
