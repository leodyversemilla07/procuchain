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

# Create initial multichain.conf with secure RPC settings
if [ ! -f "${RPC_CONF}" ]; then
    echo "Creating initial multichain.conf..."
    mkdir -p "${CHAIN_DIR}"
    # Write RPC credentials and security settings
    cat > "${RPC_CONF}" << EOF
rpcuser=${RPC_USER}
rpcpassword=${RPC_PASSWORD}
rpcport=${RPC_PORT}
port=${P2P_PORT}
rpcallowip=127.0.0.1
rpcallowip=${RPC_ALLOW_IP:-172.16.0.0/12}
EOF
else
    # Update existing config with our credentials (overwrite auto-generated ones)
    # Remove existing rpcuser/rpcpassword lines and add our own
    sed -i '/^rpcuser=/d' "${RPC_CONF}"
    sed -i '/^rpcpassword=/d' "${RPC_CONF}"
    echo "rpcuser=${RPC_USER}" >> "${RPC_CONF}"
    echo "rpcpassword=${RPC_PASSWORD}" >> "${RPC_CONF}"
    
    # Add other settings if not present
    if ! grep -q "^rpcport=" "${RPC_CONF}"; then
        echo "rpcport=${RPC_PORT}" >> "${RPC_CONF}"
    fi
    if ! grep -q "^port=" "${RPC_CONF}"; then
        echo "port=${P2P_PORT}" >> "${RPC_CONF}"
    fi
    if ! grep -q '^rpcallowip=127.0.0.1' "${RPC_CONF}"; then
        echo "rpcallowip=127.0.0.1" >> "${RPC_CONF}"
    fi
    # Use environment variable for additional allowed IPs, default to Docker network
    if ! grep -q "^rpcallowip=${RPC_ALLOW_IP:-172.16.0.0/12}" "${RPC_CONF}"; then
        echo "rpcallowip=${RPC_ALLOW_IP:-172.16.0.0/12}" >> "${RPC_CONF}"
    fi
fi

# Start the daemon with RPC bind to all interfaces
echo "Starting MultiChain daemon for '${CHAIN_NAME}'..."
exec multichaind ${CHAIN_NAME} -rpcbind=0.0.0.0
