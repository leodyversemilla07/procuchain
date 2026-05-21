#!/usr/bin/env bash
# Generate complete .env from ALL EB environment variables.
# Safety net for config-only updates (eb setenv) that skip predeploy hooks.
# Uses /opt/elasticbeanstalk/bin/get-config environment (JSON output).
# NOTE: Removed 'set -e' — individual failures are handled gracefully.
APP_DIR="/var/app/current"
ENV_FILE="$APP_DIR/.env"
BACKUP_ENV="/var/app/staging/.env"

echo "POSTDEPLOY: Generating .env from EB environment variables..."

cd "$APP_DIR"

# Step 1: Try get-config (works during full deploys)
RAW=$(/opt/elasticbeanstalk/bin/get-config environment 2>/dev/null)

if [ -n "$RAW" ]; then
 echo "POSTDEPLOY: get-config succeeded, generating .env from JSON"
 echo "$RAW" | python3 -c "
import json, sys
data = json.load(sys.stdin)
with open('$ENV_FILE', 'w') as f:
 for k, v in data.items():
 escaped = str(v).replace('\"', '\\\\\"')
 f.write(f'{k}=\"{escaped}\"\\n')
"
 chown webapp:webapp "$ENV_FILE"
elif [ -f /opt/elasticbeanstalk/deploy/configuration/envfile ]; then
 echo "POSTDEPLOY: get-config empty, using envfile fallback"
 cp /opt/elasticbeanstalk/deploy/configuration/envfile "$ENV_FILE"
 chown webapp:webapp "$ENV_FILE"
elif [ -f "$BACKUP_ENV" ]; then
 echo "POSTDEPLOY: using staging .env backup"
 cp "$BACKUP_ENV" "$ENV_FILE"
 chown webapp:webapp "$ENV_FILE"
elif [ -f "$ENV_FILE" ]; then
 echo "POSTDEPLOY: .env already exists, keeping it"
else
 # Last resort: dump all EB_* env vars (set by the EB host agent)
 echo "POSTDEPLOY: WARNING — generating .env from process environment"
 /opt/elasticbeanstalk/bin/get-config environment --format=YAML 2>/dev/null | \
 python3 -c "import sys,yaml; [print(f'{k}=\"{v}\"') for k,v in yaml.safe_load(sys.stdin).items()]" \
 > "$ENV_FILE" 2>/dev/null
 if [ ! -s "$ENV_FILE" ]; then
 echo "POSTDEPLOY: ERROR — Could not generate .env!"
 exit 1
 fi
 chown webapp:webapp "$ENV_FILE"
fi

LINE_COUNT=$(wc -l < "$ENV_FILE" 2>/dev/null || echo "0")
echo "POSTDEPLOY: .env has $LINE_COUNT lines"

# Verify critical vars exist
for VAR in APP_KEY DB_HOST MAIL_MAILER; do
 if ! grep -q "^${VAR}=" "$ENV_FILE" 2>/dev/null; then
 echo "POSTDEPLOY: WARNING — $VAR is missing from .env!"
 fi
done

# Always recache config to pick up new env vars
# Don't let config:cache failures kill the deploy (e.g., Redis not yet reachable)
if php artisan config:cache 2>/dev/null; then
 echo "POSTDEPLOY: Config cached successfully"
else
 echo "POSTDEPLOY: WARNING — config:cache failed, clearing cache to use live .env"
 php artisan config:clear 2>/dev/null || true
fi
