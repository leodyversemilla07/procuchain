# MultiChain Production Node Setup Guide

This guide covers setting up a 5-node MultiChain network for Procuchain in a production environment.

## Node Naming Convention

| Node Name | Role | Description |
|-----------|------|-------------|
| **procuchain-admin** | Admin/Seed | Primary administrative node, creates blockchain |
| **procuchain-app-primary** | App Node 1 | Handles web application RPC requests |
| **procuchain-app-secondary** | App Node 2 | Handles queue worker RPC requests |
| **procuchain-witness** | Witness | Independent validator in different location |
| **procuchain-backup** | Backup/DR | Disaster recovery cold standby |

## Architecture Overview

```
                                    ┌─────────────────────────┐
                                    │    procuchain-admin     │
                                    │      (Seed Node)        │
                                    │     Administrative      │
                                    └───────────┬─────────────┘
                                                │ P2P (Port 6487)
                    ┌───────────────────────────┼───────────────────────────┐
                    │                           │                           │
                    ▼                           ▼                           ▼
         ┌─────────────────────┐   ┌─────────────────────┐   ┌─────────────────────┐
         │ procuchain-app-     │   │ procuchain-app-     │   │ procuchain-witness  │
         │      primary        │   │     secondary       │   │   (Validator)       │
         │   Web App RPC       │   │   Queue Workers     │   │   Independent       │
         └──────────┬──────────┘   └──────────┬──────────┘   └─────────────────────┘
                    │                         │
                    │ RPC (Port 6486)         │ RPC              ┌─────────────────────┐
                    ▼                         ▼                  │  procuchain-backup  │
         ┌─────────────────────┐   ┌─────────────────────┐       │  (Disaster Recov.)  │
         │    Laravel Web      │   │   Laravel Queue     │       └─────────────────────┘
         │    Application      │   │      Workers        │
         └─────────────────────┘   └─────────────────────┘
```

## Node Specifications

| Node Name | Role | Recommended Specs | Location |
|-----------|------|-------------------|----------|
| procuchain-admin | Admin/Seed | 2 CPU, 4GB RAM, 50GB SSD | Primary datacenter |
| procuchain-app-primary | App Node 1 | 2 CPU, 4GB RAM, 50GB SSD | Same as web server |
| procuchain-app-secondary | App Node 2 | 2 CPU, 4GB RAM, 50GB SSD | Same as queue workers |
| procuchain-witness | Witness | 1 CPU, 2GB RAM, 30GB SSD | Different datacenter |
| procuchain-backup | Backup/DR | 1 CPU, 2GB RAM, 50GB SSD | Different region |

## Network Ports

| Port | Protocol | Purpose | Exposed To |
|------|----------|---------|------------|
| 6487 | TCP | P2P (peer-to-peer) | All nodes |
| 6486 | TCP | RPC (JSON-RPC API) | App servers only |

---

## Step 1: Set Up procuchain-admin (Seed Node)

This is the first node that creates the blockchain.

### 1.1 Install MultiChain

```bash
ssh admin@procuchain-admin.example.com

# Download and run install script
curl -sSL https://raw.githubusercontent.com/your-org/procuchain/main/scripts/install_procuchain.sh -o install.sh
chmod +x install.sh

# Install with secure RPC (localhost only for admin node)
./install.sh
```

### 1.2 Save the Output

The script outputs important information. Save these values:

```
----------------------------------------------------
 MultiChain 'procuchain' node is running
 P2P Port : 6487
 RPC Port : 6486
 RPC User : multichainrpc
----------------------------------------------------
 Connect other nodes with:
   multichaind procuchain@<ADMIN_NODE_IP>:6487
----------------------------------------------------
```

### 1.3 Configure Admin Node Security

Edit the multichain.conf to restrict RPC access:

```bash
nano ~/.multichain/procuchain/multichain.conf
```

```ini
# Only allow localhost RPC on admin node
rpcallowip=127.0.0.1
rpcport=6486
port=6487
```

Restart the node:

```bash
multichain-cli procuchain stop
multichaind procuchain -daemon
```

---

## Step 2: Set Up procuchain-app-primary (Web App Node)

This node handles web application RPC requests.

### 2.1 Join the Network

```bash
ssh admin@procuchain-app-primary.example.com

# Download join script
curl -sSL https://raw.githubusercontent.com/your-org/procuchain/main/scripts/join_procuchain.sh -o join.sh
chmod +x join.sh

# Join the network (replace with procuchain-admin's IP)
MULTICHAIN_SEED_HOST=<ADMIN_NODE_IP> MULTICHAIN_P2P_PORT=6487 ./join.sh
```

### 2.2 Request Permissions

