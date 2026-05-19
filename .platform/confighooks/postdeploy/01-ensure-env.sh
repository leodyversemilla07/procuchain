#!/usr/bin/env bash
# Generate complete .env from ALL EB environment variables.
# Safety net for config-only updates (eb setenv) that skip predeploy hooks.
# Uses /opt/elasticbeanstalk/bin/get-config environment (JSON output).
set -e

APP_DIR="/var/app/current"
ENV_FILE="$APP_DIR/.env"

echo "POSTDEPLOY: Generating .env from EB environment variables..."

cd "$APP_DIR"

# Step 1: Dump all EB env vars as JSON using get-config
RAW=$(/opt/elasticbeanstalk/bin/get-config environment 2>/dev/null)

if [ -z "$RAW" ]; then
  echo "POSTDEPLOY: WARNING — get-config returned empty, trying envfile fallback"
  if [ -f /opt/elasticbeanstalk/deploy/configuration/envfile ]; then
    cp /opt/elasticbeanstalk/deploy/configuration/envfile "$ENV_FILE"
    chown webapp:webapp "$ENV_FILE"
    echo "POSTDEPLOY: .env copied from envfile fallback"
  else
    echo "POSTDEPLOY: ERROR — No env vars available!"
    exit 1
  fi
else
  # Step 2: Convert JSON to KEY="VALUE" .env format using python3
  echo "$RAW" | python3 -c "
import json, sys
data = json.load(sys.stdin)
with open('$ENV_FILE', 'w') as f:
    for k, v in data.items():
        # Escape any double quotes in values
        escaped = str(v).replace('\"', '\\\\\"')
        f.write(f'{k}=\"{escaped}\"\n')
"
  chown webapp:webapp "$ENV_FILE"
fi

LINE_COUNT=$(wc -l < "$ENV_FILE")
echo "POSTDEPLOY: .env generated with $LINE_COUNT lines"

# Verify critical vars exist
for VAR in APP_KEY DB_HOST MAIL_MAILER; do
  if ! grep -q "^${VAR}=" "$ENV_FILE" 2>/dev/null; then
    echo "POSTDEPLOY: WARNING — $VAR is missing from .env!"
  fi
done

# Always recache config to pick up new env vars
php artisan config:cache 2>/dev/null && echo "POSTDEPLOY: Config cached successfully"
