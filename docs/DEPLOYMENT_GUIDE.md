# ProcuChain Deployment Guide - Complete Step-by-Step Instructions

**Version:** 1.0  
**Last Updated:** October 18, 2025  
**Target Platform:** Heroku  
**Estimated Time:** 4-6 hours (excluding MultiChain setup)

---

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Phase 1: Infrastructure Preparation](#phase-1-infrastructure-preparation)
3. [Phase 2: Service Configuration](#phase-2-service-configuration)
4. [Phase 3: Heroku Setup](#phase-3-heroku-setup)
5. [Phase 4: Application Deployment](#phase-4-application-deployment)
6. [Phase 5: Initial Configuration](#phase-5-initial-configuration)
7. [Phase 6: Testing & Validation](#phase-6-testing--validation)
8. [Phase 7: Production Hardening](#phase-7-production-hardening)
9. [Troubleshooting](#troubleshooting)
10. [Maintenance & Operations](#maintenance--operations)

---

## Prerequisites

### Required Accounts
- [ ] GitHub account with repository access
- [ ] Heroku account (with payment method for paid dynos)
- [ ] DigitalOcean account (for Spaces storage)
- [ ] Email service account (Resend, Mailgun, or similar)
- [ ] Server/VM for MultiChain node (DigitalOcean, AWS, Azure, or on-premise)

### Required Software (Local Machine)
- [ ] Git
- [ ] Heroku CLI (`https://devcenter.heroku.com/articles/heroku-cli`)
- [ ] SSH client
- [ ] Text editor

### Technical Knowledge Required
- Basic command line usage
- SSH/terminal access
- Understanding of environment variables
- Basic networking concepts (ports, firewalls, DNS)

### Estimated Costs
- Heroku Eco Dynos: $10/month (web + worker)
- JawsDB MySQL: $10/month
- DigitalOcean Spaces: $5/month
- MultiChain Node (VM): $20-50/month
- **Total: ~$45-75/month**

---

## Phase 1: Infrastructure Preparation

### Step 1.1: MultiChain Node Setup

MultiChain is the **most critical dependency**. Complete this first.

#### Option A: DigitalOcean Droplet (Recommended)

**1. Create Droplet**
```bash
# Via DigitalOcean Web UI:
# - Image: Ubuntu 22.04 LTS
# - Plan: Basic - $20/month (2GB RAM, 2 vCPUs, 50GB SSD)
# - Datacenter: Choose closest to your users (e.g., Singapore)
# - Authentication: SSH Key (recommended)
# - Hostname: procuchain-blockchain
```

**2. Connect to Droplet**
```bash
ssh root@your_droplet_ip
```

**3. Install MultiChain**
```bash
# Update system
apt update && apt upgrade -y

# Install required packages
apt install -y wget

# Download MultiChain
cd /tmp
wget https://www.multichain.com/download/multichain-2.3.3.tar.gz

# Extract and install
tar -xvzf multichain-2.3.3.tar.gz
cd multichain-2.3.3
mv multichaind multichain-cli multichain-util /usr/local/bin/

# Verify installation
multichaind --version
```

**4. Create Blockchain**
```bash
# Create the chain
multichain-util create procuchain

# This creates configuration in: ~/.multichain/procuchain/
```

**5. Configure MultiChain**
```bash
# Edit params.dat for production settings
nano ~/.multichain/procuchain/params.dat

# Key settings (adjust if needed):
# default-network-port = 6271
# default-rpc-port = 4786
# maximum-block-size = 8388608
# target-block-time = 15
```

**6. Configure RPC Access**
```bash
# Edit multichain.conf
nano ~/.multichain/procuchain/multichain.conf

# Add these lines (use strong password):
rpcuser=multichainrpc
rpcpassword=CHANGE_THIS_TO_STRONG_PASSWORD_123!@#
rpcallowip=0.0.0.0/0
rpcport=4786
```

**7. Start MultiChain**
```bash
# Start as daemon
multichaind procuchain -daemon

# Verify it's running
multichain-cli procuchain getinfo
```

**8. Configure Firewall**
```bash
# Install UFW firewall
apt install -y ufw

# Allow SSH
ufw allow 22/tcp

# Allow MultiChain P2P port
ufw allow 6271/tcp

# Allow MultiChain RPC port (limit to specific IPs in production)
ufw allow 4786/tcp

# Enable firewall
ufw --force enable

# Check status
ufw status
```

**9. Set Up SSL (Optional but Recommended)**
```bash
# Install nginx as reverse proxy
apt install -y nginx certbot python3-certbot-nginx

# Configure nginx for MultiChain RPC
nano /etc/nginx/sites-available/multichain

# Add configuration:
server {
    listen 443 ssl;
    server_name blockchain.yourdomain.com;

    ssl_certificate /etc/letsencrypt/live/blockchain.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/blockchain.yourdomain.com/privkey.pem;

    location / {
        proxy_pass http://127.0.0.1:4786;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}

# Enable site and get SSL certificate
ln -s /etc/nginx/sites-available/multichain /etc/nginx/sites-enabled/
certbot --nginx -d blockchain.yourdomain.com
systemctl restart nginx
```

**10. Create Systemd Service (Auto-start on Boot)**
```bash
# Create service file
nano /etc/systemd/system/multichain.service

# Add content:
[Unit]
Description=MultiChain Daemon for ProcuChain
After=network.target

[Service]
Type=forking
User=root
ExecStart=/usr/local/bin/multichaind procuchain -daemon
ExecStop=/usr/local/bin/multichain-cli procuchain stop
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target

# Enable and start service
systemctl daemon-reload
systemctl enable multichain
systemctl start multichain
systemctl status multichain
```

**11. Test RPC Connection**
```bash
# From the server
multichain-cli procuchain getinfo

# From another machine (test RPC access)
curl --user multichainrpc:YOUR_PASSWORD \
  --data-binary '{"jsonrpc":"1.0","id":"test","method":"getinfo","params":[]}' \
  -H 'content-type: text/plain;' \
  http://YOUR_DROPLET_IP:4786
```

**Important Notes:**
- Save the RPC password securely
- Note your droplet IP address
- Keep the server updated: `apt update && apt upgrade`
- Set up regular backups of `~/.multichain/procuchain/`

---

### Step 1.2: Storage Setup (DigitalOcean Spaces)

**1. Create Spaces Bucket**
```bash
# Via DigitalOcean Web UI:
# 1. Go to Spaces Object Storage
# 2. Click "Create a Space"
# 3. Settings:
#    - Datacenter: Singapore (sgp1) or nearest
#    - Enable CDN: No (for security)
#    - Space name: procuchain-documents
#    - Project: ProcuChain
#    - File Listing: Restricted
```

**2. Generate Access Keys**
```bash
# Via DigitalOcean Web UI:
# 1. API → Spaces Keys
# 2. Generate New Key
# 3. Name: procuchain-app-access
# 4. Save both:
#    - Access Key ID (e.g., DO00ABC123...)
#    - Secret Access Key (e.g., xyz789...)
```

**3. Test Access (Optional)**
```bash
# Install s3cmd for testing
apt install -y s3cmd

# Configure s3cmd
s3cmd --configure

# Test upload
echo "test" > test.txt
s3cmd put test.txt s3://procuchain-documents/test.txt \
  --host=sgp1.digitaloceanspaces.com \
  --host-bucket='%(bucket)s.sgp1.digitaloceanspaces.com'
```

**Record These Values:**
- Access Key ID: `___________________________`
- Secret Access Key: `___________________________`
- Bucket Name: `procuchain-documents`
- Region: `sgp1`
- Endpoint: `https://sgp1.digitaloceanspaces.com`

---

### Step 1.3: Email Service Setup (Resend)

**1. Create Resend Account**
```bash
# Go to: https://resend.com/
# Sign up for free account (3,000 emails/month)
```

**2. Verify Domain**
```bash
# Via Resend Web UI:
# 1. Go to Domains
# 2. Click "Add Domain"
# 3. Add your domain: yourdomain.com
# 4. Follow DNS verification steps (add TXT and CNAME records)
# 5. Wait for domain verification to complete
```

**3. Create API Key**
```bash
# Via Resend Web UI:
# 1. Go to API Keys
# 2. Click "Create API Key"
# 3. Name: procuchain-production
# 4. Copy the API key (starts with re_...)
```

**Alternative: Using Gmail SMTP (NOT recommended for production)**
```bash
# If using Gmail:
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_app_specific_password
MAIL_ENCRYPTION=tls

# Note: Generate App Password at: https://myaccount.google.com/apppasswords
```

**Record These Values:**
- Resend API Key: `___________________________`
- From Email: `noreply@yourdomain.com`
- From Name: `ProcuChain - BAC Office`

---

## Phase 2: Service Configuration

### Step 2.1: Install Heroku CLI

**Windows:**
```powershell
# Download installer from:
https://devcenter.heroku.com/articles/heroku-cli

# Or via chocolatey:
choco install heroku-cli
```

**macOS:**
```bash
brew tap heroku/brew && brew install heroku
```

**Linux:**
```bash
curl https://cli-assets.heroku.com/install.sh | sh
```

**Verify Installation:**
```bash
heroku --version
```

---

### Step 2.2: Heroku Login

```bash
# Login to Heroku
heroku login

# This will open browser for authentication
# Press any key after logging in via browser

# Verify login
heroku auth:whoami
```

---

## Phase 3: Heroku Setup

### Step 3.1: Create Heroku Application

```bash
# Navigate to project directory
cd /path/to/procuchain

# Create Heroku app (choose unique name)
heroku create procuchain-bac

# Or with specific region
heroku create procuchain-bac --region us

# Verify creation
heroku apps:info -a procuchain-bac

# Add git remote (if not added automatically)
git remote add heroku https://git.heroku.com/procuchain-bac.git
```

**Record Your App Name:**
- Heroku App Name: `___________________________`
- App URL: `https://procuchain-bac.herokuapp.com`

---

### Step 3.2: Add Heroku Add-ons

**1. MySQL Database (JawsDB)**
```bash
# Add JawsDB MySQL
heroku addons:create jawsdb:kitefin -a procuchain-bac

# Kitefin plan: $10/month, 1GB storage, 15 connections

# Get connection details
heroku config:get JAWSDB_URL -a procuchain-bac

# This will show: mysql://username:password@host:port/database
```

**Alternative: ClearDB**
```bash
# If JawsDB not available in your region
heroku addons:create cleardb:ignite -a procuchain-bac

# Get connection details
heroku config:get CLEARDB_DATABASE_URL -a procuchain-bac
```

**2. Logging (Papertrail) - Optional but Recommended**
```bash
# Add Papertrail for centralized logging
heroku addons:create papertrail:choklad -a procuchain-bac

# Free plan: 50MB/month of logs

# View logs dashboard
heroku addons:open papertrail -a procuchain-bac
```

**3. Redis (Optional for Better Performance)**
```bash
# Add Redis for caching/queue (optional)
heroku addons:create heroku-redis:mini -a procuchain-bac

# Mini plan: $3/month, 25MB RAM

# Get Redis URL
heroku config:get REDIS_URL -a procuchain-bac
```

---

### Step 3.3: Configure Environment Variables

**1. Set Application Variables**
```bash
# Application basics
heroku config:set APP_NAME="ProcuChain" -a procuchain-bac
heroku config:set APP_ENV=production -a procuchain-bac
heroku config:set APP_DEBUG=false -a procuchain-bac
heroku config:set APP_URL=https://procuchain-bac.herokuapp.com -a procuchain-bac

# Generate APP_KEY
php artisan key:generate --show
# Copy the output (base64:...) and set it:
heroku config:set APP_KEY="base64:YOUR_GENERATED_KEY_HERE" -a procuchain-bac

# Locale settings
heroku config:set APP_LOCALE=en -a procuchain-bac
heroku config:set APP_FALLBACK_LOCALE=en -a procuchain-bac
heroku config:set APP_FAKER_LOCALE=en_US -a procuchain-bac

# Logging
heroku config:set LOG_CHANNEL=stack -a procuchain-bac
heroku config:set LOG_LEVEL=error -a procuchain-bac
```

**2. Database Configuration**
```bash
# Heroku automatically sets DATABASE_URL or JAWSDB_URL
# Laravel will use it automatically via config/database.php

# Optional: Set explicit database connection
heroku config:set DB_CONNECTION=mysql -a procuchain-bac
```

**3. Session, Cache, Queue Configuration**
```bash
# Using database for all (as per current setup)
heroku config:set SESSION_DRIVER=database -a procuchain-bac
heroku config:set SESSION_LIFETIME=120 -a procuchain-bac
heroku config:set CACHE_DRIVER=database -a procuchain-bac
heroku config:set QUEUE_CONNECTION=database -a procuchain-bac

# If you added Redis:
# heroku config:set SESSION_DRIVER=redis -a procuchain-bac
# heroku config:set CACHE_DRIVER=redis -a procuchain-bac
# heroku config:set QUEUE_CONNECTION=redis -a procuchain-bac
```

**4. Storage Configuration (DigitalOcean Spaces)**
```bash
heroku config:set FILESYSTEM_DISK=s3 -a procuchain-bac
heroku config:set AWS_ACCESS_KEY_ID="YOUR_DO_ACCESS_KEY" -a procuchain-bac
heroku config:set AWS_SECRET_ACCESS_KEY="YOUR_DO_SECRET_KEY" -a procuchain-bac
heroku config:set AWS_DEFAULT_REGION=sgp1 -a procuchain-bac
heroku config:set AWS_BUCKET=procuchain-documents -a procuchain-bac
heroku config:set AWS_ENDPOINT=https://sgp1.digitaloceanspaces.com -a procuchain-bac
heroku config:set AWS_USE_PATH_STYLE_ENDPOINT=false -a procuchain-bac
```

**5. Email Configuration (Resend)**
```bash
heroku config:set MAIL_MAILER=resend -a procuchain-bac
heroku config:set RESEND_API_KEY="YOUR_RESEND_API_KEY" -a procuchain-bac
heroku config:set MAIL_FROM_ADDRESS="noreply@yourdomain.com" -a procuchain-bac
heroku config:set MAIL_FROM_NAME="ProcuChain - BAC Office" -a procuchain-bac
heroku config:set MAIL_SUPPORT_EMAIL="support@yourdomain.com" -a procuchain-bac
```

**6. Push Notification Configuration**
```bash
# Use the VAPID keys from .env.example
heroku config:set VAPID_PUBLIC_KEY="VAPID_PUBLIC_KEY_PLACEHOLDER" -a procuchain-bac
heroku config:set VAPID_PRIVATE_KEY="VAPID_PRIVATE_KEY_PLACEHOLDER" -a procuchain-bac
heroku config:set VAPID_SUBJECT="mailto:admin@yourdomain.com" -a procuchain-bac
```

**7. MultiChain Configuration**
```bash
# Use values from your MultiChain setup
heroku config:set MULTICHAIN_HOST="YOUR_DROPLET_IP_OR_DOMAIN" -a procuchain-bac
heroku config:set MULTICHAIN_PORT=4786 -a procuchain-bac
heroku config:set MULTICHAIN_USERNAME=multichainrpc -a procuchain-bac
heroku config:set MULTICHAIN_PASSWORD="YOUR_MULTICHAIN_RPC_PASSWORD" -a procuchain-bac
heroku config:set MULTICHAIN_CHAIN_NAME=procuchain -a procuchain-bac

# Network settings
heroku config:set MULTICHAIN_P2P_PORT=6271 -a procuchain-bac
heroku config:set MULTICHAIN_WEB_PORT=7448 -a procuchain-bac
heroku config:set MULTICHAIN_NODE_ADDRESS="YOUR_DROPLET_IP:6271" -a procuchain-bac

# SSL settings (if configured)
heroku config:set MULTICHAIN_USE_SSL=false -a procuchain-bac
heroku config:set MULTICHAIN_VERIFY_SSL=false -a procuchain-bac

# Connection settings
heroku config:set MULTICHAIN_CONNECTION_TIMEOUT=30 -a procuchain-bac
heroku config:set MULTICHAIN_MAX_RETRIES=3 -a procuchain-bac

# Blockchain addresses (will be generated during setup, set placeholders for now)
heroku config:set MULTICHAIN_ADMIN_ADDRESS=default_admin -a procuchain-bac
heroku config:set MULTICHAIN_BAC_SECRETARIAT_ADDRESS=default_bac_secretariat -a procuchain-bac
heroku config:set MULTICHAIN_BAC_CHAIRMAN_ADDRESS=default_bac_chairman -a procuchain-bac
heroku config:set MULTICHAIN_HOPE_ADDRESS=default_hope -a procuchain-bac
```

**8. Verify All Config**
```bash
# List all config variables
heroku config -a procuchain-bac

# Should see 40+ environment variables
```

---

### Step 3.4: Configure Buildpacks

```bash
# Add Node.js buildpack (for frontend assets)
heroku buildpacks:add --index 1 heroku/nodejs -a procuchain-bac

# Add PHP buildpack
heroku buildpacks:add --index 2 heroku/php -a procuchain-bac

# Verify buildpacks
heroku buildpacks -a procuchain-bac

# Should show:
# 1. heroku/nodejs
# 2. heroku/php
```

---

## Phase 4: Application Deployment

### Step 4.1: Prepare Local Repository

```bash
# Navigate to project directory
cd /path/to/procuchain

# Ensure you're on main branch
git checkout main

# Pull latest changes
git pull origin main

# Verify git remote
git remote -v

# Should show heroku remote:
# heroku  https://git.heroku.com/procuchain-bac.git (fetch)
# heroku  https://git.heroku.com/procuchain-bac.git (push)
```

---

### Step 4.2: Create Procfile (Already Exists)

The project already has a `Procfile` with correct configuration:
```
web: php artisan inertia:start-ssr & heroku-php-apache2 public/
worker: php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

**Verify it exists:**
```bash
cat Procfile
```

---

### Step 4.3: Deploy to Heroku

```bash
# Deploy application
git push heroku main

# This will:
# 1. Upload code to Heroku
# 2. Detect buildpacks (Node.js + PHP)
# 3. Install npm dependencies
# 4. Run npm run build:ssr (frontend compilation)
# 5. Install composer dependencies
# 6. Start web process

# Watch deployment logs
heroku logs --tail -a procuchain-bac

# Deployment should complete in 5-10 minutes
```

**Expected Output:**
```
remote: -----> Building on the Heroku-22 stack
remote: -----> Using buildpack: heroku/nodejs
remote: -----> Node.js app detected
remote: -----> Installing dependencies
remote: -----> Building frontend assets
remote: -----> Using buildpack: heroku/php
remote: -----> PHP app detected
remote: -----> Installing dependencies
remote: -----> Discovering process types
remote:        Procfile declares types -> web, worker
remote: -----> Compressing...
remote: -----> Launching...
remote: -----> Deployed to Heroku
```

---

### Step 4.4: Scale Dynos

```bash
# Scale web dyno
heroku ps:scale web=1 -a procuchain-bac

# Scale worker dyno (for queue processing)
heroku ps:scale worker=1 -a procuchain-bac

# Verify dyno status
heroku ps -a procuchain-bac

# Should show:
# === web (Eco): php artisan inertia:start-ssr & heroku-php-apache2 public/
# web.1: up
# 
# === worker (Eco): php artisan queue:work --sleep=3 --tries=3 --max-time=3600
# worker.1: up
```

---

## Phase 5: Initial Configuration

### Step 5.1: Run Database Migrations

```bash
# Run migrations
heroku run php artisan migrate --force -a procuchain-bac

# Expected output:
# Running migrations...
# Migration table created successfully.
# Migrating: 0001_01_01_000000_create_users_table
# Migrated:  0001_01_01_000000_create_users_table (123.45ms)
# ...
# (Should see ~20 migrations complete)
```

---

### Step 5.2: Seed Database

```bash
# Seed roles and permissions
heroku run php artisan db:seed --force -a procuchain-bac

# Expected output:
# Seeding: Database\Seeders\RolesAndPermissionsSeeder
# Creating roles...
# Creating permissions...
# Assigning permissions to roles...
# Seeded successfully.
```

---

### Step 5.3: Verify MultiChain Connection

```bash
# Test MultiChain connectivity
heroku run php artisan multichain:setup --check -a procuchain-bac

# Expected output:
# ✓ Connecting to MultiChain node...
# ✓ MultiChain connection successful!
# ✓ Chain: procuchain
# ✓ Node version: 2.3.3
# ✓ Block height: 123
```

**If connection fails:**
```bash
# Check firewall on MultiChain server
# Verify RPC credentials match
# Test from another machine:
curl --user multichainrpc:PASSWORD \
  --data-binary '{"jsonrpc":"1.0","id":"test","method":"getinfo","params":[]}' \
  -H 'content-type: text/plain;' \
  http://YOUR_DROPLET_IP:4786
```

---

### Step 5.4: Generate Blockchain Addresses

```bash
# Run MultiChain setup (generates addresses, creates streams, grants permissions)
heroku run php artisan multichain:setup -a procuchain-bac

# Expected output:
# ✓ Connecting to MultiChain node...
# ✓ Connection successful!
# 
# Generating blockchain addresses...
# ✓ admin address: 1ABC...XYZ789 (generated)
# ✓ bac_secretariat address: 1DEF...UVW456 (generated)
# ✓ bac_chairman address: 1GHI...RST123 (generated)
# ✓ hope address: 1JKL...OPQ999 (generated)
# 
# Creating streams...
# ✓ procurement.documents (created)
# ✓ procurement.status (created)
# ✓ procurement.events (created)
# ✓ procurement.corrections (created)
# 
# Granting permissions...
# ✓ admin global permissions granted
# ✓ admin stream permissions granted
# ✓ bac_secretariat permissions granted
# ✓ bac_chairman permissions granted
# ✓ hope permissions granted
# 
# Updating environment variables...
# ✓ .env file updated with addresses
# 
# ✓ Setup completed successfully!
```

**Important:** The generated addresses need to be added to Heroku config:

```bash
# The command will output addresses like:
# admin: 1ABC123...XYZ789

# Copy each address and set in Heroku:
heroku config:set MULTICHAIN_ADMIN_ADDRESS="1ABC123XYZ789" -a procuchain-bac
heroku config:set MULTICHAIN_BAC_SECRETARIAT_ADDRESS="1DEF456UVW456" -a procuchain-bac
heroku config:set MULTICHAIN_BAC_CHAIRMAN_ADDRESS="1GHI789RST123" -a procuchain-bac
heroku config:set MULTICHAIN_HOPE_ADDRESS="1JKL012OPQ999" -a procuchain-bac
```

---

### Step 5.5: Create Initial Admin User

```bash
# Create admin user via tinker
heroku run php artisan tinker -a procuchain-bac

# In tinker console, run:
$user = App\Models\User::create([
    'name' => 'BAC Administrator',
    'email' => 'admin@bac.gov.ph',
    'password' => Hash::make('SecurePassword123!'),
    'blockchain_address' => config('multichain.addresses.admin'),
    'email_verified_at' => now(),
]);

$user->assignRole('admin');

echo "Admin user created: " . $user->email;

# Press Ctrl+D to exit tinker
```

**Alternative: Via Database Seeder**
```bash
# Create a seeder for initial admin user
# (This can be done pre-deployment)

# Run specific seeder
heroku run php artisan db:seed --class=AdminUserSeeder --force -a procuchain-bac
```

---

### Step 5.6: Optimize Application

```bash
# Cache configuration
heroku run php artisan config:cache -a procuchain-bac

# Cache routes
heroku run php artisan route:cache -a procuchain-bac

# Cache views
heroku run php artisan view:cache -a procuchain-bac

# Verify optimization
heroku run php artisan optimize -a procuchain-bac
```

---

## Phase 6: Testing & Validation

### Step 6.1: Health Check

```bash
# Test application health endpoint
curl https://procuchain-bac.herokuapp.com/up

# Expected response:
# 200 OK

# Check via browser
# Visit: https://procuchain-bac.herokuapp.com
```

---

### Step 6.2: Test Login

**1. Access Application**
```
URL: https://procuchain-bac.herokuapp.com/login
```

**2. Login with Admin User**
```
Email: admin@bac.gov.ph
Password: SecurePassword123!
```

**3. Verify Dashboard Access**
```
- Should redirect to: https://procuchain-bac.herokuapp.com/admin/dashboard
- Should see admin dashboard with navigation
- Check all menu items load without errors
```

---

### Step 6.3: Test User Creation

**1. Create BAC Secretariat User**
```bash
# Via Admin UI:
1. Navigate to Users section
2. Click "Create User"
3. Fill form:
   - Name: BAC Secretariat Test
   - Email: secretariat@bac.gov.ph
   - Role: BAC Secretariat
4. Submit
```

**2. Verify User Login**
```
1. Logout admin
2. Login as: secretariat@bac.gov.ph
3. Should redirect to: /bac-secretariat/dashboard
4. Verify blockchain address is assigned
```

---

### Step 6.4: Test Document Upload

**1. Create Test Procurement**
```bash
# As BAC Secretariat user:
1. Navigate to Procurement Initiation
2. Fill form:
   - Procurement ID: TEST-001
   - Title: Test Procurement
   - Description: Testing document upload
3. Submit
```

**2. Upload Test Document**
```bash
1. Navigate to document upload page
2. Upload PDF file
3. Verify:
   - File uploads to DigitalOcean Spaces
   - Hash generated
   - Blockchain transaction logged
   - Success message displayed
```

**3. Check Blockchain**
```bash
# On MultiChain server
multichain-cli procuchain liststreamitems procurement.documents

# Should show test document metadata
```

---

### Step 6.5: Test Email Notifications

```bash
# Trigger a notification event
# Example: Create a user or upload document

# Check email received
# Check Heroku logs:
heroku logs --tail -a procuchain-bac | grep "Mail"

# Should see email send confirmations
```

---

### Step 6.6: Test Queue Workers

```bash
# Check queue worker status
heroku ps -a procuchain-bac

# Should show worker dyno running

# Check worker logs
heroku logs --tail --dyno worker -a procuchain-bac

# Should see queue job processing
```

---

### Step 6.7: Monitor Application Logs

```bash
# Watch real-time logs
heroku logs --tail -a procuchain-bac

# Check for errors
heroku logs --tail -a procuchain-bac | grep "ERROR"

# Check specific dyno
heroku logs --tail --dyno web.1 -a procuchain-bac

# View via Papertrail (if added)
heroku addons:open papertrail -a procuchain-bac
```

---

## Phase 7: Production Hardening

### Step 7.1: Security Configuration

**1. Force HTTPS**
```bash
# Laravel automatically forces HTTPS in production
# Verify APP_ENV=production is set
heroku config:get APP_ENV -a procuchain-bac
```

**2. Set HSTS Headers**
```bash
# Add to bootstrap/app.php (already done in Laravel 12)
# Verify headers are set:
curl -I https://procuchain-bac.herokuapp.com
```

**3. Configure Trusted Proxies**
```bash
# Update app/Http/Middleware/TrustProxies.php
# Set $proxies = '*' for Heroku

# Verify via:
heroku run cat app/Http/Middleware/TrustProxies.php -a procuchain-bac
```

---

### Step 7.2: Database Backups

**1. Enable Automatic Backups (JawsDB)**
```bash
# Upgrade to paid plan for backups
heroku addons:upgrade jawsdb:leopard -a procuchain-bac
# Leopard: $50/month with daily backups

# Or manually backup:
heroku run php artisan db:backup -a procuchain-bac
```

**2. Manual Backup Script**
```bash
# Create backup script locally
#!/bin/bash
heroku run "mysqldump --no-tablespaces -u USERNAME -p'PASSWORD' -h HOST DATABASE > backup.sql" -a procuchain-bac

# Schedule with cron or Task Scheduler
```

---

### Step 7.3: Monitoring Setup

**1. Application Monitoring**
```bash
# Add Heroku Metrics (included with paid dynos)
# View at: https://dashboard.heroku.com/apps/procuchain-bac/metrics

# Key metrics to watch:
# - Response time
# - Memory usage
# - Dyno load
# - Error rate
```

**2. Error Tracking (Optional - Sentry)**
```bash
# Add Sentry for error tracking
composer require sentry/sentry-laravel

# Configure Sentry DSN
heroku config:set SENTRY_LARAVEL_DSN="your_sentry_dsn" -a procuchain-bac
```

**3. Uptime Monitoring**
```bash
# Use external service:
# - UptimeRobot (free)
# - Pingdom
# - StatusCake

# Monitor: https://procuchain-bac.herokuapp.com/up
```

---

### Step 7.4: Performance Optimization

**1. Enable OPcache**
```bash
# Create .user.ini in public directory
cat > public/.user.ini << EOF
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
EOF

# Commit and deploy
git add public/.user.ini
git commit -m "Enable OPcache for production"
git push heroku main
```

**2. Optimize Composer Autoloader**
```bash
# Already done in production deployment
# Verify composer.json has:
# "optimize-autoloader": true
```

**3. Configure PHP Settings**
```bash
# Create .user.ini in root if needed
heroku config:set PHP_MEMORY_LIMIT=512M -a procuchain-bac
heroku config:set PHP_MAX_EXECUTION_TIME=300 -a procuchain-bac
```

---

### Step 7.5: Setup Maintenance Mode

```bash
# Enable maintenance mode
heroku run php artisan down --secret="bypass-token-123" -a procuchain-bac

# Access app during maintenance with:
# https://procuchain-bac.herokuapp.com/bypass-token-123

# Disable maintenance mode
heroku run php artisan up -a procuchain-bac
```

---

### Step 7.6: Custom Domain Setup (Optional)

**1. Add Domain to Heroku**
```bash
# Add custom domain
heroku domains:add procuchain.bac.gov.ph -a procuchain-bac

# Get DNS target
heroku domains -a procuchain-bac

# Will show: procuchain.bac.gov.ph -> something.herokudns.com
```

**2. Configure DNS**
```bash
# Add CNAME record in your DNS provider:
# Type: CNAME
# Name: procuchain
# Value: something.herokudns.com
# TTL: 300
```

**3. Enable Automated Certificate Management**
```bash
# Heroku automatically provisions SSL for custom domains
# Verify:
heroku certs:auto:enable -a procuchain-bac

# Check certificate status
heroku certs -a procuchain-bac
```

**4. Update Environment**
```bash
# Update APP_URL
heroku config:set APP_URL=https://procuchain.bac.gov.ph -a procuchain-bac
```

---

## Troubleshooting

### Issue 1: Deployment Fails

**Symptoms:**
```
remote: !     Push rejected, failed to compile PHP app.
```

**Solution:**
```bash
# Check composer.json syntax
composer validate

# Clear composer cache
rm -rf vendor
composer clear-cache
composer install

# Ensure composer.lock is committed
git add composer.lock
git commit -m "Update composer.lock"
git push heroku main
```

---

### Issue 2: Application Error / 503

**Symptoms:**
```
Application error - An error occurred in the application
```

**Solution:**
```bash
# Check logs
heroku logs --tail -a procuchain-bac

# Common causes:
# 1. Missing APP_KEY
heroku config:get APP_KEY -a procuchain-bac

# 2. Database not configured
heroku config:get DATABASE_URL -a procuchain-bac

# 3. Migrations not run
heroku run php artisan migrate --force -a procuchain-bac

# 4. Clear caches
heroku run php artisan config:clear -a procuchain-bac
heroku run php artisan cache:clear -a procuchain-bac
heroku run php artisan route:clear -a procuchain-bac
heroku run php artisan view:clear -a procuchain-bac

# Restart dynos
heroku restart -a procuchain-bac
```

---

### Issue 3: MultiChain Connection Failed

**Symptoms:**
```
MultiChain connection failed: Connection refused
```

**Solution:**
```bash
# 1. Check MultiChain is running
ssh root@YOUR_DROPLET_IP
systemctl status multichain
multichain-cli procuchain getinfo

# 2. Check firewall
ufw status
# Ensure port 4786 is open

# 3. Test RPC from external
curl --user multichainrpc:PASSWORD \
  --data-binary '{"jsonrpc":"1.0","id":"test","method":"getinfo","params":[]}' \
  -H 'content-type: text/plain;' \
  http://YOUR_DROPLET_IP:4786

# 4. Verify Heroku config
heroku config:get MULTICHAIN_HOST -a procuchain-bac
heroku config:get MULTICHAIN_PORT -a procuchain-bac
heroku config:get MULTICHAIN_USERNAME -a procuchain-bac

# 5. Check multichain.conf
cat ~/.multichain/procuchain/multichain.conf
# Ensure rpcallowip=0.0.0.0/0
```

---

### Issue 4: File Upload Fails

**Symptoms:**
```
Error: Could not upload file to storage
```

**Solution:**
```bash
# 1. Verify Spaces configuration
heroku config:get AWS_ACCESS_KEY_ID -a procuchain-bac
heroku config:get AWS_SECRET_ACCESS_KEY -a procuchain-bac
heroku config:get AWS_BUCKET -a procuchain-bac
heroku config:get AWS_ENDPOINT -a procuchain-bac

# 2. Test access from Heroku
heroku run bash -a procuchain-bac
# In bash:
php artisan tinker
Storage::disk('s3')->put('test.txt', 'test content');
Storage::disk('s3')->exists('test.txt');

# 3. Check Spaces bucket permissions
# Ensure bucket is NOT public (for security)
# But app credentials have read/write access

# 4. Verify FILESYSTEM_DISK
heroku config:get FILESYSTEM_DISK -a procuchain-bac
# Should be: s3
```

---

### Issue 5: Email Not Sending

**Symptoms:**
```
Swift_TransportException: Connection could not be established
```

**Solution:**
```bash
# 1. Check email configuration
heroku config:get MAIL_MAILER -a procuchain-bac
heroku config:get RESEND_API_KEY -a procuchain-bac

# 2. Test Resend API key
curl -X POST https://api.resend.com/emails \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "from": "noreply@yourdomain.com",
    "to": ["test@example.com"],
    "subject": "Test",
    "text": "Test email"
  }'

# 3. Check Resend dashboard for bounces/blocks

# 4. Test from Heroku
heroku run php artisan tinker -a procuchain-bac
Mail::raw('Test email', function($msg) {
    $msg->to('test@example.com')->subject('Test');
});
```

---

### Issue 6: Queue Jobs Not Processing

**Symptoms:**
```
Jobs stuck in queue, not being processed
```

**Solution:**
```bash
# 1. Check worker dyno status
heroku ps -a procuchain-bac

# 2. Scale up if needed
heroku ps:scale worker=1 -a procuchain-bac

# 3. Check worker logs
heroku logs --tail --dyno worker -a procuchain-bac

# 4. Restart worker
heroku restart worker -a procuchain-bac

# 5. Check failed jobs
heroku run php artisan queue:failed -a procuchain-bac

# 6. Retry failed jobs
heroku run php artisan queue:retry all -a procuchain-bac

# 7. Clear queue if stuck
heroku run php artisan queue:clear -a procuchain-bac
```

---

### Issue 7: Slow Performance

**Symptoms:**
```
Application responding slowly
```

**Solution:**
```bash
# 1. Check dyno metrics
heroku logs --tail -a procuchain-bac | grep "Memory\|Load"

# 2. Upgrade dyno type if needed
heroku ps:type web=standard-1x -a procuchain-bac

# 3. Add Redis for caching
heroku addons:create heroku-redis:mini -a procuchain-bac
heroku config:set CACHE_DRIVER=redis -a procuchain-bac
heroku config:set SESSION_DRIVER=redis -a procuchain-bac

# 4. Optimize database queries
# Check slow query logs

# 5. Enable OPcache (see Step 7.4)

# 6. Consider CDN for assets
```

---

### Issue 8: Database Connection Errors

**Symptoms:**
```
SQLSTATE[HY000]: General error: 2006 MySQL server has gone away
```

**Solution:**
```bash
# 1. Check database addon status
heroku addons -a procuchain-bac

# 2. Get connection info
heroku config:get JAWSDB_URL -a procuchain-bac

# 3. Upgrade database plan if needed
heroku addons:upgrade jawsdb:leopard -a procuchain-bac

# 4. Add to config/database.php:
'options' => [
    PDO::ATTR_PERSISTENT => true,
    PDO::ATTR_TIMEOUT => 30,
]

# 5. Restart application
heroku restart -a procuchain-bac
```

---

## Maintenance & Operations

### Regular Maintenance Tasks

**Daily:**
```bash
# Monitor logs
heroku logs --tail -a procuchain-bac

# Check dyno status
heroku ps -a procuchain-bac

# Monitor error rate
heroku addons:open papertrail -a procuchain-bac
```

**Weekly:**
```bash
# Review slow queries
# Check storage usage
# Review failed jobs
heroku run php artisan queue:failed -a procuchain-bac

# Check MultiChain blockchain height
ssh root@YOUR_DROPLET_IP
multichain-cli procuchain getinfo
```

**Monthly:**
```bash
# Database backup
# Review and rotate logs
# Security updates
composer update
git commit -am "Monthly security updates"
git push heroku main

# Check costs
heroku apps:info -a procuchain-bac
```

---

### Updating Application

**For New Features/Bug Fixes:**
```bash
# 1. On local machine
git checkout main
git pull origin main

# 2. Test changes locally
composer install
npm install
npm run build
php artisan test

# 3. Deploy to Heroku
git push heroku main

# 4. Run migrations if needed
heroku run php artisan migrate --force -a procuchain-bac

# 5. Clear caches
heroku run php artisan config:cache -a procuchain-bac
heroku run php artisan route:cache -a procuchain-bac
heroku run php artisan view:cache -a procuchain-bac

# 6. Restart
heroku restart -a procuchain-bac
```

---

### Scaling Application

**When to Scale:**
- Response time > 1000ms consistently
- Memory usage > 80%
- CPU load average > 1.0
- More than 100 concurrent users

**Vertical Scaling (Larger Dynos):**
```bash
# Upgrade to Standard-1X (2GB RAM)
heroku ps:type web=standard-1x -a procuchain-bac
heroku ps:type worker=standard-1x -a procuchain-bac

# Cost: $25/month per dyno
```

**Horizontal Scaling (More Dynos):**
```bash
# Add more web dynos
heroku ps:scale web=2 -a procuchain-bac

# Add more workers
heroku ps:scale worker=2 -a procuchain-bac

# Note: Requires Standard dynos or higher
```

**Database Scaling:**
```bash
# Upgrade database
heroku addons:upgrade jawsdb:leopard -a procuchain-bac

# Or switch to Heroku Postgres for better performance
```

---

### Backup & Disaster Recovery

**Application Backup:**
```bash
# 1. Backup code (Git)
git push --all origin

# 2. Backup database
heroku pg:backups:capture -a procuchain-bac
heroku pg:backups:download -a procuchain-bac

# 3. Backup Spaces files
# Use DigitalOcean Spaces UI or s3cmd
s3cmd sync s3://procuchain-documents/ ./backup/spaces/

# 4. Backup MultiChain
ssh root@YOUR_DROPLET_IP
tar -czf multichain-backup-$(date +%Y%m%d).tar.gz ~/.multichain/procuchain/
```

**Disaster Recovery:**
```bash
# 1. Create new Heroku app
heroku create procuchain-bac-recovery

# 2. Restore database
heroku pg:backups:restore 'https://backup-url' DATABASE_URL -a procuchain-bac-recovery

# 3. Copy all config
heroku config -s -a procuchain-bac > config.txt
# Manually set in new app

# 4. Deploy code
git push heroku-recovery main

# 5. Run migrations
heroku run php artisan migrate --force -a procuchain-bac-recovery

# 6. Update DNS if using custom domain
```

---

### Monitoring Checklist

**Application Health:**
- [ ] All dynos running
- [ ] Response time < 500ms
- [ ] Error rate < 1%
- [ ] Memory usage < 80%
- [ ] Queue jobs processing

**Infrastructure:**
- [ ] MultiChain node running
- [ ] Database connections < 80%
- [ ] Storage usage monitored
- [ ] Email delivery rate > 95%

**Security:**
- [ ] SSL certificate valid
- [ ] No suspicious login attempts
- [ ] Backups completing successfully
- [ ] Dependencies up to date

---

## Success Checklist

After completing all steps, verify:

- [ ] Application accessible at HTTPS URL
- [ ] Login working for all user roles
- [ ] Documents uploading successfully
- [ ] Blockchain transactions recording
- [ ] Email notifications sending
- [ ] Queue workers processing jobs
- [ ] No errors in logs
- [ ] All tests passing
- [ ] Backups configured
- [ ] Monitoring setup
- [ ] Custom domain configured (if applicable)
- [ ] Admin user created and can login
- [ ] MultiChain connection verified
- [ ] Storage configuration working
- [ ] Performance acceptable

---

## Quick Reference

### Essential Commands

```bash
# Deploy
git push heroku main

# Logs
heroku logs --tail -a procuchain-bac

# Restart
heroku restart -a procuchain-bac

# Run artisan
heroku run php artisan [command] -a procuchain-bac

# Scale
heroku ps:scale web=1 worker=1 -a procuchain-bac

# Config
heroku config -a procuchain-bac

# Database
heroku run php artisan migrate --force -a procuchain-bac

# Cache
heroku run php artisan config:cache -a procuchain-bac

# Maintenance
heroku run php artisan down -a procuchain-bac
heroku run php artisan up -a procuchain-bac

# MultiChain test
heroku run php artisan multichain:setup --check -a procuchain-bac
```

---

### Important URLs

```
Application: https://procuchain-bac.herokuapp.com
Login: https://procuchain-bac.herokuapp.com/login
Health Check: https://procuchain-bac.herokuapp.com/up

Heroku Dashboard: https://dashboard.heroku.com/apps/procuchain-bac
Heroku Logs: https://dashboard.heroku.com/apps/procuchain-bac/logs
```

---

### Support Contacts

**Technical Issues:**
- Development Team: [Your contact]
- Email: support@example.com

**Infrastructure:**
- Heroku Support: https://help.heroku.com
- DigitalOcean Support: https://www.digitalocean.com/support
- Resend Support: https://resend.com/docs

**Emergency Contacts:**
- On-call: [Phone number]
- Slack: #procuchain-ops

---

## Conclusion

You have successfully deployed ProcuChain to Heroku! 🎉

The application is now ready for BAC Office use with:
- ✅ Secure blockchain-backed document management
- ✅ Complete procurement workflow automation
- ✅ Role-based access control
- ✅ Email and push notifications
- ✅ Audit trail and compliance features

**Next Steps:**
1. Train BAC Office staff on system usage
2. Import existing procurement data (if applicable)
3. Monitor application performance and user feedback
4. Schedule regular maintenance windows
5. Plan for future enhancements

**Remember:**
- Keep your environment variables secure
- Monitor your blockchain node regularly
- Maintain regular backups
- Review logs for issues
- Keep dependencies updated

For ongoing support and updates, refer to the project README and documentation.

---

**Document Version:** 1.0  
**Last Updated:** October 18, 2025  
**Maintained By:** ProcuChain Development Team
