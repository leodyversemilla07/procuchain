#!/usr/bin/env bash
# Generate complete .env from EB environment variables.
# Predeploy hooks run in /var/app/staging/ — this directory gets promoted
# to /var/app/current/ after all predeploy hooks succeed.
# NOTE: Removed 'set -e' — individual failures are handled gracefully.

ENV_FILE="/var/app/staging/.env"

echo "PREDEPLOY: Generating .env from EB environment variables..."

# Try get-config first (works during full deploys)
RAW=$(/opt/elasticbeanstalk/bin/get-config environment 2>/dev/null)

if [ -n "$RAW" ]; then
 echo "PREDEPLOY: get-config succeeded, generating .env from JSON"
 echo "$RAW" | python3 -c "
import sys, json
env = json.load(sys.stdin)
lines = []
for key, value in env.items():
 escaped = str(value).replace('\"', '\\\\\"')
 lines.append(f'{key}=\"{escaped}\"')
print('\\n'.join(lines))
" > "$ENV_FILE"
elif [ -f /opt/elasticbeanstalk/deploy/configuration/envfile ]; then
 echo "PREDEPLOY: get-config failed, using envfile fallback"
 cp /opt/elasticbeanstalk/deploy/configuration/envfile "$ENV_FILE"
else
 echo "PREDEPLOY: WARNING — No env source available, creating minimal .env"
 # Create a minimal .env so the deploy doesn't crash
 touch "$ENV_FILE"
fi

# Append defaults that are NOT already in EB env vars
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
REDIS_CLIENT=predis
DEFAULTS

chown webapp:webapp "$ENV_FILE" 2>/dev/null || true
chmod 640 "$ENV_FILE" 2>/dev/null || true

echo "PREDEPLOY: .env ready ($(wc -l < "$ENV_FILE" 2>/dev/null || echo '?') lines)"
