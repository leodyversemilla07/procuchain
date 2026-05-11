#!/usr/bin/env bash
# Create .env file from Elastic Beanstalk environment variables
# This runs as a predeploy hook — CWD is /var/app/staging/

set -e

ENV_FILE="/var/app/staging/.env"

echo "Generating .env from EB environment variables..."

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

MULTICHAIN_CHAIN_NAME=${MULTICHAIN_CHAIN_NAME:-procuchain}
MULTICHAIN_RPC_HOST=${MULTICHAIN_RPC_HOST:-127.0.0.1}
MULTICHAIN_RPC_PORT=${MULTICHAIN_RPC_PORT:-6834}
MULTICHAIN_RPC_USERNAME=${MULTICHAIN_RPC_USERNAME:-multichainrpc}
MULTICHAIN_RPC_PASSWORD=${MULTICHAIN_RPC_PASSWORD:-multichainrpc}
MULTICHAIN_MASTER_PORT=${MULTICHAIN_MASTER_PORT:-6835}
MULTICHAIN_USE_SSL=${MULTICHAIN_USE_SSL:-false}
MULTICHAIN_VERIFY_SSL=${MULTICHAIN_VERIFY_SSL:-false}
MULTICHAIN_CONNECTION_TIMEOUT=${MULTICHAIN_CONNECTION_TIMEOUT:-30}
MULTICHAIN_MAX_RETRIES=${MULTICHAIN_MAX_RETRIES:-3}
EOF

chown webapp:webapp "$ENV_FILE"
echo ".env file generated successfully"
