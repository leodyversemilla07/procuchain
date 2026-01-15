# ProcuChain MultiChain GCP Deployment Guide

This guide walks you through deploying the ProcuChain MultiChain blockchain network on Google Cloud Platform using Terraform.

## Architecture Overview

The deployment creates a 5-node MultiChain network:

| Node | Role | Machine Type | Zone | Purpose |
|------|------|--------------|------|---------|
| `procuchain-admin` | Admin/Seed | n2-standard-2 | asia-southeast1-b | Creates and administers the blockchain |
| `procuchain-app-primary` | App Node | n2-standard-2 | asia-southeast1-b | Handles web app RPC requests |
| `procuchain-app-secondary` | App Node | n2-standard-2 | asia-southeast1-b | Handles queue worker RPC requests |
| `procuchain-witness` | Witness | e2-medium | asia-southeast1-c | Independent validator |
| `procuchain-backup` | Backup/DR | e2-medium | asia-northeast1-b | Disaster recovery standby |

### Network Diagram

```
                                    ┌─────────────────────────────────────────────────────┐
                                    │              GCP VPC (10.0.1.0/24)                  │
                                    │                                                     │
    ┌──────────────┐                │  ┌──────────────┐        ┌──────────────┐          │
    │   Laravel    │                │  │    Admin     │◄──────►│  App Primary │          │
    │ Application  │────────────────┼──│    Node      │        │    Node      │          │
    └──────────────┘     Load       │  │  (Seed)      │◄──┐    └──────────────┘          │
                       Balancer     │  └──────────────┘   │                              │
                      (Internal)    │         ▲           │    ┌──────────────┐          │
                                    │         │           └───►│ App Secondary│          │
                                    │         ▼                │    Node      │          │
                                    │  ┌──────────────┐        └──────────────┘          │
                                    │  │   Witness    │                                  │
                                    │  │    Node      │        (asia-southeast1-b)       │
                                    │  └──────────────┘                                  │
                                    │  (asia-southeast1-c)                               │
                                    └─────────────────────────────────────────────────────┘
                                                      │
                                                      │ VPC Peering
                                                      ▼
                                    ┌─────────────────────────────────────────────────────┐
                                    │           Backup Region (10.0.2.0/24)               │
                                    │                                                     │
                                    │              ┌──────────────┐                       │
                                    │              │   Backup     │                       │
                                    │              │    Node      │                       │
                                    │              └──────────────┘                       │
                                    │              (asia-northeast1-b)                    │
                                    └─────────────────────────────────────────────────────┘
```

## Prerequisites

### 1. GCP Project Setup

```bash
# Set your project ID
export PROJECT_ID="your-project-id"

# Enable required APIs
gcloud services enable compute.googleapis.com \
    secretmanager.googleapis.com \
    cloudresourcemanager.googleapis.com \
    monitoring.googleapis.com \
    logging.googleapis.com \
    iap.googleapis.com \
    --project=$PROJECT_ID
```

### 2. Install Terraform

```bash
# macOS
brew install terraform

# Ubuntu/Debian
wget -O- https://apt.releases.hashicorp.com/gpg | sudo gpg --dearmor -o /usr/share/keyrings/hashicorp-archive-keyring.gpg
echo "deb [signed-by=/usr/share/keyrings/hashicorp-archive-keyring.gpg] https://apt.releases.hashicorp.com $(lsb_release -cs) main" | sudo tee /etc/apt/sources.list.d/hashicorp.list
sudo apt update && sudo apt install terraform

# Windows (via Chocolatey)
choco install terraform
```

### 3. Authenticate with GCP

```bash
# Login to GCP
gcloud auth login
gcloud auth application-default login

# Set project
gcloud config set project $PROJECT_ID
```

## Deployment Steps

### Step 1: Configure Variables

```bash
cd infrastructure/gcp

# Copy example variables file
cp terraform.tfvars.example terraform.tfvars

# Edit with your values
# IMPORTANT: Set project_id and access control lists
```

Edit `terraform.tfvars`:

