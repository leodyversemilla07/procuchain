#!/bin/bash
# =============================================================================
# ProcuChain MultiChain Peer Node Startup Script
# =============================================================================
# This script runs on first boot of peer nodes (app, witness, backup) to:
# 1. Mount the data disk
# 2. Install MultiChain
# 3. Connect to the admin node
# 4. Configure RPC access
# 5. Set up systemd service
# =============================================================================

set -euo pipefail

# Logging
exec > >(tee -a /var/log/multichain-setup.log) 2>&1
echo "=== MultiChain Peer Node Setup Started at $(date) ==="

# -----------------------------------------------------------------------------
# Get metadata
# -----------------------------------------------------------------------------

CHAIN_NAME=$(curl -s -H "Metadata-Flavor: Google" \
    "http://metadata.google.internal/computeMetadata/v1/instance/attributes/chain-name")
MULTICHAIN_VERSION=$(curl -s -H "Metadata-Flavor: Google" \
    "http://metadata.google.internal/computeMetadata/v1/instance/attributes/multichain-version")
RPC_PORT=$(curl -s -H "Metadata-Flavor: Google" \
    "http://metadata.google.internal/computeMetadata/v1/instance/attributes/rpc-port")
P2P_PORT=$(curl -s -H "Metadata-Flavor: Google" \
    "http://metadata.google.internal/computeMetadata/v1/instance/attributes/p2p-port")
RPC_USERNAME=$(curl -s -H "Metadata-Flavor: Google" \
    "http://metadata.google.internal/computeMetadata/v1/instance/attributes/rpc-username")
SECRET_ID=$(curl -s -H "Metadata-Flavor: Google" \
    "http://metadata.google.internal/computeMetadata/v1/instance/attributes/secret-id")
ADMIN_NODE_IP=$(curl -s -H "Metadata-Flavor: Google" \
    "http://metadata.google.internal/computeMetadata/v1/instance/attributes/admin-node-ip")
NODE_ROLE=$(curl -s -H "Metadata-Flavor: Google" \
    "http://metadata.google.internal/computeMetadata/v1/instance/attributes/node-role")

echo "Configuration:"
echo "  Chain Name: $CHAIN_NAME"
echo "  Node Role: $NODE_ROLE"
echo "  MultiChain Version: $MULTICHAIN_VERSION"
echo "  RPC Port: $RPC_PORT"
echo "  P2P Port: $P2P_PORT"
echo "  Admin Node IP: $ADMIN_NODE_IP"

# -----------------------------------------------------------------------------
# Mount data disk
# -----------------------------------------------------------------------------

DATA_DISK="/dev/disk/by-id/google-multichain-data"
MOUNT_POINT="/data/multichain"

if [ ! -d "$MOUNT_POINT" ]; then
    echo "Setting up data disk..."
    
    # Format if not already formatted
    if ! blkid "$DATA_DISK" > /dev/null 2>&1; then
        echo "Formatting data disk..."
        mkfs.ext4 -m 0 -E lazy_itable_init=0,lazy_journal_init=0,discard "$DATA_DISK"
    fi
    
    # Create mount point and mount
    mkdir -p "$MOUNT_POINT"
    mount -o discard,defaults "$DATA_DISK" "$MOUNT_POINT"
    
    # Add to fstab for persistence
    echo "UUID=$(blkid -s UUID -o value $DATA_DISK) $MOUNT_POINT ext4 discard,defaults,nofail 0 2" >> /etc/fstab
    
    echo "Data disk mounted at $MOUNT_POINT"
fi

# -----------------------------------------------------------------------------
# Create multichain user
# -----------------------------------------------------------------------------

if ! id "multichain" &>/dev/null; then
    echo "Creating multichain user..."
    useradd -r -m -d /home/multichain -s /bin/bash multichain
fi

# Set up multichain home directory symlink to data disk
MULTICHAIN_HOME="/home/multichain/.multichain"
if [ ! -L "$MULTICHAIN_HOME" ]; then
    mkdir -p "$MOUNT_POINT/data"
    chown -R multichain:multichain "$MOUNT_POINT"
    
    rm -rf "$MULTICHAIN_HOME"
    ln -s "$MOUNT_POINT/data" "$MULTICHAIN_HOME"
    chown -h multichain:multichain "$MULTICHAIN_HOME"
fi

# -----------------------------------------------------------------------------
# Install dependencies
# -----------------------------------------------------------------------------

echo "Installing dependencies..."
apt-get update
apt-get install -y curl wget jq

# Install Google Cloud SDK for Secret Manager access
if ! command -v gcloud &> /dev/null; then
    echo "Installing Google Cloud SDK..."
    apt-get install -y apt-transport-https ca-certificates gnupg
    echo "deb [signed-by=/usr/share/keyrings/cloud.google.gpg] https://packages.cloud.google.com/apt cloud-sdk main" | \
        tee -a /etc/apt/sources.list.d/google-cloud-sdk.list
    curl https://packages.cloud.google.com/apt/doc/apt-key.gpg | \
        apt-key --keyring /usr/share/keyrings/cloud.google.gpg add -
    apt-get update && apt-get install -y google-cloud-sdk
