#!/bin/bash
# ==========================================================
# MultiChain Community Edition peer node join script for Procuchain
# Connects to an existing Procuchain blockchain as a peer node
#
# Environment Variables:
#   MULTICHAIN_CHAIN_NAME  - Chain name (default: procuchain)
#   MULTICHAIN_SEED_HOST   - Seed node IP/hostname (default: 159.65.12.99)
#   MULTICHAIN_P2P_PORT    - P2P port (default: 6487)
#
# Usage:
#   ./join_procuchain.sh
#   MULTICHAIN_SEED_HOST=10.0.0.5 ./join_procuchain.sh
# ==========================================================

set -euo pipefail

CHAIN_NAME="${MULTICHAIN_CHAIN_NAME:-procuchain}"
SEED_HOST="${MULTICHAIN_SEED_HOST:-159.65.12.99}"
P2P_PORT="${MULTICHAIN_P2P_PORT:-6487}"
MC_VERSION="2.3.3"
ARCHIVE="multichain-${MC_VERSION}.tar.gz"
DOWNLOAD_URL="https://www.multichain.com/download/${ARCHIVE}"
CHAIN_DIR="$HOME/.multichain/${CHAIN_NAME}"

if ! command -v apt-get >/dev/null 2>&1; then
    echo "This installer targets Ubuntu/Debian servers (apt-get required)." >&2
    exit 1
fi

if ! command -v sudo >/dev/null 2>&1; then
    echo "sudo is required to install MultiChain binaries." >&2
    exit 1
fi

echo ">>> Updating package lists"
sudo apt-get update -y

echo ">>> Upgrading system packages"
sudo apt-get upgrade -y

echo ">>> Installing wget, tar, and ufw"
sudo apt-get install -y wget tar ufw >/dev/null 2>&1 || true

if ! command -v multichaind >/dev/null 2>&1; then
    echo ">>> MultiChain ${MC_VERSION} not found. Installing from official tarball"
    cd /tmp
    if [[ ! -f "${ARCHIVE}" ]]; then
        wget "${DOWNLOAD_URL}"
    fi
    tar -xvzf "${ARCHIVE}"
    cd "multichain-${MC_VERSION}"
    sudo mv multichaind multichain-cli multichain-util /usr/local/bin/
    cd /tmp
    rm -rf "multichain-${MC_VERSION}" "${ARCHIVE}"
else
    echo ">>> MultiChain already installed: $(multichain-cli --version 2>/dev/null || multichaind --version)"
fi

echo ">>> Verifying MultiChain binaries"
multichaind --version >/dev/null 2>&1

echo ">>> Connecting to '${CHAIN_NAME}' blockchain at ${SEED_HOST}:${P2P_PORT} as peer node"
multichaind "${CHAIN_NAME}@${SEED_HOST}:${P2P_PORT}" -daemon

# Wait for node to start and validate connection
echo ">>> Waiting for node to initialize..."
for _ in {1..60}; do
    if multichain-cli "${CHAIN_NAME}" getinfo >/dev/null 2>&1; then
        break
    fi
    sleep 1
done

# Validate connection
if multichain-cli "${CHAIN_NAME}" getinfo >/dev/null 2>&1; then
    echo "✅ Peer node started and connected successfully"
    echo ""
    echo "----------------------------------------------------"
    echo " Node Information"
    echo "----------------------------------------------------"
    multichain-cli "${CHAIN_NAME}" getinfo | grep -E "version|nodeaddress|blocks|connections" || true
    echo "----------------------------------------------------"
else
    echo "⚠️  Node started but connection not yet verified"
    echo "   The seed node admin may need to grant connect permission"
    echo "   Your wallet address:"
    multichain-cli "${CHAIN_NAME}" getaddresses 2>/dev/null || echo "   (not available yet)"
fi

echo ""
echo "Connected to: ${SEED_HOST}:${P2P_PORT}"
echo "Chain: ${CHAIN_NAME}"