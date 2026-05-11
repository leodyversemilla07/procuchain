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
DB_USERNAME=${DB_USERNAME:-admin}
DB_PASSWORD=${DB_PASSWORD:-}

MULTICHAIN_RPC_USER=${MULTICHAIN_RPC_USER:-multichain}
MULTICHAIN_RPC_PASSWORD=${MULTICHAIN_RPC_PASSWORD:-multichain}
MULTICHAIN_RPC_PORT=6834
EOF

echo ".env file generated successfully"
cat "$ENV_FILE" | grep -v PASSWORD | grep -v KEY
