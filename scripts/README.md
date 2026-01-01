# MultiChain Setup Scripts

This directory contains scripts for setting up and managing MultiChain nodes for the Procuchain blockchain.

## Scripts Overview

| Script | Purpose |
|--------|---------|
| `install_procuchain.sh` | Install MultiChain and create a new blockchain node |
| `join_procuchain.sh` | Join an existing Procuchain network as a peer node |
| `setup_multichain_ssl.sh` | Configure SSL/TLS for secure RPC connections |
| `migrate_multichain.sh` | Migrate node data to a new server |

---

## Quick Start

### Option 1: Create a New Blockchain (Seed Node)

```bash
# Basic installation with defaults
./install_procuchain.sh

# With custom chain name
MULTICHAIN_CHAIN_NAME=mychain ./install_procuchain.sh

# Allow external RPC connections from specific IP range
MULTICHAIN_RPC_ALLOW_IP=10.0.0.0/8 ./install_procuchain.sh
```

### Option 2: Join Existing Network (Peer Node)

```bash
# Join the default Procuchain network
./join_procuchain.sh

# Join with custom seed node
MULTICHAIN_SEED_HOST=192.168.1.100 MULTICHAIN_P2P_PORT=7447 ./join_procuchain.sh
```

---

## Environment Variables

### install_procuchain.sh

| Variable | Default | Description |
|----------|---------|-------------|
| `MULTICHAIN_CHAIN_NAME` | `procuchain` | Name of the blockchain to create |
| `MULTICHAIN_RPC_ALLOW_IP` | *(localhost only)* | Additional IP/CIDR to allow RPC access |

### join_procuchain.sh

| Variable | Default | Description |
|----------|---------|-------------|
| `MULTICHAIN_CHAIN_NAME` | `procuchain` | Name of the blockchain to join |
| `MULTICHAIN_SEED_HOST` | `159.65.12.99` | IP or hostname of the seed node |
| `MULTICHAIN_P2P_PORT` | `6487` | P2P port of the seed node |

### setup_multichain_ssl.sh

| Variable | Default | Description |
|----------|---------|-------------|
| `MULTICHAIN_CHAIN_NAME` | `procuchain` | Name of the blockchain |
| `MULTICHAIN_SSL_DOMAIN` | `procuchain.tech` | Domain for SSL certificate |

---

## Detailed Usage

### Creating a New Blockchain

The `install_procuchain.sh` script performs the following:

1. Installs MultiChain 2.3.3 binaries from the official source
2. Creates a new blockchain with the specified name
3. Configures RPC settings (port, allowed IPs)
4. Opens firewall ports for P2P and RPC traffic
5. Outputs Laravel `.env` configuration values

```bash
./install_procuchain.sh
```

**Output example:**
```
====================================================
 Laravel .env Configuration
====================================================
MULTICHAIN_CHAIN_NAME=procuchain
MULTICHAIN_RPC_HOST=203.0.113.10
MULTICHAIN_RPC_PORT=7448
MULTICHAIN_RPC_USERNAME=multichainrpc
MULTICHAIN_RPC_PASSWORD=<generated-password>
====================================================
```

### Joining an Existing Network

The `join_procuchain.sh` script:

1. Installs MultiChain binaries if not present
2. Connects to the specified seed node
3. Waits for connection and validates status
4. Reports if admin permission is needed

```bash
MULTICHAIN_SEED_HOST=10.0.0.5 ./join_procuchain.sh
```

**Note:** The seed node administrator must grant connect permission to your wallet address:

```bash
# On the seed node
multichain-cli procuchain grant <peer-wallet-address> connect,send,receive
```

### Setting Up SSL

For production environments, enable SSL for RPC connections:

```bash
# Interactive mode - choose between self-signed or Let's Encrypt
sudo ./setup_multichain_ssl.sh
```

After SSL setup, update your Laravel `.env`:
```env
MULTICHAIN_USE_SSL=true
MULTICHAIN_VERIFY_SSL=true
```

---

## Node Migration

The `migrate_multichain.sh` script handles full data migration between servers.

### Export Backup (Source Server)

```bash
# Create backup archive
./migrate_multichain.sh export

# Output: /tmp/procuchain-migration-20260101-120000.tar.gz
```

### Import Backup (Destination Server)

```bash
# Restore from backup
./migrate_multichain.sh import /path/to/procuchain-migration-*.tar.gz
```

### Full Migration via SSH

