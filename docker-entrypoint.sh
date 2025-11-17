#!/bin/bash
set -e

CHAIN_DIR="/home/multichain/.multichain/${CHAIN_NAME}"
RPC_CONF="${CHAIN_DIR}/multichain.conf"

# Create blockchain if it doesn't exist
if [ ! -f "${CHAIN_DIR}/params.dat" ]; then
    echo "Creating '${CHAIN_NAME}' development blockchain..."
    multichain-util create ${CHAIN_NAME} \
        -default-network-port=${P2P_PORT} \
        -default-rpc-port=${RPC_PORT}
fi

# Create initial multichain.conf with allow IPs
if [ ! -f "${RPC_CONF}" ]; then
    echo "Creating initial multichain.conf..."
    mkdir -p "${CHAIN_DIR}"
    cat > "${RPC_CONF}" << EOF
rpcallowip=127.0.0.1
rpcallowip=0.0.0.0/0
EOF
fi

# Configure RPC settings if not already set
if [ -f "${RPC_CONF}" ]; then
    if ! grep -q "^rpcport=" "${RPC_CONF}"; then
        echo "rpcport=${RPC_PORT}" >> "${RPC_CONF}"
    fi
    if ! grep -q "^port=" "${RPC_CONF}"; then
        echo "port=${P2P_PORT}" >> "${RPC_CONF}"
    fi
    if ! grep -q '^rpcallowip=127.0.0.1' "${RPC_CONF}"; then
        echo "rpcallowip=127.0.0.1" >> "${RPC_CONF}"
    fi
    if ! grep -q '^rpcallowip=0.0.0.0/0' "${RPC_CONF}"; then
        echo "rpcallowip=0.0.0.0/0" >> "${RPC_CONF}"
    fi
fi

# Start the daemon with explicit RPC credentials and bind to all interfaces
echo "Starting MultiChain daemon for '${CHAIN_NAME}'..."
exec multichaind ${CHAIN_NAME} -rpcuser=${RPC_USER} -rpcpassword=${RPC_PASSWORD} -rpcport=${RPC_PORT} -rpcbind=0.0.0.0 -rpcallowip=0.0.0.0/0