```hcl
project_id = "your-gcp-project-id"

# CRITICAL: Set allowed SSH ranges for security
allowed_ssh_ranges = [
  "YOUR_OFFICE_IP/32",
]

# Set app server IPs that need RPC access
app_server_ips = [
  "YOUR_APP_SERVER_IP/32",
]
```

### Step 2: Initialize and Plan

```bash
# Initialize Terraform
terraform init

# Preview changes
terraform plan -out=tfplan
```

### Step 3: Deploy Infrastructure

```bash
# Apply the plan
terraform apply tfplan

# Save outputs for later
terraform output -json > outputs.json
```

### Step 4: Grant Node Permissions

After deployment, you need to grant permissions to peer nodes from the admin node.

```bash
# SSH to admin node via IAP
gcloud compute ssh procuchain-admin \
    --zone=asia-southeast1-b \
    --tunnel-through-iap

# On admin node, check logs for peer connection requests
sudo journalctl -u multichaind | grep "grant"

# Grant permissions to each peer node
# You'll see addresses like: 1ABC123...xyz
sudo -u multichain /usr/local/multichain/multichain-cli procuchain grant <PEER_ADDRESS> connect,send,receive,mine
```

Repeat for each peer node (app-primary, app-secondary, witness, backup).

### Step 5: Verify Node Status

```bash
# Check admin node
gcloud compute ssh procuchain-admin --zone=asia-southeast1-b --tunnel-through-iap
sudo -u multichain /usr/local/multichain/multichain-cli procuchain getinfo
sudo -u multichain /usr/local/multichain/multichain-cli procuchain getpeerinfo

# Check app nodes
gcloud compute ssh procuchain-app-primary --zone=asia-southeast1-b --tunnel-through-iap
sudo -u multichain /home/multichain/check-status.sh
```

### Step 6: Initialize Blockchain Streams

Once all nodes are connected, initialize the ProcuChain streams:

```bash
# On admin node
sudo -u multichain /usr/local/multichain/multichain-cli procuchain create stream procurement.metadata true
sudo -u multichain /usr/local/multichain/multichain-cli procuchain create stream procurement.documents true
sudo -u multichain /usr/local/multichain/multichain-cli procuchain create stream procurement.status true
sudo -u multichain /usr/local/multichain/multichain-cli procuchain create stream procurement.events true
sudo -u multichain /usr/local/multichain/multichain-cli procuchain create stream file.data true
sudo -u multichain /usr/local/multichain/multichain-cli procuchain create stream file.metadata true
sudo -u multichain /usr/local/multichain/multichain-cli procuchain create stream file.chunks true

# Subscribe all nodes to streams
sudo -u multichain /usr/local/multichain/multichain-cli procuchain subscribe procurement.metadata
sudo -u multichain /usr/local/multichain/multichain-cli procuchain subscribe procurement.documents
# ... repeat for all streams
```

Or use the Laravel artisan command (from your app server):

```bash
php artisan multichain:setup
```

### Step 7: Configure Laravel Application

Get the configuration values from Terraform outputs:

```bash
# Get load balancer IP
terraform output load_balancer_ip

# Get RPC password from Secret Manager
gcloud secrets versions access latest --secret="procuchain-rpc-password"
```

Update your Laravel `.env` file:

```env
MULTICHAIN_CHAIN_NAME=procuchain
MULTICHAIN_RPC_HOST=<LOAD_BALANCER_IP>
MULTICHAIN_RPC_PORT=6486
MULTICHAIN_RPC_USERNAME=multichainrpc
MULTICHAIN_RPC_PASSWORD=<SECRET_VALUE>
MULTICHAIN_USE_SSL=false
```

## Operations Guide

### SSH Access

All nodes use Identity-Aware Proxy (IAP) for secure SSH without public IPs:

```bash
# Admin node
gcloud compute ssh procuchain-admin --zone=asia-southeast1-b --tunnel-through-iap

# App nodes
gcloud compute ssh procuchain-app-primary --zone=asia-southeast1-b --tunnel-through-iap
gcloud compute ssh procuchain-app-secondary --zone=asia-southeast1-b --tunnel-through-iap

# Witness node
gcloud compute ssh procuchain-witness --zone=asia-southeast1-c --tunnel-through-iap

# Backup node
gcloud compute ssh procuchain-backup --zone=asia-northeast1-b --tunnel-through-iap
```

