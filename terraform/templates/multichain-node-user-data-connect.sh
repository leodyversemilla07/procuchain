#!/bin/bash
set -euxo pipefail

# ============================================
# ProcuChain — MultiChain PEER Node Bootstrap
# Connects to admin node and auto-discovers peers
# Docs: https://www.multichain.com/developers/creating-connecting/
#
# Flow per docs:
#   1. Run: multichaind chain1@ADMIN_IP:PORT -daemon
#   2. If private chain → admin must grant connect permission
#      (we set anyone-can-connect=true on admin, so this auto-approves)
#   3. Node downloads params, syncs chain, discovers peers automatically
#   4. Use addnode/storenode for explicit peer connections
# ============================================

CHAIN_NAME="${chain_name}"
RPC_USER="${rpc_user}"
RPC_PASSWORD="${rpc_password}"
RPC_PORT="${rpc_port}"
NETWORK_PORT="${network_port}"
MC_VER="${multichain_version}"
NODE_NAME="${node_name}"
NODE_ROLE="${node_role}"
ADMIN_IP="${admin_ip}"
PEER_IPS='${peer_ips}'

# Ensure clock is synced (critical for MultiChain — block sync fails with wrong clock)
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

# Create chain datadir
# Explicitly set HOME — cloud-init runs as root but HOME may be /
export HOME=/root
MC_DATADIR="/root/.multichain/$${CHAIN_NAME}"
mkdir -p "$${MC_DATADIR}"

# Write credentials (needed before first connect)
cat > "$${MC_DATADIR}/multichain.conf" <<EOF
rpcuser=$${RPC_USER}
rpcpassword=$${RPC_PASSWORD}
rpcport=$${RPC_PORT}
port=$${NETWORK_PORT}
rpcallowip=0.0.0.0/0
EOF

# ============================================
# WAIT FOR ADMIN NODE P2P PORT
# Per docs: connect using node address chain1@IP:PORT
# ============================================
echo "Waiting for admin node at $${ADMIN_IP}:$${NETWORK_PORT}..."
for i in $(seq 1 60); do
    if timeout 3 bash -c "echo > /dev/tcp/$${ADMIN_IP}/$${NETWORK_PORT}" 2>/dev/null; then
        echo "Admin node is reachable!"
        break
    fi
    echo "Waiting... ($$i/60)"
    sleep 10
done

# Give admin extra time to finish creating streams
sleep 15

# ============================================
# CONNECT TO THE BLOCKCHAIN
# Docs: multichaind chain1@12.34.56.78:8571 -daemon
# Since we set anyone-can-connect=true, no manual grant needed
# ============================================
echo "Connecting to blockchain $${CHAIN_NAME} at $${ADMIN_IP}:$${NETWORK_PORT}..."

# First connection uses the full node address format
/usr/local/bin/multichaind "$${CHAIN_NAME}@$${ADMIN_IP}:$${NETWORK_PORT}" -daemon

# Wait for connection and sync
CONNECTED=false
for i in $(seq 1 30); do
    INFO=$(/usr/local/bin/multichain-cli "$${CHAIN_NAME}" getinfo 2>/dev/null || echo "")
    if echo "$$INFO" | jq -r '.nodeaddress' 2>/dev/null | grep -q "@"; then
        CONNECTED=true
        BLOCKS=$(echo "$$INFO" | jq -r '.blocks')
        echo "Connected! Blocks synced: $${BLOCKS}"
        break
    fi
    echo "Waiting for connection... ($$i/30)"
    sleep 5
done

if [ "$CONNECTED" = false ]; then
    echo "WARNING: Could not confirm connection. Retrying..."
    # Per docs: reconnect using just chain name after initial attempt
    /usr/local/bin/multichain-cli "$${CHAIN_NAME}" stop 2>/dev/null || true
    sleep 5
    /usr/local/bin/multichaind "$${CHAIN_NAME}@$${ADMIN_IP}:$${NETWORK_PORT}" -daemon
    sleep 15
fi

# ============================================
# SUBSCRIBE TO ALL STREAMS
# ============================================
sleep 5
/usr/local/bin/multichain-cli "$${CHAIN_NAME}" liststreams true 2>/dev/null | jq -r '.[].name' 2>/dev/null | while read -r sname; do
    /usr/local/bin/multichain-cli "$${CHAIN_NAME}" subscribe "$${sname}" 2>/dev/null || true
done

# ============================================
# ADD PEER NODE CONNECTIONS (for full mesh)
# MultiChain auto-discovers peers, but we use addnode
# to speed up initial connectivity
# Docs: addnode ip:port add
# ============================================
if [ -n "$PEER_IPS" ] && [ "$PEER_IPS" != "{}" ]; then
    echo "Adding explicit peer connections..."

    echo "$PEER_IPS" | jq -r 'to_entries[] | "\(.key)=\(.value)"' | while IFS='=' read -r peer_name peer_ip; do
        if [ -n "$peer_ip" ] && [ "$peer_ip" != "null" ]; then
            echo "Adding peer $${peer_name} ($${peer_ip}:$${NETWORK_PORT})..."

            # Wait for peer's P2P port to be available
            for attempt in $(seq 1 15); do
                if timeout 3 bash -c "echo > /dev/tcp/$${peer_ip}/$${NETWORK_PORT}" 2>/dev/null; then
                    # Per docs: addnode ip:port add — queues peer for next available slot
                    /usr/local/bin/multichain-cli "$${CHAIN_NAME}" addnode "$${peer_ip}:$${NETWORK_PORT}" add 2>/dev/null || true

                    # Also use storenode (v2.3+) for persistent connection
                    /usr/local/bin/multichain-cli "$${CHAIN_NAME}" storenode "$${peer_ip}:$${NETWORK_PORT}" tryconnect 2>/dev/null || true
                    echo "Peer $${peer_name} added."
                    break
                fi
                echo "Waiting for peer $${peer_name}... ($$attempt/15)"
                sleep 10
            done
        fi
    done
fi

# ============================================
# VERIFY PEER CONNECTIONS
# ============================================
sleep 10
echo ""
echo "========================================"
echo "NODE '$${NODE_NAME}' (role: $${NODE_ROLE})"
echo "Chain: $${CHAIN_NAME}"
echo "========================================"

echo "Peer connections:"
/usr/local/bin/multichain-cli "$${CHAIN_NAME}" getpeerinfo 2>/dev/null | \
    jq -r '.[] | "  Peer: \(.addr) inbound=\(.inbound)"' 2>/dev/null || echo "  (retrieving peer info...)"

echo "Node info:"
/usr/local/bin/multichain-cli "$${CHAIN_NAME}" getinfo 2>/dev/null | \
    jq '{blocks, connections, nodeaddress}' 2>/dev/null || echo "  (retrieving node info...)"

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

echo "Setup complete. Node '$${NODE_NAME}' is running."
