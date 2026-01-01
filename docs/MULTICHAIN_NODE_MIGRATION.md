# MultiChain Node Migration Guide

This guide covers migrating MultiChain nodes, including full data migration and wallet-only migration strategies.

## Migration Scenarios

| Scenario | Recommended Method | Downtime |
|----------|-------------------|----------|
| Move admin to new server | Full Data Migration | 5-30 minutes |
| Replace failed hardware | Full Data Migration | Depends on backup |
| Add admin privileges to existing node | Wallet Migration | None |
| Disaster recovery | Full Data Migration from Backup | 10-60 minutes |
| Promote backup to primary | Wallet Migration | Minimal |

---

## Data Directory Structure

Before migrating, understand what data exists:

```
~/.multichain/procuchain/
├── params.dat              # Blockchain parameters (immutable)
├── multichain.conf         # Node configuration (RPC settings)
├── wallet.dat              # ⚠️ CRITICAL: Private keys & addresses
├── blocks/                 # Blockchain data
│   ├── blk00000.dat
│   ├── blk00001.dat
│   └── ...
├── chainstate/             # UTXO database
├── stream/                 # Stream indexes
├── debug.log               # Log file (not needed)
├── peers.dat               # Peer list (can regenerate)
└── .lock                   # Lock file (do not copy)
```

### Critical Files

| File/Directory | Importance | Must Migrate? |
|----------------|------------|---------------|
| `wallet.dat` | Contains private keys | ✅ **REQUIRED** |
| `params.dat` | Blockchain parameters | ✅ Required |
| `blocks/` | Blockchain data | ✅ Required for full migration |
| `chainstate/` | UTXO state | ✅ Required for full migration |
| `stream/` | Stream indexes | ✅ Required for full migration |
| `multichain.conf` | Node config | ⚠️ Review and update |
| `debug.log` | Logs | ❌ Not needed |
| `peers.dat` | Peer cache | ❌ Will regenerate |
| `.lock` | Process lock | ❌ **Never copy** |

---

## Method 1: Full Data Migration

Use this method to move a node completely to a new server with all blockchain data.

### Prerequisites

- SSH access to both old and new servers
- Sufficient disk space on new server
- Same MultiChain version on both servers

### Step 1: Stop the Node

```bash
# On OLD server (procuchain-admin)
ssh admin@procuchain-admin-old.example.com

# Gracefully stop the daemon
multichain-cli procuchain stop

# Verify it's stopped
ps aux | grep multichaind
```

### Step 2: Create Backup Archive

```bash
# On OLD server
cd ~/.multichain

# Create compressed archive (excluding lock and logs)
tar --exclude='procuchain/.lock' \
    --exclude='procuchain/debug.log' \
    -czvf procuchain-backup-$(date +%Y%m%d-%H%M%S).tar.gz \
    procuchain/

# Verify archive
tar -tzvf procuchain-backup-*.tar.gz | head -20
```

### Step 3: Transfer to New Server

```bash
# Option A: Direct transfer via SCP
scp procuchain-backup-*.tar.gz admin@procuchain-admin-new.example.com:~/

# Option B: Via rsync (resumable, better for large data)
rsync -avz --progress procuchain-backup-*.tar.gz \
    admin@procuchain-admin-new.example.com:~/
```

### Step 4: Install MultiChain on New Server

```bash
# On NEW server
ssh admin@procuchain-admin-new.example.com

# Install MultiChain binaries (without creating chain)
cd /tmp
wget https://www.multichain.com/download/multichain-2.3.3.tar.gz
tar -xvzf multichain-2.3.3.tar.gz
sudo mv multichain-2.3.3/multichaind multichain-2.3.3/multichain-cli \
    multichain-2.3.3/multichain-util /usr/local/bin/
rm -rf multichain-2.3.3*

# Verify installation
multichaind --version
```

### Step 5: Restore Data

```bash
# On NEW server
cd ~

# Create multichain directory
mkdir -p ~/.multichain

# Extract backup
tar -xzvf procuchain-backup-*.tar.gz -C ~/.multichain/

# Verify extraction
ls -la ~/.multichain/procuchain/
```

