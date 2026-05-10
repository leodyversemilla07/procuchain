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
ADMIN_IP="${admin_ip}"

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
  MC_URL="https://www.multichain.com/download/multichain-$${MC_VER}.tar.gz"
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
# WAIT FOR ADMIN NODE TO BE READY
# ============================================
echo "Waiting for admin node ($${ADMIN_IP}) to be ready..."
MAX_RETRIES=30
RETRY_COUNT=0

while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
  # Check if admin node responds on P2P port
  if timeout 5 bash -c "echo > /dev/tcp/$${ADMIN_IP}/$${NETWORK_PORT}" 2>/dev/null; then
    echo "Admin node is reachable, proceeding..."
    break
  fi
  RETRY_COUNT=$((RETRY_COUNT + 1))
  echo "Waiting for admin... ($${RETRY_COUNT}/$${MAX_RETRIES})"
  sleep 10
done

if [ $RETRY_COUNT -eq $MAX_RETRIES ]; then
  echo "WARNING: Admin node not reachable after ${MAX_RETRIES} retries."
fi

# ============================================
# CONNECT TO EXISTING BLOCKCHAIN
# ============================================
echo "Connecting $${NODE_NAME} to blockchain $${CHAIN_NAME}..."
echo "Connecting to: $${ADMIN_IP}:$${NETWORK_PORT}"

# Wait a bit more for admin to fully initialize blockchain
sleep 15

# Try to connect to the blockchain
/usr/local/bin/multichaind $${CHAIN_NAME}@$${ADMIN_IP}:$${NETWORK_PORT} -daemon

# Wait for connection
sleep 15

# Check connection status
CONNECTED=false
for i in {1..12}; do
  INFO=$(/usr/local/bin/multichain-cli $${CHAIN_NAME} getinfo 2>/dev/null || echo "")
  if echo "$$INFO" | grep -q "nodeaddress"; then
    CONNECTED=true
    break
  fi
  echo "Waiting for connection... ($$i/12)"
  sleep 5
done

if [ "$CONNECTED" = true ]; then
  echo "Successfully connected to blockchain!"
  MY_ADDRESS=$(/usr/local/bin/multichain-cli $${CHAIN_NAME} getinfo | jq -r '.nodeaddress')
  echo "My address: $${MY_ADDRESS}"
  
  # Grant permissions to ourselves
  /usr/local/bin/multichain-cli $${CHAIN_NAME} grant $${MY_ADDRESS} connect,send,receive,issue 2>/dev/null || true
  /usr/local/bin/multichain-cli $${CHAIN_NAME} grant $${MY_ADDRESS} admin 2>/dev/null || true
  
  echo "Node $${NODE_NAME} (role: $${NODE_ROLE}) connected successfully!"
else
  echo "WARNING: Could not confirm connection to blockchain."
  echo "The node may need admin to grant permissions."
fi

# Get node info
echo ""
echo "========================================"
echo "Node '$${NODE_NAME}' connection complete!"
echo "Chain: $${CHAIN_NAME}"
echo "Role: $${NODE_ROLE}"
echo "========================================"

echo "Setup complete. Node is running."