fi

# -----------------------------------------------------------------------------
# Install MultiChain
# -----------------------------------------------------------------------------

MULTICHAIN_DIR="/usr/local/multichain"

if [ ! -f "$MULTICHAIN_DIR/multichaind" ]; then
    echo "Installing MultiChain $MULTICHAIN_VERSION..."
    
    cd /tmp
    wget -q "https://www.multichain.com/download/multichain-${MULTICHAIN_VERSION}.tar.gz"
    tar -xzf "multichain-${MULTICHAIN_VERSION}.tar.gz"
    
    mkdir -p "$MULTICHAIN_DIR"
    cp "multichain-${MULTICHAIN_VERSION}/multichain"* "$MULTICHAIN_DIR/"
    chmod +x "$MULTICHAIN_DIR/multichain"*
    
    # Add to PATH
    echo "export PATH=\$PATH:$MULTICHAIN_DIR" >> /etc/profile.d/multichain.sh
    
    # Cleanup
    rm -rf "multichain-${MULTICHAIN_VERSION}"*
    
    echo "MultiChain installed successfully"
fi

export PATH="$PATH:$MULTICHAIN_DIR"

# -----------------------------------------------------------------------------
# Get RPC password from Secret Manager
# -----------------------------------------------------------------------------

echo "Retrieving RPC password from Secret Manager..."
RPC_PASSWORD=$(gcloud secrets versions access latest --secret="$SECRET_ID" 2>/dev/null || echo "")

if [ -z "$RPC_PASSWORD" ]; then
    echo "ERROR: Could not retrieve RPC password from Secret Manager"
    exit 1
fi

# -----------------------------------------------------------------------------
# Wait for admin node to be ready
# -----------------------------------------------------------------------------

echo "Waiting for admin node to be ready..."
MAX_RETRIES=60
RETRY_COUNT=0

while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
    if nc -z "$ADMIN_NODE_IP" "$P2P_PORT" 2>/dev/null; then
        echo "Admin node is reachable"
        break
    fi
    RETRY_COUNT=$((RETRY_COUNT + 1))
    echo "Waiting for admin node... ($RETRY_COUNT/$MAX_RETRIES)"
    sleep 10
done

if [ $RETRY_COUNT -eq $MAX_RETRIES ]; then
    echo "ERROR: Admin node not reachable after $MAX_RETRIES attempts"
    exit 1
fi

# Additional wait to ensure admin node is fully initialized
sleep 30

# -----------------------------------------------------------------------------
# Connect to blockchain
# -----------------------------------------------------------------------------

CHAIN_DIR="$MULTICHAIN_HOME/$CHAIN_NAME"

if [ ! -d "$CHAIN_DIR" ]; then
    echo "Connecting to blockchain at $ADMIN_NODE_IP:$P2P_PORT..."
    
    # First connection attempt - this will fail but download chain params
    sudo -u multichain "$MULTICHAIN_DIR/multichaind" \
        "${CHAIN_NAME}@${ADMIN_NODE_IP}:${P2P_PORT}" \
        -daemon=0 \
        -printtoconsole &
    
    CONNECT_PID=$!
    sleep 15
    
    # Kill the initial connection (it's waiting for grant)
    kill $CONNECT_PID 2>/dev/null || true
    wait $CONNECT_PID 2>/dev/null || true
    
    echo "Chain parameters downloaded"
    echo ""
    echo "=========================================="
    echo "IMPORTANT: Manual step required!"
    echo "=========================================="
    echo ""
    echo "On the ADMIN node, run the following command to grant connect permission:"
    echo ""
    
    # Get the wallet address
    if [ -f "$CHAIN_DIR/wallet.dat" ]; then
        # Start temporarily to get address
        sudo -u multichain "$MULTICHAIN_DIR/multichaind" "$CHAIN_NAME" -daemon
        sleep 5
        NODE_ADDRESS=$(sudo -u multichain "$MULTICHAIN_DIR/multichain-cli" "$CHAIN_NAME" getaddresses | jq -r '.[0]')
        sudo -u multichain "$MULTICHAIN_DIR/multichain-cli" "$CHAIN_NAME" stop 2>/dev/null || true
        sleep 3
        
        echo "  multichain-cli $CHAIN_NAME grant $NODE_ADDRESS connect,send,receive,mine"
        echo ""
        echo "Node wallet address: $NODE_ADDRESS"
    else
        echo "  (Wallet not yet created - check logs after first connection)"
    fi
    echo ""
    echo "=========================================="
fi

# -----------------------------------------------------------------------------
# Configure multichain.conf
# -----------------------------------------------------------------------------

CONF_FILE="$CHAIN_DIR/multichain.conf"

echo "Configuring multichain.conf..."

# Determine allowed IPs based on node role
if [ "$NODE_ROLE" = "app" ]; then
    # App nodes accept RPC from load balancer and internal network
    RPC_ALLOW_IPS="rpcallowip=10.0.0.0/8
