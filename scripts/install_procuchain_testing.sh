#!/bin/bash
# Procuchain Testing Node Installer
# Creates an isolated blockchain for testing

set -euo pipefail

# Configuration
CHAIN_NAME="procuchain-testing"
MC_VERSION="2.3.3"
P2P_PORT=7449
RPC_PORT=7450
CHAIN_DIR="$HOME/.multichain/${CHAIN_NAME}"

echo "🔧 Procuchain Testing Node Setup"
echo "================================="

# Install MultiChain if needed
if ! command -v multichaind &>/dev/null; then
    echo ">>> Installing MultiChain ${MC_VERSION}..."
    apt-get update -y && apt-get install -y wget tar ufw
    cd /tmp
    wget -q "https://www.multichain.com/download/multichain-${MC_VERSION}.tar.gz"
    tar -xzf "multichain-${MC_VERSION}.tar.gz"
    mv "multichain-${MC_VERSION}/multichaind" "multichain-${MC_VERSION}/multichain-cli" "multichain-${MC_VERSION}/multichain-util" /usr/local/bin/
    rm -rf "multichain-${MC_VERSION}"*
fi

# Create blockchain if needed
if [[ ! -f "${CHAIN_DIR}/params.dat" ]]; then
    echo ">>> Creating '${CHAIN_NAME}' blockchain..."
    multichain-util create "${CHAIN_NAME}" -default-network-port=${P2P_PORT} -default-rpc-port=${RPC_PORT}
fi

# Start node
echo ">>> Starting node..."
multichain-cli "${CHAIN_NAME}" stop &>/dev/null || true
sleep 2
multichaind "${CHAIN_NAME}" -daemon

# Wait for node to be ready
for i in {1..30}; do
    multichain-cli "${CHAIN_NAME}" getinfo &>/dev/null && break
    sleep 1
done

# Configure RPC access
RPC_CONF="${CHAIN_DIR}/multichain.conf"
grep -q "^rpcallowip=0.0.0.0/0" "${RPC_CONF}" 2>/dev/null || {
    echo "rpcallowip=0.0.0.0/0" >> "${RPC_CONF}"
    multichain-cli "${CHAIN_NAME}" stop &>/dev/null || true
    sleep 2
    multichaind "${CHAIN_NAME}" -daemon
    sleep 5
}

# Configure firewall
ufw allow ${P2P_PORT}/tcp &>/dev/null || true
ufw allow ${RPC_PORT}/tcp &>/dev/null || true

# Get credentials
RPC_USER=$(grep "^rpcuser=" "${RPC_CONF}" | cut -d'=' -f2)
RPC_PASS=$(grep "^rpcpassword=" "${RPC_CONF}" | cut -d'=' -f2)
RPC_HOST=$(curl -fsSL ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')

echo ""
echo "✅ Development node is running!"
echo ""
echo "Laravel .env Configuration:"
echo "----------------------------"
echo "MULTICHAIN_CHAIN_NAME=${CHAIN_NAME}"
echo "MULTICHAIN_RPC_HOST=${RPC_HOST}"
echo "MULTICHAIN_RPC_PORT=${RPC_PORT}"
echo "MULTICHAIN_RPC_USERNAME=${RPC_USER}"
echo "MULTICHAIN_RPC_PASSWORD=${RPC_PASS}"
echo ""
echo "Next: php artisan multichain:setup"
