#!/usr/bin/env bash
# Generate complete .env from EB environment variables.
# Predeploy hooks run in /var/app/staging/ — this directory gets promoted
# to /var/app/current/ after all predeploy hooks succeed.
set -e

ENV_FILE="/var/app/staging/.env"

echo "PREDEPLOY: Generating .env from EB environment variables..."

# Use get-config to pull all EB env vars as JSON, then convert to .env format.
# This is the canonical source — every EB env var becomes a .env entry.
/opt/elasticbeanstalk/bin/get-config environment | python3 -c "
import sys, json
env = json.load(sys.stdin)
lines = []
for key, value in env.items():
    escaped = str(value).replace('\"', '\\\\\"')
    lines.append(f'{key}=\"{escaped}\"')
print('\n'.join(lines))
" > "$ENV_FILE"

# Append any defaults that are NOT already in EB env vars
# (these have safe production defaults and rarely change)
cat >> "$ENV_FILE" <<'DEFAULTS'

# --- Defaults (not managed via EB console) ---
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_PH
APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12
BROADCAST_CONNECTION=log
DB_FOREIGN_KEYS=true
FILESYSTEM_DISK=s3
SESSION_LIFETIME=120
SESSION_PATH=/
SESSION_ENCRYPT=false
REDIS_CLIENT=phpredis
DEFAULTS

chown webapp:webapp "$ENV_FILE"
chmod 640 "$ENV_FILE"

echo "PREDEPLOY: .env generated successfully ($(wc -l < "$ENV_FILE") lines)"
