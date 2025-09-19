#!/bin/bash
# ==========================================================
# MultiChain Installer & Procuchain Creator
# Aligns with official MultiChain install steps for Linux
# ==========================================================

set -euo pipefail

# Defaults (can be overridden via flags)
CHAIN_NAME="procuchain"
MC_VERSION="2.3.3"
MC_FILE="multichain-${MC_VERSION}.tar.gz"
MC_URL="https://www.multichain.com/download/${MC_FILE}"
CHAIN_DIR="$HOME/.multichain/${CHAIN_NAME}"
OPEN_FIREWALL=false
SHOW_CREDENTIALS=false
SEED_NODE=""

usage() {
    cat <<USAGE
Usage: $(basename "$0") [options]

Options:
    --chain-name <name>     Blockchain name (default: ${CHAIN_NAME})
    --version <x.y.z>       MultiChain version to install (default: ${MC_VERSION})
    --seed <ip[:port]>      Seed node to connect to an existing chain
    --open-firewall         Add UFW rules for detected P2P/RPC ports
    --show-credentials      Print RPC credentials after startup (not recommended on shared shells)
    -h, --help              Show this help
USAGE
}

# Parse flags
while [[ $# -gt 0 ]]; do
    case "$1" in
        --chain-name)
            CHAIN_NAME="$2"; shift 2 ;;
        --version)
            MC_VERSION="$2"; MC_FILE="multichain-${MC_VERSION}.tar.gz"; MC_URL="https://www.multichain.com/download/${MC_FILE}"; shift 2 ;;
        --seed)
            SEED_NODE="$2"; shift 2 ;;
        --open-firewall)
            OPEN_FIREWALL=true; shift ;;
        --show-credentials)
            SHOW_CREDENTIALS=true; shift ;;
        -h|--help)
            usage; exit 0 ;;
        *)
            echo "Unknown option: $1" >&2; usage; exit 1 ;;
    esac
done

# Refresh derived vars after potential flag changes
CHAIN_DIR="$HOME/.multichain/${CHAIN_NAME}"

# OS / prereq checks
if ! command -v apt-get >/dev/null 2>&1; then
    echo "This script targets Debian/Ubuntu with apt-get. Use a compatible Linux or install manually." >&2
    exit 1
fi

if ! command -v sudo >/dev/null 2>&1; then
    echo "sudo is required to install binaries." >&2
    exit 1
fi

echo ">>> Updating package lists..."
sudo apt-get update -y

echo ">>> Installing dependencies (wget, tar, curl)..."
sudo apt-get install -y wget tar curl >/dev/null

# Download and install MultiChain (official method: download tar.gz, extract, copy binaries)
if ! command -v multichaind >/dev/null 2>&1; then
    echo ">>> Downloading MultiChain ${MC_VERSION}..."
    if [[ ! -f "${MC_FILE}" ]]; then
        wget -qO "${MC_FILE}" "${MC_URL}"
    else
        echo " Skipping download, file exists: ${MC_FILE}"
    fi

    echo ">>> Extracting MultiChain..."
    tar -xzf "${MC_FILE}"

    echo ">>> Installing MultiChain binaries to /usr/local/bin ..."
    pushd "multichain-${MC_VERSION}" >/dev/null
    sudo install -m 0755 multichaind multichain-cli multichain-util /usr/local/bin/
    popd >/dev/null

    # Cleanup extracted dir but keep archive for reference
    rm -rf "multichain-${MC_VERSION}"
else
    echo ">>> MultiChain already installed: $(multichaind --version)"
fi

echo ">>> Verifying installation..."
if ! multichaind --version >/dev/null 2>&1; then
    echo "MultiChain install failed or binaries not in PATH." >&2
    exit 1
fi

# Create blockchain if needed (idempotent and robust)
if [[ ! -f "${CHAIN_DIR}/params.dat" ]]; then
    echo ">>> No params.dat found. Creating blockchain: ${CHAIN_NAME}"
    multichain-util create "${CHAIN_NAME}"
