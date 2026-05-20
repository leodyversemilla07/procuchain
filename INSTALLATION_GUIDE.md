# ProcuChain Installation Guide

> Blockchain-backed procurement management system for Philippine Government (RA 12009)
> Built with Laravel 13 + Inertia v3 + React 19 + MultiChain CE

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Prerequisites](#prerequisites)
3. [Local Development Setup](#local-development-setup-docker)
4. [Local Development Setup (Manual)](#local-development-setup-manual)
5. [Environment Configuration](#environment-configuration)
6. [Database Setup](#database-setup)
7. [MultiChain Blockchain Setup](#multichain-blockchain-setup)
8. [Roles & Permissions](#roles--permissions)
9. [Production Deployment (AWS)](#production-deployment-aws)
10. [Post-Deployment Verification](#post-deployment-verification)
11. [Troubleshooting](#troubleshooting)

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────┐
│                   AWS Cloud (us-east-1)                  │
│                                                          │
│  ┌──────────────────┐    ┌───────────────────────────┐  │
│  │  Elastic Beanstalk│    │   MultiChain Node Cluster │  │
│  │  (Laravel App)    │───▶│   ┌─────────┐             │  │
│  │  PHP 8.4 + Nginx │    │   │ Admin   │ us-east-1a  │  │
│  │  i-03a0bba9a3…   │    │   │ BAC-Sec │ us-east-1b  │  │
│  └────────┬─────────┘    │   │ BAC-Ch  │ us-east-1c  │  │
│           │              │   │ HOPE    │ us-east-1d  │  │
│           ▼              │   └─────────┘             │  │
│  ┌──────────────────┐    └───────────────────────────┘  │
│  │  RDS MySQL 8.4   │                                   │
│  │  db.t3.micro     │    ┌───────────────────────────┐  │
│  │  Encrypted (gp3) │    │   HAProxy Load Balancer   │  │
│  └──────────────────┘    │   Round-robin RPC proxy   │  │
│                          └───────────────────────────┘  │
└─────────────────────────────────────────────────────────┘

Tech Stack:
  Backend:  Laravel 13, PHP 8.3+, MySQL 8.4, Redis (queue)
  Frontend: React 19, Inertia v3, Vite 8, Tailwind v4, shadcn/ui
  Blockchain: MultiChain CE 2.3.3 (4-node cluster)
  Infra: AWS EB + RDS + EC2 (Terraform-managed)
  Auth: Laravel Fortify + 2FA (Google2FA) + spatie/permission
```

---

## Prerequisites

### Required Software

| Software | Version | Purpose |
|----------|---------|---------|
| PHP | 8.3+ | Backend runtime |
| Node.js | 22+ | Frontend build tooling |
| MySQL | 8.4+ | Application database |
| Redis | 7+ | Queue driver |
| MultiChain | 2.3.3 | Blockchain node |
| Docker | 24+ | Container runtime (for Docker setup) |
| Docker Compose | v2+ | Multi-container orchestration |
| Terraform | 1.15+ | Infrastructure provisioning (production) |
| AWS CLI | v2 | AWS operations (production) |

### PHP Extensions

```bash
# Required extensions (typically bundled with PHP 8.3+)
bcmath, json, curl, mbstring, xml, dom, pdo_mysql, fileinfo, openssl
```

### OS Support

- **Development**: macOS, Linux, Windows (WSL2)
- **Production**: Amazon Linux 2023 (AWS Elastic Beanstalk)

---

## Local Development Setup (Docker)

The fastest way to get running. Uses `docker-compose.yml` with all services.

### 1. Clone the Repository

```bash
git clone https://github.com/leodyversemilla07/procuchain.git
cd procuchain
```

### 2. Configure Environment

```bash
cp .env.example .env
```

Edit `.env` — key values for Docker setup:

```env
DB_CONNECTION=mysql
DB_HOST=mysql              # Docker service name
DB_PORT=3306
DB_DATABASE=procuchain
DB_USERNAME=procuchain
DB_PASSWORD=               # Empty in dev (MYSQL_ALLOW_EMPTY_PASSWORD=yes)

REDIS_HOST=redis           # If using Redis container (add to compose)
REDIS_PASSWORD=null
REDIS_PORT=6379

MULTICHAIN_RPC_HOST=multichain   # Docker service name
MULTICHAIN_RPC_PORT=7450
MULTICHAIN_RPC_USERNAME=multichainrpc
MULTICHAIN_RPC_PASSWORD=multichainrpc
MULTICHAIN_CHAIN_NAME=procuchain-dev
```

### 3. Start All Services

```bash
docker compose up -d
```

This starts:
- **multichain** — MultiChain node (P2P: 7449, RPC: 7450)
- **haproxy** — RPC load balancer (7451, stats: 8404)
- **mysql** — MySQL 8.4 (3306)
- **phpmyadmin** — DB admin UI (8080)

### 4. Install Backend Dependencies

```bash
docker compose exec multichain bash  # Or run locally if PHP is installed
composer install
```

### 5. Generate App Key

```bash
php artisan key:generate
```

### 6. Run Migrations & Seed

```bash
php artisan migrate --force
php artisan db:seed
```

> **Note**: `db:seed` requires MultiChain to be running (creates blockchain addresses for users).

### 7. Build Frontend Assets

```bash
npm install
npm run build
```

### 8. Start the Development Server

```bash
php artisan serve
```

App is live at **http://127.0.0.1:8000**

### Service URLs (Docker)

| Service | URL |
|---------|-----|
| Laravel App | http://localhost:8000 |
| phpMyAdmin | http://localhost:8080 |
| HAProxy Stats | http://localhost:8404/stats |
| MultiChain RPC | localhost:7450 |
| MultiChain P2P | localhost:7449 |

---

## Local Development Setup (Manual)

For developers who prefer running services natively.

### 1. Clone & Install

```bash
git clone https://github.com/leodyversemilla07/procuchain.git
cd procuchain
composer install
npm install
```

### 2. Configure Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306              # or 3307 if using custom port
DB_DATABASE=procuchain
DB_USERNAME=procuchain
DB_PASSWORD=your_password

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MULTICHAIN_RPC_HOST=127.0.0.1
MULTICHAIN_RPC_PORT=6486
MULTICHAIN_RPC_USERNAME=multichainrpc
MULTICHAIN_RPC_PASSWORD=your_rpc_password
MULTICHAIN_CHAIN_NAME=procuchain
```

### 3. Create MySQL Database

```bash
mysql -u root -p -e "CREATE DATABASE procuchain CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "CREATE USER 'procuchain'@'localhost' IDENTIFIED BY 'your_password';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON procuchain.* TO 'procuchain'@'localhost';"
mysql -u root -p -e "FLUSH PRIVILEGES;"
```

Or import the pre-generated schema:

```bash
mysql -u procuchain -p procuchain < database/schema.sql
```

### 4. Install MultiChain

```bash
cd /tmp
wget https://www.multichain.com/download/multichain-2.3.3.tar.gz
tar xzf multichain-2.3.3.tar.gz
sudo mv multichain-2.3.3/multichaind multichain-2.3.3/multichain-cli multichain-2.3.3/multichain-util /usr/local/bin/
rm -rf multichain-2.3.3 multichain-2.3.3.tar.gz
```

### 5. Create & Start the Blockchain

```bash
# Create blockchain with open permissions
multichain-util create procuchain \
  -default-network-port=6835 \
  -default-rpc-port=6834 \
  -anyone-can-connect=true \
  -anyone-can-send=true \
  -anyone-can-receive=true \
  -anyone-can-create=true \
  -anyone-can-issue=true \
  -anyone-can-mine=true \
  -anyone-can-activate=true

# Write RPC credentials
mkdir -p ~/.multichain/procuchain
cat > ~/.multichain/procuchain/multichain.conf <<EOF
rpcuser=multichainrpc
rpcpassword=your_rpc_password
rpcport=6834
port=6835
rpcallowip=0.0.0.0/0
EOF

# Start the daemon
multichaind procuchain -daemon
```

Verify it's running:

```bash
multichain-cli procuchain -rpcuser=multichainrpc -rpcpassword=your_rpc_password getinfo
```

### 6. Run Migrations & Seed

```bash
php artisan migrate
php artisan db:seed
```

### 7. Build & Serve

```bash
# Build frontend
npm run build

# Start dev server (with hot reload)
php artisan serve

# Or use Vite dev server alongside Laravel
concurrently "php artisan serve" "npm run dev"
```

---

## Environment Configuration

### Core Application Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_NAME` | Application name | `ProcuChain` |
| `APP_ENV` | Environment (`local`/`production`) | `local` |
| `APP_KEY` | Encryption key (auto-generated) | — |
| `APP_DEBUG` | Debug mode | `true` (local) / `false` (prod) |
| `APP_URL` | Application URL | `http://127.0.0.1:8000` |

### Database Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `DB_CONNECTION` | Driver | `mysql` |
| `DB_HOST` | MySQL host | `127.0.0.1` |
| `DB_PORT` | MySQL port | `3306` (prod) / `3307` (dev) |
| `DB_DATABASE` | Database name | `procuchain` |
| `DB_USERNAME` | MySQL user | `procuchain` |
| `DB_PASSWORD` | MySQL password | — |
| `DB_FOREIGN_KEYS` | Enable foreign keys | `true` |

### Session & Cache

| Variable | Description | Default |
|----------|-------------|---------|
| `SESSION_DRIVER` | Session storage | `database` |
| `SESSION_LIFETIME` | Session timeout (min) | `120` |
| `SESSION_ENCRYPT` | Encrypt session data | `false` (set `true` in prod) |
| `CACHE_DRIVER` | Cache backend | `database` |
| `QUEUE_CONNECTION` | Queue driver | `redis` |

### MultiChain Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `MULTICHAIN_CHAIN_NAME` | Blockchain name | `procuchain` |
| `MULTICHAIN_RPC_HOST` | RPC host | `127.0.0.1` |
| `MULTICHAIN_RPC_PORT` | RPC port | `6486` (dev) / `6834` (prod) |
| `MULTICHAIN_RPC_USERNAME` | RPC user | `multichainrpc` |
| `MULTICHAIN_RPC_PASSWORD` | RPC password | — |
| `MULTICHAIN_USE_SSL` | Use SSL for RPC | `false` |
| `MULTICHAIN_CONNECTION_TIMEOUT` | RPC timeout (sec) | `30` |
| `MULTICHAIN_MAX_RETRIES` | Retry attempts | `3` |

### MultiChain Node Registry (Production)

| Variable | Description |
|----------|-------------|
| `MULTICHAIN_NODE_ADMIN_IP` | Admin node public IP |
| `MULTICHAIN_NODE_ADMIN_PRIVATE_IP` | Admin node private IP |
| `MULTICHAIN_NODE_ADMIN_P2P_PORT` | Admin P2P port (6835) |
| `MULTICHAIN_NODE_ADMIN_RPC_PORT` | Admin RPC port (6834) |
| `MULTICHAIN_NODE_BAC_SECRETARIAT_IP` | BAC-Sec node IP |
| `MULTICHAIN_NODE_BAC_CHAIRMAN_IP` | BAC-Chair node IP |
| `MULTICHAIN_NODE_HOPE_IP` | HOPE node IP |

### Email (Resend)

| Variable | Description | Default |
|----------|-------------|---------|
| `MAIL_MAILER` | Mail driver | `resend` |
| `RESEND_API_KEY` | Resend API key | — |
| `MAIL_FROM_ADDRESS` | Sender email | `no-reply@example.com` |

### Web Push Notifications

| Variable | Description |
|----------|-------------|
| `VAPID_PUBLIC_KEY` | VAPID public key (pre-generated) |
| `VAPID_PRIVATE_KEY` | VAPID private key |
| `VAPID_SUBJECT` | VAPID subject mailto |

### Monitoring

| Variable | Description | Default |
|----------|-------------|---------|
| `SENTRY_LARAVEL_DSN` | Sentry DSN | `null` |
| `SENTRY_TRACES_SAMPLE_RATE` | Trace sampling | `0.1` |

---

## Database Setup

### Schema

The database has **20 tables** organized into:

- **Auth**: `users`, `password_reset_tokens`, `sessions`
- **Cache**: `cache`, `cache_locks`
- **Queue**: `jobs`, `failed_jobs`
- **RBAC**: `roles`, `permissions`, `role_has_permissions`, `model_has_roles`, `model_has_permissions`
- **Procurement**: `procurements`, `procurement_stages`, `documents`, `stage_document_configs`, `procurement_workflow_configs`
- **Audit**: `audit_logs`, `push_subscriptions`

### Run Migrations

```bash
# Fresh install
php artisan migrate

# Reset and re-run (destroys all data)
php artisan migrate:fresh

# With seed data
php artisan migrate:fresh --seed
```

### Import Pre-Built Schema

If you want to set up the database without running Laravel migrations:

```bash
mysql -u procuchain -p procuchain < database/schema.sql
```

> This file is generated from the Laravel migrations and is always in sync.

### Seed Data

The `DatabaseSeeder` runs two seeders in order:

1. **RoleAndPermissionSeeder** — Creates 4 roles and 30+ permissions
2. **UserSeeder** — Creates default users with blockchain addresses

```bash
php artisan db:seed

# Or run individual seeders
php artisan db:seed --class=RoleAndPermissionSeeder
php artisan db:seed --class=UserSeeder
```

---

## MultiChain Blockchain Setup

### How It Works

ProcuChain uses MultiChain's **data streams** to create an immutable audit trail of procurement actions. Each procurement event is published as a stream item with a JSON payload.

### Architecture

```
Laravel App
  ↓
Manager (app/Services/Manager.php)
  ├─ Automatic failover across 4 nodes
  ├─ Retry logic (3 retries, 2s backoff)
  └─ Health check & primary promotion
  ↓
Client (app/Libraries/MultiChain/Client.php)
  ↓ JSON-RPC
MultiChain Node Cluster
  ├─ Admin Node     (us-east-1a) — creates the chain
  ├─ BAC-Secretariat (us-east-1b) — peer
  ├─ BAC-Chairman   (us-east-1c) — peer
  └─ HOPE           (us-east-1d) — peer
```

### Connecting Peer Nodes (Production)

After the admin node creates the blockchain, peer nodes connect:

```bash
# On any peer node — connect to the admin node
multichaind procuchain@<ADMIN_PRIVATE_IP>:6835 -daemon
```

The first connection triggers a **subscribe + rescan** — the peer syncs all historical data. This may take several minutes.

### Failover Behavior

The `Manager` service automatically handles node failures:

1. **Primary node fails** → Tries next node in failover order: `admin → bac-sec → bac-chair → hope`
2. **RPC -703 error** (not subscribed) → Triggers failover (peer was purged)
3. **Background health check** → Promotes original primary back when it recovers
4. **Purged primary** → Stops retrying until `resetByResync()` is called after a re-sync

### Verifying Blockchain Connection

```bash
# Check node info
multichain-cli procuchain getinfo

# List all streams
multichain-cli procuchain liststreams

# Check peer connections
multichain-cli procuchain getpeerinfo
```

### From Laravel

```bash
php artisan tinker
>>> app(\App\Services\Manager::class)->getinfo();
>>> app(\App\Services\Manager::class)->liststreams();
```

---

## Roles & Permissions

ProcuChain implements **RBAC** via `spatie/laravel-permission` with 4 roles aligned to Philippine procurement law (RA 12009):

### Roles

| Role | Description | Dashboard |
|------|-------------|-----------|
| `admin` | System administrator — all permissions except procurement creation | Admin Dashboard |
| `bac_secretariat` | BAC Secretariat — manages procurement workflow end-to-end | BAC-Sec Dashboard |
| `bac_chairman` | BAC Chairman — approves/rejects procurements, signs resolutions | BAC-Chair Dashboard |
| `hope` | Head of Procuring Entity — final approvals, oversight | HOPE Dashboard |

### Permissions (30+)

- **Dashboard**: `view admin dashboard`, `view bac-secretariat dashboard`, `view bac-chairman dashboard`, `view hope dashboard`
- **Procurement**: `create`, `view`, `edit`, `delete`, `publish`, `manage procurements`
- **Documents**: `upload`, `view`, `download`, `delete documents`
- **Users**: `manage`, `create`, `edit`, `delete users`, `assign roles`
- **Stages**: `manage procurement initiation` through `manage completion` (13 stage permissions)
- **Approvals**: `approve`, `reject procurement`, `approve stage transition`
- **Blockchain**: `view blockchain transactions`, `publish to blockchain`
- **Notifications**: `manage`, `send notifications`
- **Settings**: `manage`, `view settings`

### Default Seeded Users

| Name | Email | Role | Password |
|------|-------|------|----------|
| LeoBriel Zilvrak | leobrielzilvrak@gmail.com | admin | *(set in UserSeeder)* |
| Bryle Maamo | brylemaamo@gmail.com | bac_secretariat | BryleMaamo00 |
| Adrian Gupit | adriangupit18@gmail.com | bac_chairman | Adrian18 |
| Leif Sage Semilla | leifsagesemilla@gmail.com | hope | LeifSage07 |

> **⚠️ Change these passwords immediately in production.**

---

## Production Deployment (AWS)

### Infrastructure Overview

All infrastructure is **Terraform-managed** in `terraform/`:

```
terraform/
├── main.tf                    # Provider config, S3 state backend
├── variables.tf               # All variables with defaults
├── iam.tf                     # SSM instance profile for EC2
├── ec2-multichain-nodes.tf    # 4 MultiChain EC2 nodes across AZs
├── ec2-app.tf                 # (EB-managed, reference only)
├── rds.tf                     # MySQL 8.4 on RDS (db.t3.micro)
├── security-groups.tf         # SG rules (HTTP, SSH, P2P, RPC)
└── templates/
    ├── multichain-node-user-data.sh        # Admin node bootstrap
    └── multichain-node-user-data-connect.sh # Peer node bootstrap
```

### Application Server (Elastic Beanstalk)

The Laravel app runs on **AWS Elastic Beanstalk** (PHP 8.4 on AL2023 + Nginx).

**EB Configuration:**
- Environment: `procuchain-prod`
- Platform: PHP 8.4 on Amazon Linux 2023
- URL: `http://procuchain-prod.eba-vujm352s.us-east-1.elasticbeanstalk.com`
- Document root: `/public`

### Step 1: Set Up Terraform

```bash
cd terraform

# Create a secrets.tfvars for sensitive values
cat > secrets.tfvars <<EOF
rds_master_password = "your_strong_password"
app_key = "base64:your_generated_key"
multichain_rpc_password = "your_rpc_password"
EOF

# Initialize (state stored in S3)
terraform init

# Plan & apply
terraform plan -var-file=secrets.tfvars
terraform apply -var-file=secrets.tfvars
```

This provisions:
- **RDS MySQL 8.4** — encrypted, gp3, automated backups
- **4 EC2 instances** — MultiChain nodes across 4 AZs
- **Security groups** — HTTP/HTTPS public, P2P/RPC internal-only
- **IAM roles** — SSM agent for remote management
- **S3 backend** — encrypted Terraform state

### Step 2: Deploy to Elastic Beanstalk

```bash
# Install EB CLI
pip install awsebcli

# Initialize EB (first time)
eb init -p php-8.4 procuchain-prod --region us-east-1

# Create environment (first time)
eb create procuchain-prod --region us-east-1 --instance-type t3.micro

# Deploy
eb deploy procuchain-prod
```

### Step 3: Set Environment Variables

```bash
eb setenv \
  APP_ENV=production \
  APP_DEBUG=false \
  APP_KEY="base64:your_key" \
  APP_URL=http://procuchain-prod.eba-vujm352s.us-east-1.elasticbeanstalk.com \
  DB_HOST=<RDS_ENDPOINT> \
  DB_PORT=3306 \
  DB_DATABASE=procuchain \
  DB_USERNAME=procuchain \
  DB_PASSWORD=<RDS_PASSWORD> \
  MULTICHAIN_RPC_HOST=<ADMIN_PRIVATE_IP> \
  MULTICHAIN_RPC_PORT=6834 \
  MULTICHAIN_RPC_USERNAME=multichainrpc \
  MULTICHAIN_RPC_PASSWORD=<RPC_PASSWORD> \
  MULTICHAIN_NODE_ADMIN_IP=<ADMIN_PUBLIC_IP> \
  MULTICHAIN_NODE_ADMIN_PRIVATE_IP=<ADMIN_PRIVATE_IP> \
  QUEUE_CONNECTION=redis \
  CACHE_DRIVER=database
```

### Step 4: EB Deployment Hooks

The `.platform/hooks/` directory automates deployment:

**Pre-deploy** (runs on `/var/app/staging/` before going live):

| Hook | Purpose |
|------|---------|
| `predeploy/01-generate-env.sh` | Creates `.env` from EB env vars |
| `predeploy/02-build-frontend.sh` | Installs Node 22, runs `npm install && npm run build` |

**Post-deploy** (runs on `/var/app/current/` after going live):

| Hook | Purpose |
|------|---------|
| `postdeploy/00-run-migrations.sh` | Runs `php artisan migrate --force` |
| `postdeploy/01-write-laravel-env.sh` | Injects EB env vars → `.env` (bridges EB ↔ Laravel) |
| `postdeploy/01-ensure-env.sh` | Safety net — ensures `.env` exists even after `eb setenv` |
| `postdeploy/02-start-queue-worker.sh` | Registers `laravel-queue-worker` as systemd service |
| `postdeploy/03-clear-cache.sh` | Clears dashboard caches |

### EB Extensions

| File | Purpose |
|------|---------|
| `.ebextensions/01-build-frontend.config` | Installs Node 22 via `dnf`, sets `NPM_USE_PRODUCTION=false` |
| `.ebextensions/01-laravel.config` | Sets document root to `/public` |

### Nginx Configuration

`.platform/nginx/conf.d/elasticbeanstalk/php.conf` — overrides EB's default nginx:

- Points `root` to `/var/app/current/public`
- Laravel pretty URL: `try_files $uri $uri/ /index.php?$query_string`
- FastCGI pass to `php-fpm`
- Denies access to hidden files (`.env`, `.git`)
- Serves `.mjs` files with correct MIME type

---

## Post-Deployment Verification

### 1. Check Application Health

```bash
curl -s http://procuchain-prod.eba-vujm352s.us-east-1.elasticbeanstalk.com | head -20
```

### 2. Verify Database Migration

```bash
# SSH via SSM or EB CLI
eb ssh

cd /var/app/current
php artisan migrate:status
```

### 3. Verify MultiChain Connection

```bash
php artisan tinker --execute="echo json_encode(app(\App\Services\Manager::class)->getinfo(), JSON_PRETTY_PRINT);"
```

Expected output includes `version`, `blocks`, `connections` (should be ≥ 3 for admin node).

### 4. Verify Queue Worker

```bash
systemctl status laravel-queue-worker
```

### 5. Check Node Cluster

```bash
# On admin node — verify all peers connected
multichain-cli procuchain getpeerinfo | jq '.[].subver'
```

### 6. Verify Roles & Permissions

```bash
php artisan tinker --execute="
  foreach (Spatie\Permission\Models\Role::all() as \$role) {
    echo \$role->name . ': ' . \$role->permissions->count() . ' permissions\n';
  }
"
```

---

## Troubleshooting

### MultiChain "RPC -703 not subscribed"

A peer node lost its subscription to a stream. **Fix:**

```bash
# On the affected node
multichain-cli procuchain subscribe <stream_name>

# Or resubscribe to all streams
for stream in $(multichain-cli procuchain liststreams -r | jq -r '.[].name'); do
  multichain-cli procuchain subscribe "$stream"
done
```

### MultiChain Node Won't Start

```bash
# Check if daemon is running
pgrep -f multichaind

# Check logs
cat ~/.multichain/procuchain/debug.log | tail -50

# Common issue: HOME is wrong (cloud-init runs as root)
export HOME=/root
multichaind procuchain -daemon
```

### "Connection refused" on MultiChain RPC

```bash
# Verify RPC credentials in config
cat ~/.multichain/procuchain/multichain.conf

# Test RPC directly
curl --user multichainrpc:your_password \
  -H "Content-Type: application/json" \
  -d '{"method":"getinfo","params":[],"id":1}' \
  http://127.0.0.1:6834
```

### Peer Not Syncing

If a node was purged and needs to re-sync:

1. Stop the node: `multichain-cli procuchain stop`
2. Write correct `multichain.conf` (credentials + `rpcallowip`)
3. Re-connect: `multichaind procuchain@<ADMIN_IP>:6835 -daemon`
4. Wait for "subscribing" + "rescanning" to complete

### EB Deployment Fails at Frontend Build

```bash
# Check Node.js version on EB instance
eb ssh
node -v   # Should be 22+

# If Node 22 is not installed:
sudo dnf install -y nodejs22 nodejs22-npm
sudo alternatives --set node /usr/bin/node-22
```

### Queue Worker Not Processing Jobs

```bash
# Check service status
eb ssh
sudo systemctl status laravel-queue-worker

# Restart
sudo systemctl restart laravel-queue-worker

# Check logs
tail -50 /var/app/current/storage/logs/queue-worker.log
```

### Session "Invalid Date" Errors

```bash
# Clear dashboard caches (the postdeploy hook does this automatically)
php artisan cache:clear
```

### Reset Production Database (⚠️ Destructive)

```bash
php artisan migrate:fresh --seed --force
```

> This drops all tables and re-creates them. **Use with extreme caution.**

---

## Quick Reference

### Artisan Commands

```bash
php artisan key:generate          # Generate APP_KEY
php artisan migrate               # Run pending migrations
php artisan migrate:fresh --seed  # Reset DB + seed
php artisan db:seed               # Run seeders
php artisan cache:clear           # Clear application cache
php artisan config:cache          # Cache config (production)
php artisan route:cache           # Cache routes (production)
php artisan queue:work database   # Start queue worker
php artisan tinker                # Interactive REPL
```

### Frontend Commands

```bash
npm install                # Install JS dependencies
npm run dev                # Vite dev server (hot reload)
npm run build              # Production build
npm run lint               # ESLint check
npm run format             # Prettier format
npm run types              # TypeScript type check
npm run wayfinder:generate # Generate route types
```

### Docker Commands

```bash
docker compose up -d                # Start all services
docker compose down                 # Stop all services
docker compose logs -f multichain   # Follow MultiChain logs
docker compose exec mysql bash      # Shell into MySQL container
```

### MultiChain Commands

```bash
multichain-util create <chain>      # Create new blockchain
multichaind <chain> -daemon         # Start daemon
multichain-cli <chain> getinfo      # Node status
multichain-cli <chain> getpeerinfo  # Connected peers
multichain-cli <chain> liststreams  # List data streams
multichain-cli <chain> stop         # Stop daemon
```

---

## File Structure Reference

```
procuchain/
├── app/
│   ├── Libraries/MultiChain/
│   │   ├── Client.php              # JSON-RPC client (magic __call)
│   │   └── README.md               # API documentation
│   └── Services/
│       └── Manager.php             # Laravel wrapper + failover
├── config/
│   └── multichain.php              # MultiChain config
├── database/
│   ├── migrations/                 # 50 migration files
│   ├── seeders/
│   │   ├── DatabaseSeeder.php
│   │   ├── RoleAndPermissionSeeder.php
│   │   ├── UserSeeder.php
│   │   └── ...
│   └── schema.sql                  # Pre-built MySQL schema
├── docker-compose.yml              # Dev environment
├── Dockerfile.multichain           # MultiChain container
├── docker-entrypoint.sh            # MultiChain bootstrap script
├── haproxy/
│   ├── Dockerfile
│   └── haproxy.cfg                 # Round-robin RPC proxy
├── terraform/                      # AWS infrastructure
│   ├── main.tf
│   ├── variables.tf
│   ├── outputs.tf
│   ├── rds.tf
│   ├── ec2-multichain-nodes.tf
│   ├── iam.tf
│   ├── security-groups.tf
│   └── templates/                  # Node bootstrap scripts
├── .ebextensions/                  # EB build + PHP config
├── .platform/
│   ├── hooks/
│   │   ├── predeploy/              # Frontend build + .env generation
│   │   └── postdeploy/             # Migrations + queue worker + cache clear
│   └── nginx/                      # Custom nginx config for AL2023
├── .env.example                    # Environment variable template
├── composer.json                   # PHP dependencies
└── package.json                    # Node.js dependencies
```