```bash
# Migrate directly to new server (requires SSH access)
./migrate_multichain.sh migrate admin@procuchain-admin-new.example.com
```

### Verify After Migration

```bash
./migrate_multichain.sh verify
```

For detailed migration procedures, see [Node Migration Guide](../docs/MULTICHAIN_NODE_MIGRATION.md).

---

## Docker Development Environment

For local development, use Docker Compose:

```bash
# Start the multichain dev container
docker compose up multichain -d

# Check logs
docker logs procuchain-multichain-dev

# Test RPC connection
curl -X POST http://localhost:7450 \
  -H "Content-Type: application/json" \
  -u multichainrpc:multichainrpc \
  -d '{"method":"getinfo","params":[],"id":1}'
```

### Docker Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `CHAIN_NAME` | `procuchain-dev` | Chain name for development |
| `RPC_USER` | `multichainrpc` | RPC username |
| `RPC_PASSWORD` | `multichainrpc` | RPC password |
| `RPC_PORT` | `7450` | RPC port |
| `P2P_PORT` | `7449` | P2P port |
| `RPC_ALLOW_IP` | `172.16.0.0/12` | Allowed IP range for RPC |

---

## Security Considerations

### RPC Access Control

By default, RPC access is restricted to localhost only. To allow external connections:

```bash
# Allow specific IP
MULTICHAIN_RPC_ALLOW_IP=10.0.0.5 ./install_procuchain.sh

# Allow subnet
MULTICHAIN_RPC_ALLOW_IP=10.0.0.0/24 ./install_procuchain.sh
```

**⚠️ Never use `0.0.0.0/0` in production** - this allows RPC access from anywhere.

### SSL/TLS

For production deployments:
1. Use Let's Encrypt certificates (option 2 in `setup_multichain_ssl.sh`)
2. Set `MULTICHAIN_USE_SSL=true` in Laravel
3. Set `MULTICHAIN_VERIFY_SSL=true` for certificate validation

### Firewall

The scripts automatically configure UFW firewall rules. Ensure these ports are open:

| Port | Protocol | Purpose |
|------|----------|---------|
| P2P Port (default: 7447) | TCP | Peer-to-peer communication |
| RPC Port (default: 7448) | TCP | JSON-RPC API access |

---

## Troubleshooting

### Node won't start

Check the debug log:
```bash
tail -100 ~/.multichain/procuchain/debug.log
```

### RPC connection refused

1. Verify the node is running:
   ```bash
   multichain-cli procuchain getinfo
   ```

2. Check `rpcallowip` in `~/.multichain/procuchain/multichain.conf`

3. Verify firewall rules:
   ```bash
   sudo ufw status
   ```

### Peer connection pending

The seed node admin must grant connect permission:
```bash
# Get your wallet address
multichain-cli procuchain getaddresses

# Ask admin to run on seed node:
multichain-cli procuchain grant <your-address> connect
```

### Health check failing (Docker)

Verify RPC credentials match between environment and config:
```bash
docker exec procuchain-multichain-dev cat /home/multichain/.multichain/procuchain-dev/multichain.conf
```

---

## Laravel Integration

After running the install script, add the output values to your `.env`:

```env
MULTICHAIN_CHAIN_NAME=procuchain
MULTICHAIN_RPC_HOST=127.0.0.1
MULTICHAIN_RPC_PORT=7448
MULTICHAIN_RPC_USERNAME=multichainrpc
MULTICHAIN_RPC_PASSWORD=<your-generated-password>
MULTICHAIN_USE_SSL=false
MULTICHAIN_VERIFY_SSL=false
```

Test the connection:
```bash
php artisan multichain:setup --check
```

Complete the blockchain setup:
```bash
php artisan multichain:setup
```

---

## File Locations

| Path | Description |
|------|-------------|
| `~/.multichain/<chain>/` | Blockchain data directory |
| `~/.multichain/<chain>/multichain.conf` | Node configuration |
| `~/.multichain/<chain>/params.dat` | Blockchain parameters |
| `~/.multichain/<chain>/debug.log` | Debug log file |
| `~/.multichain/<chain>/wallet.dat` | Wallet file (backup this!) |

---

## Additional Resources

- [MultiChain Official Documentation](https://www.multichain.com/developers/)
- [MultiChain JSON-RPC API](https://www.multichain.com/developers/json-rpc-api/)
- [Procuchain Laravel Integration](../app/Libraries/MultiChain/README.md)