### Step 6: Update Configuration

```bash
# On NEW server
nano ~/.multichain/procuchain/multichain.conf
```

Update settings for new server:

```ini
rpcuser=multichainrpc
rpcpassword=<NEW_SECURE_PASSWORD>
rpcport=6486
port=6487

# Update allowed IPs for new network location
rpcallowip=127.0.0.1
rpcallowip=<NEW_ALLOWED_IPS>
```

### Step 7: Start Node on New Server

```bash
# On NEW server
multichaind procuchain -daemon

# Wait for startup
sleep 5

# Verify node is running
multichain-cli procuchain getinfo
```

### Step 8: Verify Migration

```bash
# Check wallet addresses are present
multichain-cli procuchain getaddresses

# Check permissions
multichain-cli procuchain listpermissions "*" $(multichain-cli procuchain getaddresses | grep -o '"[^"]*"' | head -1 | tr -d '"')

# Check peer connections
multichain-cli procuchain getpeerinfo

# Verify block count matches network
multichain-cli procuchain getblockcount
```

### Step 9: Update DNS/Firewall

1. Update DNS records to point to new server IP
2. Update firewall rules on new server
3. Notify other nodes of new IP (if using static peer lists)

### Step 10: Decommission Old Server

```bash
# On OLD server - only after verifying new server works
# Securely delete wallet data
shred -vfz -n 5 ~/.multichain/procuchain/wallet.dat
rm -rf ~/.multichain/procuchain/
```

---

## Method 2: Wallet-Only Migration

Use this method to transfer admin privileges to an existing node without moving all blockchain data.

### When to Use

- Promoting an existing node to admin
- Adding admin redundancy
- Node already has synced blockchain

### Step 1: Export Private Key from Source

```bash
# On SOURCE node (current admin)
# Get the admin wallet address
multichain-cli procuchain listpermissions admin

# Export private key for that address
multichain-cli procuchain dumpprivkey <ADMIN_ADDRESS>
```

**⚠️ SECURITY WARNING**: The private key output gives full control of that wallet. Handle with extreme care:
- Never send via email or chat
- Use encrypted transfer methods
- Delete from terminal history after use

### Step 2: Import Private Key to Target

```bash
# On TARGET node
multichain-cli procuchain importprivkey <PRIVATE_KEY> "" true

# The third parameter 'true' triggers a rescan of the blockchain
# This may take several minutes for large chains
```

### Step 3: Verify Import

```bash
# On TARGET node
# Check that address is now in wallet
multichain-cli procuchain getaddresses

# Verify permissions transferred
multichain-cli procuchain listpermissions admin
```

### Step 4: Test Admin Functions

```bash
# On TARGET node - test an admin operation
# Create a test stream (requires admin)
multichain-cli procuchain create stream test-migration-stream true

# If successful, delete the test stream data
multichain-cli procuchain purge test-migration-stream
```

---

## Method 3: Disaster Recovery

Use this when the original node is no longer accessible.

### From procuchain-backup Node

```bash
# On procuchain-backup
# Stop the backup node
multichain-cli procuchain stop

# The backup has full blockchain data but limited permissions
# You need to import an admin wallet to gain admin access

# If you have admin private key backed up:
multichaind procuchain -daemon
multichain-cli procuchain importprivkey <ADMIN_PRIVATE_KEY>

# Upgrade permissions if needed
multichain-cli procuchain grant $(multichain-cli procuchain getaddresses | head -1) admin,mine
```

### From Cold Backup Files

```bash
# On NEW server
# Restore from backup archive
tar -xzvf procuchain-backup-YYYYMMDD.tar.gz -C ~/.multichain/

# Start node
multichaind procuchain -daemon

# Verify wallet and permissions
multichain-cli procuchain getaddresses
multichain-cli procuchain listpermissions admin
```

---

## Post-Migration Checklist

After any migration, verify:

