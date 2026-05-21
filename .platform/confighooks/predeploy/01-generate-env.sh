#!/usr/bin/env bash
# Generate .env from EB environment variables during config-only deploys.
# This is the confighooks equivalent of hooks/predeploy/01-generate-env.sh.
# Config-only deploys (eb setenv) only run confighooks, not hooks.
# NOTE: Removed 'set -e' — individual failures are handled gracefully.

APP_DIR="/var/app/staging"
ENV_FILE="$APP_DIR/.env"

echo "PREDEPLOY CONFIG: Generating .env from EB environment variables..."

# Try get-config first
RAW=$(/opt/elasticbeanstalk/bin/get-config environment 2>/dev/null)

if [ -n "$RAW" ]; then
 echo "PREDEPLOY CONFIG: get-config succeeded"
 echo "$RAW" | python3 -c "
import json, sys
data = json.load(sys.stdin)
with open('$ENV_FILE', 'w') as f:
 for k, v in data.items():
 escaped = str(v).replace('\"', '\\\\\"')
 f.write(f'{k}=\"{escaped}\"\\n')
"
else
 echo "PREDEPLOY CONFIG: get-config failed, trying envfile fallback"
 if [ -f /opt/elasticbeanstalk/deploy/configuration/envfile ]; then
 cp /opt/elasticbeanstalk/deploy/configuration/envfile "$ENV_FILE"
 else
 echo "PREDEPLOY CONFIG: WARNING — no env source available, .env may be incomplete"
 # Don't exit 1 — let the postdeploy hook try other fallbacks
 fi
fi

# Append defaults if they don't already exist
if [ -f "$ENV_FILE" ]; then
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
 echo "PREDEPLOY CONFIG: .env generated ($(wc -l < "$ENV_FILE" 2>/dev/null || echo '?') lines)"

 # Fix REDIS_PASSWORD=null — Predis sends literal "AUTH null" which Redis rejects.
 if grep -q '^REDIS_PASSWORD="null"' "$ENV_FILE" 2>/dev/null || grep -q '^REDIS_PASSWORD=null' "$ENV_FILE" 2>/dev/null; then
  echo "PREDEPLOY CONFIG: Stripping REDIS_PASSWORD=null (breaks Predis AUTH)"
  sed -i '/^REDIS_PASSWORD=/d' "$ENV_FILE"
 fi
fi
