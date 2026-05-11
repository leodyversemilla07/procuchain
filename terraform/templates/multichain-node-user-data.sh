#!/bin/bash
set -euxo pipefail

# ============================================
# ProcuChain — MultiChain ADMIN Node Bootstrap
# Creates the blockchain and grants permissions
# Docs: https://www.multichain.com/developers/creating-connecting/
# ============================================

CHAIN_NAME="${chain_name}"
RPC_USER="${rpc_user}"
RPC_PASSWORD="${rpc_password}"
RPC_PORT="${rpc_port}"
NETWORK_PORT="${network_port}"
MC_VER="${multichain_version}"
NODE_NAME="${node_name}"

# Ensure clock is synced (critical for MultiChain)
dnf install -y chrony 2>/dev/null || true
systemctl enable chronyd 2>/dev/null || true
systemctl start chronyd 2>/dev/null || true

# Install dependencies (AL2023: curl conflicts with curl-minimal)
dnf install -y --allowerasing git wget jq curl

# Install AWS SSM Agent for remote management
dnf install -y amazon-ssm-agent
systemctl enable amazon-ssm-agent
systemctl start amazon-ssm-agent

# ============================================
# INSTALL MULTICHAIN
# ============================================
MC_DIR="/opt/multichain-$${MC_VER}"
if [ ! -f "$${MC_DIR}/multichaind" ]; then
    cd /opt
    wget -q "https://www.multichain.com/download/multichain-$${MC_VER}.tar.gz" -O multichain.tar.gz
    tar xzf multichain.tar.gz
    rm -f multichain.tar.gz
fi
cp "$${MC_DIR}/multichaind" "$${MC_DIR}/multichain-cli" "$${MC_DIR}/multichain-util" /usr/local/bin/

# ============================================
# CREATE BLOCKCHAIN (if not already created)
# ============================================
# Explicitly set HOME — cloud-init runs as root but HOME may be /
export HOME=/root
MC_DATADIR="/root/.multichain/$${CHAIN_NAME}"

if [ ! -f "$${MC_DATADIR}/params.dat" ]; then
 /usr/local/bin/multichain-util create "$${CHAIN_NAME}"

    # Allow any node to connect without manual permission grant
    # This is key for automated deployments
    # Docs: https://multichain.com/developers/blockchain-parameters/
    sed -i "s/anyone-can-connect = false/anyone-can-connect = true/" "$${MC_DATADIR}/params.dat"
    sed -i "s/anyone-can-send = false/anyone-can-send = true/" "$${MC_DATADIR}/params.dat"
    sed -i "s/anyone-can-receive = false/anyone-can-receive = true/" "$${MC_DATADIR}/params.dat"
    sed -i "s/anyone-can-create = false/anyone-can-create = true/" "$${MC_DATADIR}/params.dat"
    sed -i "s/anyone-can-issue = false/anyone-can-issue = true/" "$${MC_DATADIR}/params.dat"
    sed -i "s/anyone-can-mine = false/anyone-can-mine = true/" "$${MC_DATADIR}/params.dat"
    sed -i "s/anyone-can-activate = false/anyone-can-activate = true/" "$${MC_DATADIR}/params.dat"

    # Set custom ports
    sed -i "s/default-network-port = .*/default-network-port = $${NETWORK_PORT}/" "$${MC_DATADIR}/params.dat"
    sed -i "s/default-rpc-port = .*/default-rpc-port = $${RPC_PORT}/" "$${MC_DATADIR}/params.dat"

    echo "Blockchain created with anyone-can-* permissions and custom ports."
fi

# Write RPC credentials
cat > "$${MC_DATADIR}/multichain.conf" <<EOF
rpcuser=$${RPC_USER}
rpcpassword=$${RPC_PASSWORD}
rpcport=$${RPC_PORT}
port=$${NETWORK_PORT}
rpcallowip=0.0.0.0/0
EOF

# ============================================
# START THE BLOCKCHAIN
# Docs: multichaind [chain-name] -daemon
# ============================================
if ! pgrep -f "multichaind $${CHAIN_NAME}" > /dev/null 2>&1; then
    /usr/local/bin/multichaind "$${CHAIN_NAME}" -daemon
    # Wait for genesis block
    for i in $(seq 1 30); do
        if /usr/local/bin/multichain-cli "$${CHAIN_NAME}" getinfo > /dev/null 2>&1; then
            echo "Blockchain started successfully."
            break
        fi
        echo "Waiting for blockchain to initialize... ($$i/30)"
        sleep 2
    done
fi

# Get node info
NODE_ADDRESS=$(/usr/local/bin/multichain-cli "$${CHAIN_NAME}" getinfo 2>/dev/null | jq -r '.nodeaddress' || echo "pending")
MY_IP=$(curl -s ifconfig.me 2>/dev/null || wget -q -O - ifconfig.me 2>/dev/null || echo "unknown")
BLOCKS=$(/usr/local/bin/multichain-cli "$${CHAIN_NAME}" getinfo 2>/dev/null | jq -r '.blocks' || echo "0")

echo "Admin node address: $${NODE_ADDRESS}"
echo "Admin public IP: $${MY_IP}"
echo "Current blocks: $${BLOCKS}"

# ============================================
# CREATE PROCUCHAIN STREAMS
# These are the data stores the Laravel app uses
# ============================================
for stream in procurement.data procurement.documents procurement.status procurement.events procurement.corrections file.data file.chunks file.metadata; do
    /usr/local/bin/multichain-cli "$${CHAIN_NAME}" create stream "$${stream}" true 2>/dev/null || \
        echo "Stream '$${stream}' already exists or will auto-subscribe."
done

# Subscribe to all streams
/usr/local/bin/multichain-cli "$${CHAIN_NAME}" liststreams true 2>/dev/null | jq -r '.[].name' 2>/dev/null | while read -r sname; do
    /usr/local/bin/multichain-cli "$${CHAIN_NAME}" subscribe "$${sname}" 2>/dev/null || true
done

# ============================================
# STORE CONNECTION INFO FOR OTHER NODES
# ============================================
cat > /var/lib/multichain-connection.txt <<EOF
CHAIN_NAME=$${CHAIN_NAME}
ADMIN_IP=$${MY_IP}
NODE_ADDRESS=$${NODE_ADDRESS}
RPC_PORT=$${RPC_PORT}
NETWORK_PORT=$${NETWORK_PORT}
EOF
chmod 644 /var/lib/multichain-connection.txt

# ============================================
# SYSTEMD SERVICE — keeps MultiChain running
# ============================================
cat > /etc/systemd/system/multichaind.service <<SYSTEMD
[Unit]
Description=MultiChain Daemon ($${CHAIN_NAME})
After=network.target chronyd.service
Wants=chronyd.service

[Service]
Type=forking
ExecStart=/usr/local/bin/multichaind $${CHAIN_NAME} -daemon
ExecStop=/usr/local/bin/multichain-cli $${CHAIN_NAME} stop
Restart=always
RestartSec=15
User=root

[Install]
WantedBy=multi-user.target
SYSTEMD

systemctl daemon-reload
systemctl enable multichaind

echo "========================================"
echo "ADMIN NODE '$${NODE_NAME}' READY"
echo "Chain: $${CHAIN_NAME}"
echo "Node address: $${NODE_ADDRESS}"
echo "Public IP: $${MY_IP}"
echo "P2P port: $${NETWORK_PORT}"
echo "RPC port: $${RPC_PORT}"
echo "Other nodes connect with:"
echo "  multichaind $${CHAIN_NAME}@$${MY_IP}:$${NETWORK_PORT} -daemon"
echo "========================================"
