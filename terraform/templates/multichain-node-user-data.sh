#!/bin/bash
set -e

CHAIN_NAME="${chain_name}"
RPC_USER="${rpc_user}"
RPC_PASSWORD="${rpc_password}"
RPC_PORT="${rpc_port}"
NETWORK_PORT="${network_port}"
MC_VER="${multichain_version}"
NODE_NAME="${node_name}"
NODE_ROLE="${node_role}"

# ============================================
# INSTALL MULTICHAIN
# ============================================
# Fix curl conflict by removing curl-minimal first
sudo dnf remove -y curl-minimal 2>/dev/null || true
sudo dnf install -y docker git wget jq

systemctl enable docker
systemctl start docker

cd /opt
# Check if already downloaded
if [ ! -f "multichain-$${MC_VER}/multichaind" ]; then
  MC_TAR="multichain-$${MC_VER}.tar.gz"
  MC_URL="https://github.com/MultiChain/multichain/releases/download/v$${MC_VER}/$${MC_TAR}"
  wget -q "$${MC_URL}" -O "$${MC_TAR}"
  tar xzf "$${MC_TAR}"
fi
cd "multichain-$${MC_VER}"
cp multichaind multichain-cli /usr/local/bin/
mkdir -p /root/.multichain/$${CHAIN_NAME}

# ============================================
# CREATE ADMIN CREDENTIALS FILE
# ============================================
cat > /root/.multichain/$${CHAIN_NAME}/multichain.conf <<EOF
rpcuser=$${RPC_USER}
rpcpassword=$${RPC_PASSWORD}
rpcport=$${RPC_PORT}
port=$${NETWORK_PORT}
EOF

# ============================================
# CREATE BLOCKCHAIN PARAMETERS
# ============================================
# Check if blockchain params already exist
if [ ! -f "/root/.multichain/$${CHAIN_NAME}/params.dat" ]; then
  /usr/local/bin/multichain-util create $${CHAIN_NAME}
fi

# ============================================
# START THE BLOCKCHAIN (FIRST TIME)
# ============================================
echo "Starting MultiChain daemon for $${NODE_NAME}..."
if ! pgrep -f "multichaind $${CHAIN_NAME}" > /dev/null; then
  /usr/local/bin/multichaind $${CHAIN_NAME} -daemon
  sleep 5
fi

# Get our node address
MY_ADDRESS=$(/usr/local/bin/multichain-cli $${CHAIN_NAME} getinfo 2>/dev/null | jq -r '.nodeaddress' || echo "pending")
echo "Node address: $${MY_ADDRESS}"

# ============================================
# GRANT ADMIN PERMISSIONS TO OURSELVES
# ============================================
sleep 3
/usr/local/bin/multichain-cli $${CHAIN_NAME} grant $${MY_ADDRESS} admin,activate,mine,connect,send,receive 2>/dev/null || true

# ============================================
# STORE CONNECTION INFO FOR OTHER NODES
# ============================================
MY_IP=$(wget -q -O - ifconfig.me)
echo "My IP: $${MY_IP}"

# Write connection parameters to a shared location
cat > /var/lib/multichain-connection.txt <<EOF
CHAIN_NAME=$${CHAIN_NAME}
ADMIN_IP=$${MY_IP}
RPC_PORT=$${RPC_PORT}
NETWORK_PORT=$${NETWORK_PORT}
EOF

chmod 644 /var/lib/multichain-connection.txt

echo "========================================"
echo "Admin node '$${NODE_NAME}' started successfully!"
echo "Chain: $${CHAIN_NAME}"
echo "Node address: $${MY_ADDRESS}"
echo "Public IP: $${MY_IP}"
echo "RPC: $${MY_IP}:$${RPC_PORT}"
echo "P2P: $${MY_IP}:$${NETWORK_PORT}"
echo "========================================"

echo "Setup complete. MultiChain admin node is running."