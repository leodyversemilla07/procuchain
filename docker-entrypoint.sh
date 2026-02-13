#!/bin/bash
set -e

CHAIN_DIR="/home/multichain/.multichain/${CHAIN_NAME}"
RPC_CONF="${CHAIN_DIR}/multichain.conf"

# Create blockchain if it doesn't exist
if [ ! -f "${CHAIN_DIR}/params.dat" ]; then
    echo "Creating '${CHAIN_NAME}' blockchain..."
    multichain-util create ${CHAIN_NAME} \
        -default-network-port=${P2P_PORT} \
        -default-rpc-port=${RPC_PORT}
fi

# Create/update multichain.conf
mkdir -p "${CHAIN_DIR}"
cat > "${RPC_CONF}" << EOF
rpcuser=${RPC_USER}
rpcpassword=${RPC_PASSWORD}
rpcport=${RPC_PORT}
port=${P2P_PORT}
rpcallowip=${RPC_ALLOW_IP:-0.0.0.0/0}
EOF

# Start the daemon
echo "Starting MultiChain daemon for '${CHAIN_NAME}'..."
exec multichaind ${CHAIN_NAME} -rpcbind=0.0.0.0