else
    echo ">>> params.dat found, assuming blockchain '${CHAIN_NAME}' was created."
fi

# Start blockchain daemon (idempotent)
echo ">>> Starting blockchain: ${CHAIN_NAME} (daemon)"
if multichain-cli "${CHAIN_NAME}" getinfo >/dev/null 2>&1; then
    echo " Node appears to be running already."
else
    if [[ -n "${SEED_NODE}" ]]; then
        echo " Using seed node ${SEED_NODE}"
        multichaind "${CHAIN_NAME}@${SEED_NODE}" -daemon || true
    else
        multichaind "${CHAIN_NAME}" -daemon || true
    fi
fi

# Wait for node readiness
echo ">>> Waiting for node to respond..."
for i in {1..30}; do
    if multichain-cli "${CHAIN_NAME}" getinfo >/dev/null 2>&1; then
        break
    fi
    sleep 1
done

if ! multichain-cli "${CHAIN_NAME}" getinfo >/dev/null 2>&1; then
    echo "Node did not respond to getinfo in time." >&2
    exit 1
fi

# Determine actual ports
RPC_FILE="${CHAIN_DIR}/multichain.conf"
RPC_PORT=$(grep -E '^rpcport=' "${RPC_FILE}" 2>/dev/null | tail -n1 | cut -d'=' -f2 || true)
if [[ -z "${RPC_PORT}" ]]; then
    RPC_PORT=7448
fi
P2P_PORT=$(multichain-cli "${CHAIN_NAME}" getnetworkinfo 2>/dev/null \
    | awk '/"localaddresses"/ {flag=1} flag {print} flag && /\]/ {exit}' \
    | grep -Eo '"port"\s*:\s*[0-9]+' \
    | head -n1 \
    | grep -Eo '[0-9]+' || true)
if [[ -z "${P2P_PORT}" ]]; then
        P2P_PORT=$(grep -E '^default-network-port' "${CHAIN_DIR}/params.dat" 2>/dev/null \
            | awk -F'=' '{print $2}' \
            | sed 's/#.*$//' \
            | tr -d ' ' || true)
fi
if [[ -z "${P2P_PORT}" ]]; then
    P2P_PORT=7447
fi

# Optional firewall rules (official docs do not enforce firewall changes)
if [[ "${OPEN_FIREWALL}" == true ]]; then
    echo ">>> Configuring UFW firewall rules (${P2P_PORT}/tcp P2P, ${RPC_PORT}/tcp RPC) ..."
    if ! command -v ufw >/dev/null 2>&1; then
        sudo apt-get install -y ufw >/dev/null
    fi
    sudo ufw allow ${P2P_PORT}/tcp || true
    sudo ufw allow ${RPC_PORT}/tcp || true
    if ! sudo ufw status | grep -q "Status: active"; then
        echo " Note: UFW is not active. Rules will apply when it's enabled (sudo ufw enable)."
    fi
fi

echo "----------------------------------------------------"
echo " Blockchain '${CHAIN_NAME}' is running!"
echo "----------------------------------------------------"
echo " Connection string for other nodes:"
echo " multichaind ${CHAIN_NAME}@$(curl -fsSL ifconfig.me 2>/dev/null || echo "<your-ip>"):${P2P_PORT}"
echo "----------------------------------------------------"

if [[ "${SHOW_CREDENTIALS}" == true ]]; then
    if [[ -f "${RPC_FILE}" ]]; then
        echo " RPC Credentials (for API access)"
        echo "----------------------------------------------------"
        cat "${RPC_FILE}"
        echo "----------------------------------------------------"
    echo " Example CURL request:"
    echo " curl --user rpcuser:rpcpassword --data-binary '{\"method\":\"getinfo\",\"params\":[],\"id\":1}' -H 'content-type:text/plain;' http://$(curl -fsSL ifconfig.me 2>/dev/null || echo 127.0.0.1):${RPC_PORT}"
        echo "----------------------------------------------------"
    else
        echo "WARNING: RPC file not found at ${RPC_FILE}"
    fi
fi

echo "Setup complete."
