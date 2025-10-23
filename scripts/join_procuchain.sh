#!/bin/bash
# ==========================================================
# MultiChain Community Edition peer node join script for Procuchain
# Connects to an existing Procuchain blockchain as a peer node
# ==========================================================

set -euo pipefail

CHAIN_NAME="procuchain"
SEED_HOST="159.65.12.99"
P2P_PORT="6487"
MC_VERSION="2.3.3"
ARCHIVE="multichain-${MC_VERSION}.tar.gz"
DOWNLOAD_URL="https://www.multichain.com/download/${ARCHIVE}"

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

echo ">>> Peer node started successfully"
echo "Connected to: ${SEED_HOST}:${P2P_PORT}"
echo "Chain: ${CHAIN_NAME}"