rpcallowip=127.0.0.1
rpcallowip=130.211.0.0/22
rpcallowip=35.191.0.0/16"
else
    # Witness and backup nodes only accept local RPC
    RPC_ALLOW_IPS="rpcallowip=127.0.0.1
rpcallowip=10.0.0.0/8"
fi

cat > "$CONF_FILE" << EOF
# RPC Configuration
rpcuser=$RPC_USERNAME
rpcpassword=$RPC_PASSWORD
rpcport=$RPC_PORT

# Allow RPC from specified networks
$RPC_ALLOW_IPS

# Performance tuning
rpcthreads=4
rpctimeout=60

# Logging
debug=mchn
debug=mcapi

# Server settings
server=1
daemon=0

# Add seed node
addnode=$ADMIN_NODE_IP:$P2P_PORT
EOF

chown multichain:multichain "$CONF_FILE"
chmod 600 "$CONF_FILE"

# -----------------------------------------------------------------------------
# Create systemd service
# -----------------------------------------------------------------------------

echo "Creating systemd service..."
cat > /etc/systemd/system/multichaind.service << EOF
[Unit]
Description=MultiChain Daemon - $CHAIN_NAME ($NODE_ROLE)
After=network.target

[Service]
Type=simple
User=multichain
Group=multichain
ExecStart=$MULTICHAIN_DIR/multichaind $CHAIN_NAME -daemon=0 -printtoconsole
ExecStop=$MULTICHAIN_DIR/multichain-cli $CHAIN_NAME stop
Restart=always
RestartSec=10
TimeoutStartSec=300
TimeoutStopSec=120

# Security hardening
NoNewPrivileges=true
ProtectSystem=strict
ProtectHome=read-only
ReadWritePaths=$MOUNT_POINT
PrivateTmp=true

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable multichaind

# -----------------------------------------------------------------------------
# Create permission request script
# -----------------------------------------------------------------------------

cat > /home/multichain/request-permissions.sh << 'PERM_SCRIPT'
#!/bin/bash
# Run this after admin has granted initial connect permission

CHAIN_NAME="__CHAIN_NAME__"
MULTICHAIN_DIR="/usr/local/multichain"

# Start the daemon
sudo systemctl start multichaind
sleep 10

# Get node info
NODE_ADDRESS=$($MULTICHAIN_DIR/multichain-cli $CHAIN_NAME getaddresses | jq -r '.[0]')
NODE_INFO=$($MULTICHAIN_DIR/multichain-cli $CHAIN_NAME getinfo)

echo "Node connected successfully!"
echo "Wallet Address: $NODE_ADDRESS"
echo ""
echo "Node Info:"
echo "$NODE_INFO" | jq '.'
echo ""
echo "To grant full permissions on admin node, run:"
echo "  multichain-cli $CHAIN_NAME grant $NODE_ADDRESS connect,send,receive,mine"
PERM_SCRIPT

sed -i "s/__CHAIN_NAME__/$CHAIN_NAME/g" /home/multichain/request-permissions.sh
chmod +x /home/multichain/request-permissions.sh
chown multichain:multichain /home/multichain/request-permissions.sh

# -----------------------------------------------------------------------------
# Start MultiChain (will retry until permissions granted)
# -----------------------------------------------------------------------------

echo "Starting MultiChain..."
systemctl start multichaind || true

# Create a helper script to check connection status
cat > /home/multichain/check-status.sh << 'STATUS_SCRIPT'
#!/bin/bash
CHAIN_NAME="__CHAIN_NAME__"
MULTICHAIN_DIR="/usr/local/multichain"

echo "=== MultiChain Node Status ==="
echo ""

if systemctl is-active --quiet multichaind; then
    echo "Service: RUNNING"
    echo ""
    $MULTICHAIN_DIR/multichain-cli $CHAIN_NAME getinfo 2>/dev/null || echo "RPC not responding (may need permissions)"
else
    echo "Service: STOPPED"
    echo ""
    echo "Check logs: journalctl -u multichaind -n 50"
fi
STATUS_SCRIPT

sed -i "s/__CHAIN_NAME__/$CHAIN_NAME/g" /home/multichain/check-status.sh
chmod +x /home/multichain/check-status.sh
chown multichain:multichain /home/multichain/check-status.sh

# -----------------------------------------------------------------------------
# Display status
# -----------------------------------------------------------------------------

INTERNAL_IP=$(curl -s -H "Metadata-Flavor: Google" \
    "http://metadata.google.internal/computeMetadata/v1/instance/network-interfaces/0/ip")

echo ""
echo "=== Peer Node Setup Complete ==="
echo ""
echo "Node Role: $NODE_ROLE"
echo "Internal IP: $INTERNAL_IP"
echo ""
echo "NEXT STEPS:"
echo "1. On the ADMIN node, grant connect permissions for this node"
echo "2. Run: sudo -u multichain /home/multichain/check-status.sh"
echo "3. If connected, node will automatically sync blockchain"
echo ""
echo "Useful commands:"
echo "  Check status: /home/multichain/check-status.sh"
echo "  View logs: journalctl -u multichaind -f"
echo "  Restart: sudo systemctl restart multichaind"
echo ""

echo "=== Setup Complete at $(date) ==="