The script will display your wallet address. On **procuchain-admin**, grant permissions:

```bash
# On procuchain-admin - Grant full app permissions
multichain-cli procuchain grant <APP_PRIMARY_WALLET_ADDRESS> connect,send,receive,mine
```

### 2.3 Configure RPC Access

On procuchain-app-primary, configure RPC to allow connections from your Laravel app:

```bash
nano ~/.multichain/procuchain/multichain.conf
```

```ini
rpcuser=multichainrpc
rpcpassword=<GENERATE_SECURE_PASSWORD>
rpcport=6486
port=6487

# Allow RPC from localhost and app servers
rpcallowip=127.0.0.1
rpcallowip=<LARAVEL_APP_SERVER_IP>
# Or use CIDR for a subnet
# rpcallowip=10.0.1.0/24
```

Restart the node:

```bash
multichain-cli procuchain stop
multichaind procuchain -daemon
```

### 2.4 Verify Connection

```bash
multichain-cli procuchain getinfo
```

Expected output should show `connections: 1` (connected to procuchain-admin).

---

## Step 3: Set Up procuchain-app-secondary (Queue Worker Node)

Repeat Step 2 for procuchain-app-secondary, which handles queue worker RPC requests.

```bash
ssh admin@procuchain-app-secondary.example.com

MULTICHAIN_SEED_HOST=<ADMIN_NODE_IP> MULTICHAIN_P2P_PORT=6487 ./join.sh
```

On **procuchain-admin**, grant permissions:

```bash
multichain-cli procuchain grant <APP_SECONDARY_WALLET_ADDRESS> connect,send,receive,mine
```

Configure RPC access for queue workers in `multichain.conf`.

---

## Step 4: Set Up procuchain-witness (Validator Node)

The witness node provides independent block validation from a different location.

### 4.1 Join Network

```bash
ssh admin@procuchain-witness.example.com

MULTICHAIN_SEED_HOST=<ADMIN_NODE_IP> MULTICHAIN_P2P_PORT=6487 ./join.sh
```

### 4.2 Grant Mining Permission Only

On **procuchain-admin**:

```bash
# Witness only needs connect and mine permissions
multichain-cli procuchain grant <WITNESS_WALLET_ADDRESS> connect,mine
```

### 4.3 Configure (No RPC Exposure)

```bash
nano ~/.multichain/procuchain/multichain.conf
```

```ini
# Witness node - no external RPC access
rpcallowip=127.0.0.1
rpcport=6486
port=6487
```

---

## Step 5: Set Up procuchain-backup (Disaster Recovery Node)

The backup node maintains a copy of the blockchain for disaster recovery.

### 5.1 Join Network

```bash
ssh admin@procuchain-backup.example.com

MULTICHAIN_SEED_HOST=<ADMIN_NODE_IP> MULTICHAIN_P2P_PORT=6487 ./join.sh
```

### 5.2 Grant Minimal Permissions

On **procuchain-admin**:

```bash
# Backup node only needs to receive and sync data
multichain-cli procuchain grant <BACKUP_WALLET_ADDRESS> connect,receive
```

### 5.3 Configure as Cold Standby

```bash
nano ~/.multichain/procuchain/multichain.conf
```

```ini
# Backup node - receive only, no mining
rpcallowip=127.0.0.1
rpcport=6486
port=6487
```

---

## Step 6: Configure Laravel Application

### 6.1 Update .env

Configure your Laravel application to connect to procuchain-app-primary:

```env
# Primary connection to procuchain-app-primary
MULTICHAIN_CHAIN_NAME=procuchain
MULTICHAIN_RPC_HOST=<APP_PRIMARY_IP>
MULTICHAIN_RPC_PORT=6486
MULTICHAIN_RPC_USERNAME=multichainrpc
MULTICHAIN_RPC_PASSWORD=<APP_PRIMARY_RPC_PASSWORD>
MULTICHAIN_USE_SSL=false
MULTICHAIN_VERIFY_SSL=false

# Connection settings
MULTICHAIN_CONNECTION_TIMEOUT=10
MULTICHAIN_MAX_RETRIES=3
MULTICHAIN_RETRY_DELAY=1
```

### 6.2 For Queue Workers (Optional Separate Connection)

If queue workers run on a different server, they can connect to procuchain-app-secondary:

```env
# In queue worker environment
MULTICHAIN_RPC_HOST=<APP_SECONDARY_IP>
```

### 6.3 Test Connection

```bash
php artisan multichain:setup --check
```

Expected output:

```
✓ Connected to MultiChain node
  Chain: procuchain
  Version: 2.3.3
  Blocks: 1234
  Connections: 4
```

---

## Step 7: Enable SSL (Production)