- [ ] Node is running: `multichain-cli procuchain getinfo`
- [ ] Wallet addresses present: `multichain-cli procuchain getaddresses`
- [ ] Admin permissions intact: `multichain-cli procuchain listpermissions admin`
- [ ] Connected to peers: `multichain-cli procuchain getpeerinfo`
- [ ] Block count matches: Compare with other nodes
- [ ] Can perform admin operations: Test creating a stream
- [ ] RPC accessible: Test from Laravel app
- [ ] Firewall configured: Ports 6486, 6487 open
- [ ] Old server decommissioned: Wallet data securely deleted

---

## Backup Best Practices

### Automated Backup Script

Create `/usr/local/bin/backup-multichain.sh`:

```bash
#!/bin/bash
set -euo pipefail

CHAIN_NAME="procuchain"
BACKUP_DIR="/var/backups/multichain"
RETENTION_DAYS=30

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Create backup filename with timestamp
BACKUP_FILE="$BACKUP_DIR/${CHAIN_NAME}-$(date +%Y%m%d-%H%M%S).tar.gz"

# Create backup (node can stay running for wallet.dat backup)
tar --exclude="${CHAIN_NAME}/.lock" \
    --exclude="${CHAIN_NAME}/debug.log" \
    -czf "$BACKUP_FILE" \
    -C ~/.multichain \
    "$CHAIN_NAME/wallet.dat" \
    "$CHAIN_NAME/params.dat" \
    "$CHAIN_NAME/multichain.conf"

# Set secure permissions
chmod 600 "$BACKUP_FILE"

# Remove old backups
find "$BACKUP_DIR" -name "${CHAIN_NAME}-*.tar.gz" -mtime +$RETENTION_DAYS -delete

echo "Backup created: $BACKUP_FILE"
```

### Schedule Daily Backups

```bash
# Add to crontab
crontab -e

# Add this line for daily backup at 2 AM
0 2 * * * /usr/local/bin/backup-multichain.sh >> /var/log/multichain-backup.log 2>&1
```

### Off-Site Backup

```bash
# Sync to remote storage
aws s3 sync /var/backups/multichain/ s3://your-bucket/multichain-backups/

# Or to another server
rsync -avz /var/backups/multichain/ backup-server:/backups/multichain/
```

---

## Troubleshooting Migration Issues

### "Wallet is encrypted"

```bash
# Unlock wallet before exporting
multichain-cli procuchain walletpassphrase "your-passphrase" 60
multichain-cli procuchain dumpprivkey <ADDRESS>
```

### "Address not found" after import

```bash
# Trigger blockchain rescan
multichain-cli procuchain rescan
```

### Node won't start after migration

```bash
# Check for lock file
rm -f ~/.multichain/procuchain/.lock

# Check debug log
tail -100 ~/.multichain/procuchain/debug.log

# Try with reindex
multichaind procuchain -daemon -reindex
```

### Permissions missing after import

```bash
# The imported key has the address, but check if permissions exist
multichain-cli procuchain listpermissions "*" <IMPORTED_ADDRESS>

# If permissions show, wait for blockchain sync
multichain-cli procuchain getblockcount
```

### Peer connection issues after IP change

```bash
# Clear old peer data
rm ~/.multichain/procuchain/peers.dat

# Manually add known peers
multichain-cli procuchain addnode <KNOWN_PEER_IP>:6487 add
```

---

## Security Considerations

1. **Encrypt backups**: Use GPG or similar for backup encryption
   ```bash
   gpg --symmetric --cipher-algo AES256 procuchain-backup.tar.gz
   ```

2. **Secure transfer**: Always use encrypted channels (SCP, SFTP, rsync over SSH)

3. **Clear history**: Remove private keys from shell history
   ```bash
   history -d $(history | tail -1 | awk '{print $1}')
   ```

4. **Verify checksums**: Confirm backup integrity before and after transfer
   ```bash
   sha256sum procuchain-backup.tar.gz > procuchain-backup.sha256
   # After transfer
   sha256sum -c procuchain-backup.sha256
   ```

5. **Secure deletion**: Use `shred` to securely delete sensitive files
   ```bash
   shred -vfz -n 5 wallet.dat
   ```
