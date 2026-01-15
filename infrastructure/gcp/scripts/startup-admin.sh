#!/bin/bash
# =============================================================================
# ProcuChain MultiChain Admin Node Startup Script
# =============================================================================
# This script runs on first boot of the admin node to:
# 1. Mount the data disk
# 2. Install MultiChain
# 3. Create the blockchain
# 4. Configure RPC access
# 5. Set up systemd service
# =============================================================================

set -euo pipefail

# Logging
exec > >(tee -a /var/log/multichain-setup.log) 2>&1
echo "=== MultiChain Admin Node Setup Started at $(date) ==="

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

echo "Configuration:"
echo "  Chain Name: $CHAIN_NAME"
echo "  MultiChain Version: $MULTICHAIN_VERSION"
echo "  RPC Port: $RPC_PORT"
echo "  P2P Port: $P2P_PORT"

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
# Create blockchain (if not exists)
# -----------------------------------------------------------------------------

CHAIN_DIR="$MULTICHAIN_HOME/$CHAIN_NAME"

if [ ! -d "$CHAIN_DIR" ]; then
    echo "Creating blockchain: $CHAIN_NAME..."
    
    sudo -u multichain "$MULTICHAIN_DIR/multichain-util" create "$CHAIN_NAME" \
        -default-network-port="$P2P_PORT" \
        -default-rpc-port="$RPC_PORT" \
        -anyone-can-connect=false \
        -anyone-can-send=false \
        -anyone-can-receive=false \
        -anyone-can-mine=false \
        -anyone-can-admin=false \
        -mining-diversity=0.3 \
        -mine-empty-rounds=10 \
        -mining-turnover=0.5 \
        -target-block-time=15 \
        -max-std-tx-size=4194304 \
        -max-std-op-returns-count=1024 \
        -max-std-op-return-size=2097152 \
        -max-std-op-drops-count=5 \
        -max-std-element-size=32768
    
    echo "Blockchain created"
fi

# -----------------------------------------------------------------------------
# Configure multichain.conf
# -----------------------------------------------------------------------------

CONF_FILE="$CHAIN_DIR/multichain.conf"

echo "Configuring multichain.conf..."
cat > "$CONF_FILE" << EOF
# RPC Configuration
rpcuser=$RPC_USERNAME
rpcpassword=$RPC_PASSWORD
rpcport=$RPC_PORT

# Allow RPC from internal network only
rpcallowip=10.0.0.0/8
rpcallowip=127.0.0.1

# Performance tuning
rpcthreads=4
rpctimeout=60

# Logging
debug=mchn
debug=mcapi

# Server settings
server=1
daemon=0
EOF

chown multichain:multichain "$CONF_FILE"
chmod 600 "$CONF_FILE"

# -----------------------------------------------------------------------------
# Create systemd service
# -----------------------------------------------------------------------------

echo "Creating systemd service..."
cat > /etc/systemd/system/multichaind.service << EOF
[Unit]
Description=MultiChain Daemon - $CHAIN_NAME
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
# Start MultiChain
# -----------------------------------------------------------------------------

echo "Starting MultiChain..."
systemctl start multichaind

# Wait for RPC to be ready
echo "Waiting for RPC to be ready..."
for i in {1..30}; do
    if sudo -u multichain "$MULTICHAIN_DIR/multichain-cli" "$CHAIN_NAME" getinfo > /dev/null 2>&1; then
        echo "MultiChain RPC is ready"
        break
    fi
    echo "Waiting... ($i/30)"
    sleep 5
done

# -----------------------------------------------------------------------------
# Display node address for peer connections
# -----------------------------------------------------------------------------

echo ""
echo "=== Admin Node Setup Complete ==="
echo ""

NODE_ADDRESS=$(sudo -u multichain "$MULTICHAIN_DIR/multichain-cli" "$CHAIN_NAME" getinfo | jq -r '.nodeaddress')
INTERNAL_IP=$(curl -s -H "Metadata-Flavor: Google" "http://metadata.google.internal/computeMetadata/v1/instance/network-interfaces/0/ip")

echo "Node Address: $NODE_ADDRESS"
echo "Internal IP: $INTERNAL_IP"
echo ""
echo "For peer nodes to connect, use:"
echo "  multichaind $CHAIN_NAME@${INTERNAL_IP}:${P2P_PORT}"
echo ""

# Save node address to metadata file for other scripts
echo "$NODE_ADDRESS" > "$CHAIN_DIR/node_address.txt"
chown multichain:multichain "$CHAIN_DIR/node_address.txt"

# -----------------------------------------------------------------------------
# Set up daily backup cron job
# -----------------------------------------------------------------------------

echo "Setting up backup cron job..."

BACKUP_BUCKET=$(curl -s -H "Metadata-Flavor: Google" \
    "http://metadata.google.internal/computeMetadata/v1/project/attributes/backup-bucket" 2>/dev/null || echo "")

if [ -n "$BACKUP_BUCKET" ]; then
    cat > /etc/cron.daily/multichain-backup << 'BACKUP_SCRIPT'
#!/bin/bash
CHAIN_NAME="__CHAIN_NAME__"
BACKUP_BUCKET="__BACKUP_BUCKET__"
BACKUP_DIR="/tmp/multichain-backup"
DATE=$(date +%Y%m%d-%H%M%S)

mkdir -p "$BACKUP_DIR"

# Stop daemon briefly for consistent backup
systemctl stop multichaind

# Copy wallet
cp /data/multichain/data/$CHAIN_NAME/wallet.dat "$BACKUP_DIR/wallet-$DATE.dat"

# Restart daemon
systemctl start multichaind

# Upload to GCS
gsutil cp "$BACKUP_DIR/wallet-$DATE.dat" "gs://$BACKUP_BUCKET/wallet-backups/"

# Cleanup
rm -rf "$BACKUP_DIR"

echo "Backup completed: wallet-$DATE.dat"
BACKUP_SCRIPT

    sed -i "s/__CHAIN_NAME__/$CHAIN_NAME/g" /etc/cron.daily/multichain-backup
    sed -i "s/__BACKUP_BUCKET__/$BACKUP_BUCKET/g" /etc/cron.daily/multichain-backup
    chmod +x /etc/cron.daily/multichain-backup
    
    echo "Backup cron job configured"
fi

echo "=== Setup Complete at $(date) ==="