### Node Management

```bash
# Check node status
sudo systemctl status multichaind

# View logs
sudo journalctl -u multichaind -f

# Restart node
sudo systemctl restart multichaind

# Stop node (for maintenance)
sudo systemctl stop multichaind
```

### MultiChain CLI Commands

```bash
# Switch to multichain user
sudo -u multichain bash

# Basic info
multichain-cli procuchain getinfo
multichain-cli procuchain getpeerinfo
multichain-cli procuchain getblockchaininfo

# Check wallet
multichain-cli procuchain getaddresses
multichain-cli procuchain listpermissions

# Stream operations
multichain-cli procuchain liststreams
multichain-cli procuchain liststreamitems procurement.metadata
```

### Backup and Recovery

#### Manual Backup

```bash
# On admin node
sudo systemctl stop multichaind
sudo cp /data/multichain/data/procuchain/wallet.dat /tmp/wallet-backup-$(date +%Y%m%d).dat
sudo systemctl start multichaind

# Upload to GCS
gsutil cp /tmp/wallet-backup-*.dat gs://procuchain-backups-$PROJECT_ID/manual/
```

#### Restore from Backup

```bash
# Stop daemon
sudo systemctl stop multichaind

# Restore wallet
sudo cp /path/to/wallet-backup.dat /data/multichain/data/procuchain/wallet.dat
sudo chown multichain:multichain /data/multichain/data/procuchain/wallet.dat

# Start daemon
sudo systemctl start multichaind
```

### Monitoring

View monitoring dashboards in GCP Console:
- **Cloud Monitoring** → Uptime Checks
- **Cloud Logging** → Logs Explorer → Filter by `resource.type="gce_instance"`

Alert policies are automatically created for node downtime.

## Security Considerations

1. **No Public IPs**: All nodes use private IPs only. Access via IAP.
2. **Firewall Rules**: Only necessary ports open between nodes.
3. **Secret Manager**: RPC passwords stored securely.
4. **Service Account**: Least-privilege permissions.
5. **Shielded VMs**: Secure boot enabled.

### Rotate RPC Password

```bash
# Generate new password
NEW_PASSWORD=$(openssl rand -base64 32 | tr -d '/+=' | head -c 32)

# Update in Secret Manager
echo -n "$NEW_PASSWORD" | gcloud secrets versions add procuchain-rpc-password --data-file=-

# SSH to each node and update multichain.conf
# Then restart multichaind service
```

## Cost Estimation

| Resource | Quantity | Monthly Cost (est.) |
|----------|----------|---------------------|
| n2-standard-2 (admin + 2 app) | 3 | ~$210 |
| e2-medium (witness + backup) | 2 | ~$50 |
| SSD Persistent Disks (230GB) | 5 | ~$40 |
| Cloud NAT | 1 | ~$30 |
| Load Balancer | 1 | ~$20 |
| **Total** | | **~$350/month** |

## Troubleshooting

### Node Won't Start

```bash
# Check logs
sudo journalctl -u multichaind -n 100

# Check disk space
df -h /data/multichain

# Verify permissions
ls -la /data/multichain/data/procuchain/
```

### Peer Connection Failed

```bash
# On admin node, check if permission granted
sudo -u multichain multichain-cli procuchain listpermissions connect

# Check network connectivity
nc -zv <PEER_IP> 6487
```

### RPC Connection Failed

```bash
# Test RPC locally
curl -u multichainrpc:PASSWORD \
    -d '{"method":"getinfo","params":[],"id":1}' \
    http://localhost:6486

# Check firewall rules
gcloud compute firewall-rules list --filter="network:procuchain-vpc"
```

## Cleanup

To destroy all resources:

```bash
# WARNING: This deletes all data including blockchain!
terraform destroy
```

## Support

- [MultiChain Documentation](https://www.multichain.com/developers/)
- [GCP Compute Engine Docs](https://cloud.google.com/compute/docs)
- [Terraform GCP Provider](https://registry.terraform.io/providers/hashicorp/google/latest/docs)