For production, enable SSL on App Nodes (procuchain-app-primary and procuchain-app-secondary).

### 7.1 Run SSL Setup

On each app node:

```bash
sudo ./scripts/setup_multichain_ssl.sh
```

Choose option 2 (Let's Encrypt) for production.

### 7.2 Update Laravel .env

```env
MULTICHAIN_USE_SSL=true
MULTICHAIN_VERIFY_SSL=true
```

---

## Step 8: Set Up Load Balancer (Optional)

For high availability, place a load balancer in front of App Nodes.

### HAProxy Configuration

```haproxy
frontend multichain_rpc
    bind *:6486
    mode tcp
    default_backend multichain_nodes

backend multichain_nodes
    mode tcp
    balance roundrobin
    option tcp-check
    
    server procuchain-app-primary <APP_PRIMARY_IP>:6486 check inter 5000 rise 2 fall 3
    server procuchain-app-secondary <APP_SECONDARY_IP>:6486 check inter 5000 rise 2 fall 3 backup
```

### Update Laravel .env

```env
MULTICHAIN_RPC_HOST=<LOAD_BALANCER_IP>
MULTICHAIN_RPC_PORT=6486
```

---

## Verification Checklist

After setup, verify all nodes are working:

### On procuchain-admin

```bash
multichain-cli procuchain getpeerinfo
```

Should show 4 connected peers.

### On Any Node

```bash
multichain-cli procuchain listpermissions mine
```

Should show procuchain-admin, procuchain-app-primary, procuchain-app-secondary, and procuchain-witness with mine permissions.

### Check Block Sync

```bash
# Run on each node - block count should match
multichain-cli procuchain getblockcount
```

### Test from Laravel

```bash
php artisan tinker
```

```php
$manager = app(\App\Services\Manager::class);
$info = $manager->getinfo();
echo "Blocks: " . $info['blocks'] . ", Connections: " . $info['connections'];
```

---

## Network Summary

| Node Name | IP | Wallet Address | Permissions | RPC Access |
|-----------|-----|----------------|-------------|------------|
| procuchain-admin | x.x.x.x | 1Abc... | admin,mine,activate,send,receive | localhost only |
| procuchain-app-primary | x.x.x.x | 1Def... | connect,send,receive,mine | App servers |
| procuchain-app-secondary | x.x.x.x | 1Ghi... | connect,send,receive,mine | Queue workers |
| procuchain-witness | x.x.x.x | 1Jkl... | connect,mine | localhost only |
| procuchain-backup | x.x.x.x | 1Mno... | connect,receive | localhost only |

---

## Maintenance Commands

### Check Node Status

```bash
multichain-cli procuchain getinfo
```

### View Connected Peers

```bash
multichain-cli procuchain getpeerinfo
```

### Check Permissions

```bash
multichain-cli procuchain listpermissions "*" "<WALLET_ADDRESS>"
```

### Stop Node

```bash
multichain-cli procuchain stop
```

### Start Node

```bash
multichaind procuchain -daemon
```

### Backup Wallet

```bash
cp ~/.multichain/procuchain/wallet.dat ~/backups/wallet-$(date +%Y%m%d).dat
```

---

## Troubleshooting

### Node Won't Connect to Peers

1. Check firewall allows port 6487:
   ```bash
   sudo ufw status
   sudo ufw allow 6487/tcp
   ```

2. Verify seed node is running:
   ```bash
   multichain-cli procuchain getinfo
   ```

3. Check if permission was granted:
   ```bash
   multichain-cli procuchain listpermissions connect <YOUR_ADDRESS>
   ```

### RPC Connection Refused

1. Verify `rpcallowip` includes your client IP
2. Check RPC port is correct
3. Verify credentials match

### Blocks Not Syncing

1. Check peer connections:
   ```bash
   multichain-cli procuchain getpeerinfo
   ```

2. Check for chain forks:
   ```bash
   multichain-cli procuchain getblockchaininfo
   ```

### Mining Stopped

With `mining-diversity=0.3`, at least 2 of 5 miners (40%) must be online. If too many nodes are down, mining stops.

Check active miners:
```bash
multichain-cli procuchain listpermissions mine
```

---

## Security Best Practices

1. **Never expose Admin Node RPC** to external networks
2. **Use unique RPC passwords** for each node
3. **Enable SSL** for all RPC connections in production
4. **Restrict `rpcallowip`** to specific IPs, never use `0.0.0.0/0`
5. **Regular backups** of wallet.dat files
6. **Monitor node health** with automated checks
7. **Keep nodes updated** with latest MultiChain version
8. **Use private network** (VPN/VPC) for inter-node communication
9. **Separate admin credentials** from application credentials
10. **Audit permissions** regularly with `listpermissions`
