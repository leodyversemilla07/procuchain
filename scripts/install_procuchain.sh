#!/bin/bash
# ==========================================================
# MultiChain Community Edition quick install for Procuchain
# Follows the official Linux installation steps:
#   cd /tmp
#   wget https://www.multichain.com/download/multichain-2.3.3.tar.gz
#   tar -xvzf multichain-2.3.3.tar.gz
#   mv multichaind multichain-cli multichain-util /usr/local/bin
#
# Environment Variables:
#   MULTICHAIN_CHAIN_NAME    - Chain name (default: procuchain)
#   MULTICHAIN_RPC_ALLOW_IP  - Additional IP/CIDR to allow RPC (optional)
#
# Security Note:
#   By default, RPC is only accessible from localhost (127.0.0.1).
#   Set MULTICHAIN_RPC_ALLOW_IP to allow external connections.
#
# Usage:
#   ./install_procuchain.sh
#   MULTICHAIN_RPC_ALLOW_IP=10.0.0.0/8 ./install_procuchain.sh
# ==========================================================

set -euo pipefail

CHAIN_NAME="${MULTICHAIN_CHAIN_NAME:-procuchain}"
MC_VERSION="2.3.3"
ARCHIVE="multichain-${MC_VERSION}.tar.gz"
DOWNLOAD_URL="https://www.multichain.com/download/${ARCHIVE}"
CHAIN_DIR="$HOME/.multichain/${CHAIN_NAME}"
RPC_CONF="${CHAIN_DIR}/multichain.conf"

ensure_ufw_rule() {
    local port="$1"
    if sudo ufw status | grep -E "^${port}/tcp\s+ALLOW" >/dev/null 2>&1; then
        echo "     • ${port}/tcp already allowed"
        return 0
    fi

    if sudo ufw allow "${port}"/tcp >/dev/null 2>&1; then
        echo "     • ${port}/tcp rule added"
        return 0
    fi

    echo "     • WARNING: unable to add ${port}/tcp rule" >&2
    return 1
}

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

if [[ ! -f "${CHAIN_DIR}/params.dat" ]]; then
    echo ">>> Creating '${CHAIN_NAME}' blockchain"
    multichain-util create "${CHAIN_NAME}"
else
    echo ">>> '${CHAIN_NAME}' already exists"
fi

RPC_PORT=$(grep -E '^default-rpc-port' "${CHAIN_DIR}/params.dat" 2>/dev/null | tail -n1 | awk -F'=' '{print $2}' | cut -d'#' -f1 | tr -d ' ')
P2P_PORT=$(grep -E '^default-network-port' "${CHAIN_DIR}/params.dat" 2>/dev/null | tail -n1 | awk -F'=' '{print $2}' | cut -d'#' -f1 | tr -d ' ')
[[ -z "${RPC_PORT}" ]] && RPC_PORT=7448
[[ -z "${P2P_PORT}" ]] && P2P_PORT=7447

echo ">>> Starting '${CHAIN_NAME}' node"
if ! multichain-cli "${CHAIN_NAME}" getinfo >/dev/null 2>&1; then
    multichaind "${CHAIN_NAME}" -daemon
fi

for _ in {1..60}; do
    if multichain-cli "${CHAIN_NAME}" getinfo >/dev/null 2>&1; then
        break
    fi
    sleep 1
done

if ! multichain-cli "${CHAIN_NAME}" getinfo >/dev/null 2>&1; then
    echo "Failed to communicate with the node." >&2
    exit 1
fi

if [[ ! -f "${RPC_CONF}" ]]; then
    echo ">>> Waiting for multichain.conf"
    for _ in {1..30}; do
        [[ -f "${RPC_CONF}" ]] && break
        sleep 1
    done
fi

needs_restart=0
# Set allowed RPC IPs - default to localhost only for security
# Set MULTICHAIN_RPC_ALLOW_IP to allow external connections
RPC_ALLOW_IP="${MULTICHAIN_RPC_ALLOW_IP:-}"

if [[ -f "${RPC_CONF}" ]]; then
    if ! grep -q "^rpcport=" "${RPC_CONF}"; then
        echo "rpcport=${RPC_PORT}" >> "${RPC_CONF}"
        needs_restart=1
    fi
    if ! grep -q "^port=" "${RPC_CONF}"; then
        echo "port=${P2P_PORT}" >> "${RPC_CONF}"
        needs_restart=1
    fi
    if ! grep -q '^rpcallowip=127.0.0.1' "${RPC_CONF}"; then
        echo "rpcallowip=127.0.0.1" >> "${RPC_CONF}"
        needs_restart=1
    fi
    # Only add external RPC access if explicitly configured
    if [[ -n "${RPC_ALLOW_IP}" ]] && ! grep -q "^rpcallowip=${RPC_ALLOW_IP}" "${RPC_CONF}"; then
        echo "rpcallowip=${RPC_ALLOW_IP}" >> "${RPC_CONF}"
        needs_restart=1
    fi
fi

if [[ ${needs_restart} -eq 1 ]]; then
    echo ">>> Restarting node to apply RPC settings"
    multichain-cli "${CHAIN_NAME}" stop >/dev/null 2>&1 || true
    sleep 2
    multichaind "${CHAIN_NAME}" -daemon
    for _ in {1..60}; do
        if multichain-cli "${CHAIN_NAME}" getinfo >/dev/null 2>&1; then
            break
        fi
        sleep 1
    done
fi

echo ">>> Ensuring firewall allows P2P and RPC traffic"
allow_errors=0
ensure_ufw_rule "${P2P_PORT}" || allow_errors=1
ensure_ufw_rule "${RPC_PORT}" || allow_errors=1

if [[ ${allow_errors} -eq 0 ]]; then
    echo "✅ Firewall confirmed for ${P2P_PORT}/tcp and ${RPC_PORT}/tcp"
else
    echo "⚠️ Please review ufw output above to confirm rules were applied" >&2
fi

RPC_USER=$(grep -E '^rpcuser=' "${RPC_CONF}" | head -n1 | cut -d'=' -f2)
RPC_PASS=$(grep -E '^rpcpassword=' "${RPC_CONF}" | head -n1 | cut -d'=' -f2)
RPC_HOST=$(curl -fsSL ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}' || echo "<node-ip>")

echo ""
echo "----------------------------------------------------"
echo " MultiChain '${CHAIN_NAME}' node is running"
echo " P2P Port : ${P2P_PORT}"
echo " RPC Port : ${RPC_PORT}"
echo " RPC User : ${RPC_USER}"
echo "----------------------------------------------------"
echo " Connect other nodes with:"
echo "   multichaind ${CHAIN_NAME}@${RPC_HOST}:${P2P_PORT}"
echo "----------------------------------------------------"

echo ""
echo "===================================================="
echo " Laravel .env Configuration"
echo "===================================================="
echo "MULTICHAIN_CHAIN_NAME=${CHAIN_NAME}"
echo "MULTICHAIN_RPC_HOST=${RPC_HOST}"
echo "MULTICHAIN_RPC_PORT=${RPC_PORT}"
echo "MULTICHAIN_RPC_USERNAME=${RPC_USER}"
echo "MULTICHAIN_RPC_PASSWORD=${RPC_PASS}"
echo "===================================================="

echo ""
echo "Next steps"
echo "  1. Copy the values above into your Laravel .env"
echo "  2. Test the RPC connection: php artisan multichain:setup --check"
echo "  3. Finish setup        : php artisan multichain:setup"
