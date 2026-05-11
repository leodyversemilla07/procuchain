#!/usr/bin/env bash
# Safety net: ensure .env exists even after eb setenv (which skips predeploy hooks)
# This runs after every deployment, including config-only updates

set -e

APP_DIR="/var/app/current"
ENV_FILE="$APP_DIR/.env"

if [ ! -f "$ENV_FILE" ]; then
  echo "POSTDEPLOY: .env missing — regenerating from environment variables"
  cat > "$ENV_FILE" <<EOF
APP_NAME=ProcuChain
APP_ENV=${APP_ENV:-production}
APP_KEY=${APP_KEY:-}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=http://procuchain-prod.eba-vujm352s.us-east-1.elasticbeanstalk.com

LOG_CHANNEL=stderr

DB_CONNECTION=mysql
DB_HOST=${DB_HOST:-127.0.0.1}
DB_PORT=3306
DB_DATABASE=${DB_DATABASE:-procuchain}
DB_USERNAME=${DB_USERNAME:-procuchain}
DB_PASSWORD=${DB_PASSWORD:-}

MULTICHAIN_RPC_USER=${MULTICHAIN_RPC_USER:-multichain}
MULTICHAIN_RPC_PASSWORD=${MULTICHAIN_RPC_PASSWORD:-multichain}
MULTICHAIN_RPC_PORT=6834
EOF
  chown webapp:webapp "$ENV_FILE"
  echo "POSTDEPLOY: .env generated successfully"
else
  echo "POSTDEPLOY: .env already exists — skipping"
fi
