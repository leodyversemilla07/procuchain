# ProcuChain Network Topology

> Network architecture diagram from user client desktop to blockchain nodes

---

## Table of Contents

1. [Overview](#overview)
2. [Network Topology Diagram](#network-topology-diagram)
3. [Layer-by-Layer Breakdown](#layer-by-layer-breakdown)
4. [Component Details](#component-details)
5. [Network Ports & Protocols](#network-ports--protocols)
6. [Data Flow Sequences](#data-flow-sequences)
7. [Security Boundaries](#security-boundaries)
8. [Deployment Scenarios](#deployment-scenarios)

---

## Overview

ProcuChain is a distributed blockchain-backed procurement system with multiple network layers spanning from user client devices to blockchain nodes. The architecture follows a multi-tier pattern with clear separation between presentation, application, data, and blockchain layers.

**Key Architecture Characteristics:**
- Client-Server Architecture with SPA (Single Page Application)
- RESTful API communication
- Blockchain integration via JSON-RPC
- Cloud-based file storage
- Database-driven sessions and queues

---

## Network Topology Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                            USER CLIENT LAYER                                 │
│                         (Desktop/Laptop/Mobile)                              │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      │ HTTPS/443 (Browser)
                                      │ WebSocket (Push Notifications)
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                          WEB SERVER LAYER                                    │
│                    (Apache/Nginx + PHP-FPM)                                  │
│                                                                              │
│  ┌──────────────────────────────────────────────────────────┐               │
│  │  Frontend Layer (Vite-built React SPA)                   │               │
│  │  - Static Assets (JS/CSS/Images)                         │               │
│  │  - Service Worker (Push Notifications)                   │               │
│  │  - React 19 + Inertia.js v2 Client                       │               │
│  └──────────────────────────────────────────────────────────┘               │
│                             │                                                │
│                             │ HTTP/Internal                                  │
│                             ▼                                                │
│  ┌──────────────────────────────────────────────────────────┐               │
│  │  Laravel 12 Application Server (PHP 8.2+)                │               │
│  │  - public/index.php (Entry Point)                        │               │
│  │  - Inertia SSR Server (Node.js) [Optional]               │               │
│  │  - Route Handlers (web.php, auth.php, settings.php)      │               │
│  │  - Controllers (BAC, HOPE, Admin, Procurement, etc.)     │               │
│  │  - Middleware (Auth, Role-based Access Control)          │               │
│  │  - Services (MultichainService, ProcurementService)      │               │
│  │  - Jobs (Queue: Database-driven)                         │               │
│  └──────────────────────────────────────────────────────────┘               │
└─────────────────────────────────────────────────────────────────────────────┘
         │              │                │                 │
         │              │                │                 │
         ▼              ▼                ▼                 ▼
    ┌────────┐   ┌────────────┐   ┌──────────┐    ┌────────────────┐
    │ MySQL  │   │ Cloud File │   │  SMTP    │    │   MultiChain   │
    │Database│   │  Storage   │   │  Server  │    │ Blockchain Node│
    │        │   │ (S3/Spaces)│   │  (Email) │    │                │
    └────────┘   └────────────┘   └──────────┘    └────────────────┘
    3306/TCP     HTTPS/443        587/TLS         RPC:4786/TCP
                                                   P2P:6271/TCP
         │              │                │                 │
         │              │                │                 │
         └──────────────┴────────────────┴─────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                        EXTERNAL SERVICES LAYER                               │
│                                                                              │
│  ┌────────────────────┐  ┌──────────────────┐  ┌───────────────────────┐   │
│  │  AWS S3 Compatible │  │   Email Gateway  │  │   MultiChain P2P      │   │
│  │  Storage (DigOcean │  │   (Gmail SMTP)   │  │   Network             │   │
│  │  Spaces - SGP1)    │  │                  │  │   (Other Nodes)       │   │
│  └────────────────────┘  └──────────────────┘  └───────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────────┘
                              │
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                      BLOCKCHAIN NETWORK LAYER                                │
│                                                                              │
│  ┌──────────────────┐    ┌──────────────────┐    ┌──────────────────┐      │
│  │  MultiChain Node │◄───│  MultiChain Node │◄───│  MultiChain Node │      │
│  │   (Primary)      │    │   (Peer 1)       │    │   (Peer 2)       │      │
│  │                  │───►│                  │───►│                  │      │
│  │ Chain: procuchain│    │ Chain: procuchain│    │ Chain: procuchain│      │
│  │                  │    │                  │    │                  │      │
│  │ Streams:         │    │ Streams:         │    │ Streams:         │      │
│  │ - documents      │    │ - documents      │    │ - documents      │      │
│  │ - status         │    │ - status         │    │ - status         │      │
│  │ - events         │    │ - events         │    │ - events         │      │
│  │ - corrections    │    │ - corrections    │    │ - corrections    │      │
│  └──────────────────┘    └──────────────────┘    └──────────────────┘      │
│         ▲                         ▲                         ▲               │
│         │                         │                         │               │
│         └─────────────────────────┴─────────────────────────┘               │
│                    P2P Communication (Port 6271/TCP)                         │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Layer-by-Layer Breakdown

### 1. User Client Layer

**Components:**
- Desktop browsers (Chrome, Firefox, Safari, Edge)
- Mobile browsers (iOS Safari, Android Chrome)
- Progressive Web App (PWA) capability via Service Worker

**Technologies:**
- React 19 SPA rendered via Inertia.js v2
- Tailwind CSS v4 for styling
- WebPush API for browser notifications
- VAPID protocol for push authentication

**Client-Side Features:**
- Dynamic UI rendering without page refreshes
- Real-time search with debounced API calls
- Client-side routing (Inertia Router)
- Theme management (Light/Dark/System)
- Offline-capable service worker (`public/sw.js`)
- Browser notification subscriptions

**Network Interactions:**
```
User Browser
    ↓ HTTPS/443 (TLS 1.2+)
    → GET/POST/PUT/DELETE requests
    → WebSocket connections (if enabled)
    → Push notification subscriptions (VAPID)
    ← HTML/JSON responses
    ← Static assets (JS, CSS, images)
    ← Server-side events (notifications)
```

---

### 2. Web Server Layer

**Components:**

#### 2.1 Web Server (Apache/Nginx)
- **Default Development:** PHP built-in server (`php artisan serve`)
- **Production:** Apache2 with mod_php or Nginx + PHP-FPM
- **Entry Point:** `public/index.php`
- **Document Root:** `/public/`

**Configuration:**
```apache
# Apache (.htaccess)
- Rewrites all non-file requests to index.php
- Authorization header handling
- XSRF token header forwarding
- Trailing slash redirects
```

#### 2.2 Laravel Application Server
- **Framework:** Laravel 12 (PHP 8.2+)
- **Architecture:** MVC + Service Layer + Job Queues
- **Session Management:** Database-driven
- **Cache:** Database-driven
- **Queue:** Database-driven (synchronous or async workers)

**Key Directories:**
```
app/
├── Console/Commands/          # Artisan commands (MultichainSetup)
├── Http/
│   ├── Controllers/           # Request handlers
│   ├── Middleware/            # Auth, RBAC, CORS
│   └── Requests/              # Form validation
├── Jobs/                      # Queue jobs (blockchain, documents)
├── Libraries/                 # MultichainClient
├── Models/                    # Eloquent ORM models
├── Services/                  # Business logic
└── Notifications/             # Email, push notifications
```

**Routes:**
```php
routes/
├── web.php                    # Main web routes
├── auth.php                   # Authentication routes
├── settings.php               # User settings routes
└── file-uploads-ui-preview.php # File upload previews
```

**Middleware Stack:**
1. Authentication (`auth`)
2. Role-based access (`role:admin|bac_secretariat|bac_chairman|hope`)
3. CSRF protection
4. Session management
5. Throttling (rate limiting)

#### 2.3 Inertia SSR Server (Optional)
- **Runtime:** Node.js
- **Purpose:** Server-side rendering for SEO/performance
- **Entry:** `resources/js/ssr.tsx`
- **Port:** Configurable (typically 13714)
- **Process:** Managed by `php artisan inertia:start-ssr`

---

### 3. Database Layer

**Database Server:**
- **RDBMS:** MySQL 8.0+ (or compatible: MariaDB)
- **Host:** Configurable via `DB_HOST` (default: 127.0.0.1)
- **Port:** 3306/TCP (default)
- **Connection:** TCP with authentication

**Database Schema:**
```sql
procuchain
├── users                      # User accounts with blockchain addresses
├── user_login_logs            # Login tracking and audit
├── notifications              # In-app notifications
├── push_subscriptions         # WebPush subscriptions
├── sessions                   # User sessions
├── cache                      # Application cache
├── jobs                       # Queue jobs
├── failed_jobs                # Failed queue jobs
├── migrations                 # Schema version control
└── password_reset_tokens      # Password reset tokens
```

**Key Tables:**
- `users.blockchain_address`: Links users to blockchain addresses
- `sessions`: Database-driven session storage
- `jobs`: Queue job storage (synchronous or async processing)
- `user_login_logs`: Security audit trail

**Connection Details:**
```
Laravel App → MySQL
    Protocol: TCP
    Port: 3306
    Auth: Username/Password (DB_USERNAME, DB_PASSWORD)
    Connection Pool: Laravel's database manager
    Encryption: Optional (MySQL over SSL)
```

---

### 4. File Storage Layer

**Cloud Storage Provider:**
- **Service:** AWS S3-compatible (DigitalOcean Spaces)
- **Region:** Singapore (sgp1)
- **Endpoint:** `https://sgp1.digitaloceanspaces.com`
- **Access:** AWS SDK via access key + secret key

**Configuration:**
```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=sgp1
AWS_BUCKET=your_bucket_name
AWS_ENDPOINT=https://sgp1.digitaloceanspaces.com
```

**Storage Structure:**
```
Bucket: {AWS_BUCKET}
├── procurement-documents/
│   ├── {procurement_id}/
│   │   ├── {file_hash}.pdf
│   │   ├── {file_hash}.docx
│   │   └── {file_hash}.xlsx
│   └── ...
└── public/                    # Public assets (if any)
```

**Network Flow:**
```
Laravel App → Cloud Storage API
    Protocol: HTTPS
    Port: 443
    Auth: AWS Signature v4
    Operations:
        - PUT (upload files)
        - GET (retrieve files)
        - DELETE (remove files)
        - HEAD (check existence)
```

---

### 5. Email Service Layer

**SMTP Server:**
- **Provider:** Gmail SMTP (or configurable)
- **Host:** smtp.gmail.com
- **Port:** 587 (TLS) or 465 (SSL)
- **Authentication:** App password (not regular password)

**Configuration:**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_app_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your_email@gmail.com
MAIL_FROM_NAME="ProcuChain"
```

**Email Types:**
- Workflow transition notifications
- Account security alerts
- Password reset emails
- System notifications

**Network Flow:**
```
Laravel App → SMTP Server
    Protocol: SMTP over TLS
    Port: 587
    Auth: Username/Password
    Queue: Database-driven (async sending)
```

---

### 6. Blockchain Layer (MultiChain)

**Primary MultiChain Node:**

**Server Requirements:**
- **OS:** Linux (Ubuntu/Debian recommended)
- **MultiChain Version:** 2.3.3 (Community Edition)
- **Binaries:** `multichaind`, `multichain-cli`, `multichain-util`
- **Installation:** Via `scripts/install_procuchain.sh`

**Network Configuration:**
```
Chain Name: procuchain
RPC Port: 4786 (default, configurable)
P2P Port: 6271 (default, configurable)
Web Port: 7448 (optional web interface)
```

**RPC Interface:**
- **Protocol:** JSON-RPC 2.0 over HTTP(S)
- **Host:** Configurable via `MULTICHAIN_HOST`
- **Port:** Configurable via `MULTICHAIN_PORT` (default: 4786)
- **Authentication:** Basic Auth (username/password from `multichain.conf`)
- **Client:** `App\Libraries\MultichainClient` (PHP JSON-RPC client)

**MultiChain Configuration Files:**
```
~/.multichain/procuchain/
├── params.dat                 # Blockchain parameters
├── multichain.conf            # RPC credentials
├── permissions.dat            # Permission ledger
└── wallet.dat                 # Node wallet
```

**Blockchain Streams:**
```
1. procurement.documents       # Document metadata and hashes
2. procurement.status          # Procurement status transitions
3. procurement.events          # Audit events and logs
4. procurement.corrections     # Correction records
```

**Role-Based Addresses:**
```
MULTICHAIN_ADMIN_ADDRESS               # Full admin permissions
MULTICHAIN_BAC_SECRETARIAT_ADDRESS     # BAC Secretariat operations
MULTICHAIN_BAC_CHAIRMAN_ADDRESS        # BAC Chairman approvals
MULTICHAIN_HOPE_ADDRESS                # HOPE oversight
```

**Permission Matrix:**
| Role              | Global Permissions                                    | Stream Permissions       |
|-------------------|-------------------------------------------------------|--------------------------|
| Admin             | admin, send, receive, create, issue, mine, activate  | admin, write, read       |
| BAC Secretariat   | send, receive, create, issue, activate               | admin, write, read       |
| BAC Chairman      | send, receive                                        | write, read              |
| HOPE              | send, receive                                        | write, read              |

**Network Flow:**
```
Laravel App → MultiChain Node (RPC)
    Protocol: JSON-RPC over HTTP(S)
    Port: 4786/TCP
    Auth: Basic Auth (username/password)
    Timeout: 30s (console), 12s (web)
    Retries: 3 (console), 2 (web)
    
    Methods Used:
        - getinfo()
        - publish()
        - subscribe()
        - liststreamitems()
        - liststreams()
        - getnewaddress()
        - grant()
        - create()
```

**MultiChain P2P Network:**
```
Node 1 (Primary) ←→ Node 2 (Peer)
    Protocol: MultiChain P2P
    Port: 6271/TCP
    Purpose: Block/transaction synchronization
    Consensus: Mining permissions
    
Connection Command:
    multichaind procuchain@{RPC_HOST}:{P2P_PORT}
```

**Blockchain Data Structure:**
```json
// Document Stream Item
{
    "txid": "transaction_hash",
    "vout": 0,
    "address": "blockchain_address",
    "key": "procurement_id",
    "data": {
        "json": {
            "document_type": "bidding_documents",
            "file_hash": "sha256_hash",
            "file_size": 1024000,
            "file_key": "s3_object_key",
            "user_address": "publisher_address",
            "timestamp": "2025-10-16T12:00:00Z",
            "procurement_id": "PROC-2025-001",
            "procurement_title": "IT Equipment Procurement"
        }
    }
}

// Status Stream Item
{
    "key": "procurement_id",
    "data": {
        "json": {
            "status": "bid_opening",
            "stage": "bidding",
            "previous_status": "pre_bid_conference",
            "timestamp": "2025-10-16T14:00:00Z",
            "changed_by": "blockchain_address"
        }
    }
}
```

---

## Network Ports & Protocols

### Inbound Connections (User → Application)

| Port  | Protocol | Service          | Purpose                          | Security       |
|-------|----------|------------------|----------------------------------|----------------|
| 443   | HTTPS    | Web Server       | User browser access              | TLS 1.2+       |
| 80    | HTTP     | Web Server       | HTTP redirect to HTTPS           | Redirect only  |
| 8000  | HTTP     | Dev Server       | Laravel development server       | Dev only       |

### Outbound Connections (Application → External Services)

| Port  | Protocol      | Service          | Purpose                          | Security       |
|-------|---------------|------------------|----------------------------------|----------------|
| 3306  | MySQL         | Database         | Data persistence                 | Auth required  |
| 4786  | JSON-RPC/HTTP | MultiChain RPC   | Blockchain operations            | Basic Auth     |
| 443   | HTTPS         | S3/Spaces        | File storage                     | AWS Sig v4     |
| 587   | SMTP/TLS      | Email Server     | Email notifications              | TLS + Auth     |

### MultiChain Node Ports

| Port  | Protocol      | Service          | Purpose                          | Security       |
|-------|---------------|------------------|----------------------------------|----------------|
| 4786  | JSON-RPC/HTTP | MultiChain RPC   | API access for Laravel           | Basic Auth     |
| 6271  | P2P           | MultiChain P2P   | Node-to-node synchronization     | Chain specific |
| 7448  | HTTP          | MultiChain Web   | Optional web interface           | Basic Auth     |

### Internal Services (Docker/Local Development)

| Port  | Protocol | Service          | Purpose                          |
|-------|----------|------------------|----------------------------------|
| 3307  | MySQL    | Docker MySQL     | Development database (mapped)    |
| 6379  | Redis    | Docker Redis     | Cache/session (if using Redis)   |
| 13714 | HTTP     | Inertia SSR      | Server-side rendering            |

---

## Data Flow Sequences

### 1. Document Upload Flow

```
┌──────────┐     ┌────────────┐     ┌─────────────┐     ┌────────────┐     ┌────────────┐
│  User    │     │   Laravel  │     │    Cloud    │     │   MySQL    │     │ MultiChain │
│ Browser  │     │Application │     │   Storage   │     │  Database  │     │    Node    │
└────┬─────┘     └─────┬──────┘     └──────┬──────┘     └─────┬──────┘     └─────┬──────┘
     │                 │                    │                  │                  │
     │ 1. Upload File  │                    │                  │                  │
     ├────────────────►│                    │                  │                  │
     │                 │                    │                  │                  │
     │                 │ 2. Validate File   │                  │                  │
     │                 │    (Size, Type)    │                  │                  │
     │                 │                    │                  │                  │
     │                 │ 3. Calculate Hash  │                  │                  │
     │                 │    (SHA-256)       │                  │                  │
     │                 │                    │                  │                  │
     │                 │ 4. Store File      │                  │                  │
     │                 ├───────────────────►│                  │                  │
     │                 │                    │                  │                  │
     │                 │ 5. S3 Object Key   │                  │                  │
     │                 │◄───────────────────┤                  │                  │
     │                 │                    │                  │                  │
     │                 │ 6. Save Metadata   │                  │                  │
     │                 ├────────────────────┴─────────────────►│                  │
     │                 │                                       │                  │
     │                 │ 7. Dispatch Job    │                  │                  │
     │                 │    (Queue)         │                  │                  │
     │                 │                    │                  │                  │
     │ 8. Success      │                    │                  │                  │
     │◄────────────────┤                    │                  │                  │
     │                 │                    │                  │                  │
     │                 │ [Background Job Starts]               │                  │
     │                 │                    │                  │                  │
     │                 │ 9. Publish to      │                  │                  │
     │                 │    Blockchain      │                  │                  │
     │                 ├────────────────────┴──────────────────┴─────────────────►│
     │                 │                                                          │
     │                 │    publish('procurement.documents', key, metadata)       │
     │                 │                                                          │
     │                 │ 10. Transaction ID │                                     │
     │                 │◄─────────────────────────────────────────────────────────┤
     │                 │                    │                  │                  │
     │                 │ 11. Update DB      │                  │                  │
     │                 ├────────────────────┴─────────────────►│                  │
     │                 │    (txid, confirmed)                  │                  │
     │                 │                    │                  │                  │
```

### 2. Document Verification Flow

```
┌──────────┐     ┌────────────┐     ┌────────────┐     ┌────────────┐
│  User    │     │   Laravel  │     │   MySQL    │     │ MultiChain │
│ Browser  │     │Application │     │  Database  │     │    Node    │
└────┬─────┘     └─────┬──────┘     └─────┬──────┘     └─────┬──────┘
     │                 │                  │                  │
     │ 1. View Doc     │                  │                  │
     ├────────────────►│                  │                  │
     │                 │                  │                  │
     │                 │ 2. Get Metadata  │                  │
     │                 ├─────────────────►│                  │
     │                 │                  │                  │
     │                 │ 3. Doc Info      │                  │
     │                 │    (hash, txid)  │                  │
     │                 │◄─────────────────┤                  │
     │                 │                  │                  │
     │                 │ 4. Query Chain   │                  │
     │                 ├──────────────────┴─────────────────►│
     │                 │  liststreamitems('procurement.documents', key)
     │                 │                                     │
     │                 │ 5. Blockchain    │                  │
     │                 │    Record        │                  │
     │                 │◄────────────────────────────────────┤
     │                 │                  │                  │
     │                 │ 6. Verify Hash   │                  │
     │                 │    Match         │                  │
     │                 │                  │                  │
     │ 7. Verified     │                  │                  │
     │    Status       │                  │                  │
     │◄────────────────┤                  │                  │
     │                 │                  │                  │
```

### 3. Workflow Transition Flow

```
┌──────────┐     ┌────────────┐     ┌─────────────┐     ┌────────────┐
│BAC User  │     │   Laravel  │     │    MySQL    │     │ MultiChain │
│ Browser  │     │Application │     │   Database  │     │    Node    │
└────┬─────┘     └─────┬──────┘     └──────┬──────┘     └─────┬──────┘
     │                 │                   │                  │
     │ 1. Submit       │                   │                  │
     │    Transition   │                   │                  │
     ├────────────────►│                   │                  │
     │                 │                   │                  │
     │                 │ 2. Validate       │                  │
     │                 │    Permissions    │                  │
     │                 │    & Rules        │                  │
     │                 │                   │                  │
     │                 │ 3. Check Current  │                  │
     │                 │    Status         │                  │
     │                 ├──────────────────►│                  │
     │                 │                   │                  │
     │                 │ 4. Current State  │                  │
     │                 │◄──────────────────┤                  │
     │                 │                   │                  │
     │                 │ 5. Validate       │                  │
     │                 │    Transition     │                  │
     │                 │    (Business      │                  │
     │                 │     Logic)        │                  │
     │                 │                   │                  │
     │                 │ 6. Publish Status │                  │
     │                 ├───────────────────┴─────────────────►│
     │                 │  publish('procurement.status', ...)  │
     │                 │                                      │
     │                 │ 7. Transaction ID│                  │
     │                 │◄─────────────────────────────────────┤
     │                 │                   │                  │
     │                 │ 8. Update DB      │                  │
     │                 ├──────────────────►│                  │
     │                 │                   │                  │
     │                 │ 9. Log Event      │                  │
     │                 ├───────────────────┴─────────────────►│
     │                 │  publish('procurement.events', ...)  │
     │                 │                                      │
     │                 │ 10. Send Email    │                  │
     │                 │     Notification  │                  │
     │                 │     (Queue Job)   │                  │
     │                 │                   │                  │
     │ 11. Success     │                   │                  │
     │     Response    │                   │                  │
     │◄────────────────┤                   │                  │
     │                 │                   │                  │
```

### 4. User Authentication Flow

```
┌──────────┐     ┌────────────┐     ┌─────────────┐     ┌────────────┐
│  User    │     │   Laravel  │     │    MySQL    │     │  Session   │
│ Browser  │     │Application │     │   Database  │     │   Store    │
└────┬─────┘     └─────┬──────┘     └──────┬──────┘     └─────┬──────┘
     │                 │                   │                  │
     │ 1. Login        │                   │                  │
     │    Credentials  │                   │                  │
     ├────────────────►│                   │                  │
     │                 │                   │                  │
     │                 │ 2. Validate       │                  │
     │                 │    Credentials    │                  │
     │                 ├──────────────────►│                  │
     │                 │                   │                  │
     │                 │ 3. User Data      │                  │
     │                 │    & Roles        │                  │
     │                 │◄──────────────────┤                  │
     │                 │                   │                  │
     │                 │ 4. Check Account  │                  │
     │                 │    Lockout Status │                  │
     │                 ├──────────────────►│                  │
     │                 │                   │                  │
     │                 │ 5. Lockout Info   │                  │
     │                 │◄──────────────────┤                  │
     │                 │                   │                  │
     │                 │ 6. Create Session │                  │
     │                 ├───────────────────┴─────────────────►│
     │                 │                                      │
     │                 │ 7. Session ID     │                  │
     │                 │◄─────────────────────────────────────┤
     │                 │                   │                  │
     │                 │ 8. Log Login      │                  │
     │                 │    Activity       │                  │
     │                 ├──────────────────►│                  │
     │                 │                   │                  │
     │ 9. Set Cookie   │                   │                  │
     │    & Redirect   │                   │                  │
     │◄────────────────┤                   │                  │
     │                 │                   │                  │
```

---

## Security Boundaries

### 1. Network Perimeter

```
Internet
    │
    │ TLS 1.2+ (HTTPS)
    ▼
┌────────────────────────────┐
│  Firewall / Load Balancer  │
│  - DDoS Protection         │
│  - SSL Termination         │
│  - Rate Limiting           │
└────────────────────────────┘
    │
    ▼
```

### 2. Application Security Zones

**Public Zone (No Authentication Required):**
- Home page
- About page
- Team page
- Contact page
- Login page
- Password reset

**Authenticated Zone (Requires Login):**
- User dashboard (role-specific)
- Procurement listings
- Document viewing
- Profile settings
- Notifications

**Role-Based Access Control:**
```
Admin:
    - Full system access
    - User management
    - Login audit logs
    - Account lockout management
    - Blockchain administration

BAC Secretariat:
    - Create procurements
    - Upload documents
    - Manage workflow transitions
    - View all procurements

BAC Chairman:
    - View procurements
    - Approve transitions
    - Review documents

HOPE:
    - View procurements
    - Oversight functions
    - Review audit trails
```

### 3. Data Security

**Encryption at Rest:**
- Database: Encrypted storage (configurable)
- File Storage: S3/Spaces server-side encryption
- Blockchain: Immutable, publicly readable (no sensitive data)

**Encryption in Transit:**
- Browser ↔ Web Server: TLS 1.2+
- Application ↔ Database: MySQL over SSL (optional)
- Application ↔ S3: HTTPS
- Application ↔ SMTP: TLS/STARTTLS
- Application ↔ MultiChain: HTTP (internal network) or HTTPS

**Sensitive Data Handling:**
- Passwords: Bcrypt hashed (12 rounds)
- Session tokens: Encrypted
- CSRF tokens: Per-session unique
- API credentials: Environment variables only
- Blockchain addresses: Masked display (first 6 + last 6 chars)

### 4. Blockchain Security

**Permission-Based Access:**
```
Stream: procurement.documents
    - Write: BAC Secretariat, Admin
    - Read: All authenticated blockchain addresses
    - Admin: Admin, BAC Secretariat

Stream: procurement.status
    - Write: BAC Secretariat, Admin
    - Read: All authenticated blockchain addresses
    - Admin: Admin, BAC Secretariat

Stream: procurement.events
    - Write: System (via Jobs)
    - Read: All authenticated blockchain addresses
    - Admin: Admin
```

**Immutability Guarantee:**
- All blockchain writes are permanent
- No delete/modify operations
- Corrections tracked in separate stream
- Full audit trail of all changes

---

## Deployment Scenarios

### Scenario 1: Development Environment (Local)

```
Developer Laptop
├── PHP Built-in Server (127.0.0.1:8000)
├── Vite Dev Server (HMR - Hot Module Replacement)
├── MySQL (Local or Docker - 127.0.0.1:3306)
├── MultiChain Node (Local - 127.0.0.1:4786)
└── Inertia SSR Server (Optional - 127.0.0.1:13714)

External Services:
├── DigitalOcean Spaces (Cloud)
├── Gmail SMTP (Cloud)
└── (No production blockchain nodes)
```

**Network Topology:**
```
┌─────────────────────────────────────┐
│     Developer Laptop (localhost)    │
│                                     │
│  ┌───────────┐   ┌──────────────┐  │
│  │ Browser   │   │ Vite Dev     │  │
│  │ :8000     │◄─►│ Server       │  │
│  └───────────┘   │ (HMR)        │  │
│                  └──────────────┘  │
│                                     │
│  ┌───────────┐   ┌──────────────┐  │
│  │ Laravel   │◄─►│ MySQL        │  │
│  │ Server    │   │ :3306        │  │
│  │ :8000     │   └──────────────┘  │
│  └─────┬─────┘                      │
│        │       ┌──────────────┐     │
│        └──────►│ MultiChain   │     │
│                │ :4786        │     │
│                └──────────────┘     │
└─────────────────────────────────────┘
         │
         │ HTTPS
         ▼
    Cloud Services
    (S3, SMTP)
```

### Scenario 2: Production Environment (Cloud)

```
Cloud Infrastructure
├── Load Balancer (Public IP)
│   └── SSL Termination
├── Web Server (Nginx/Apache)
│   ├── PHP-FPM Pool
│   └── Static Assets
├── Application Server (Laravel)
│   ├── Queue Workers
│   └── Scheduler
├── Database Server (MySQL - Managed)
├── MultiChain Node(s)
│   ├── Primary Node
│   └── Peer Nodes (Optional)
└── File Storage (S3/Spaces - Managed)
```

**Network Topology:**
```
                        Internet
                           │
                           │ HTTPS/443
                           ▼
                  ┌─────────────────┐
                  │ Load Balancer   │
                  │ (SSL Terminate) │
                  └────────┬────────┘
                           │
            ┌──────────────┼──────────────┐
            │              │              │
            ▼              ▼              ▼
    ┌────────────┐ ┌────────────┐ ┌────────────┐
    │ Web Server │ │ Web Server │ │ Web Server │
    │ (Nginx)    │ │ (Nginx)    │ │ (Nginx)    │
    └─────┬──────┘ └─────┬──────┘ └─────┬──────┘
          │              │              │
          └──────────────┼──────────────┘
                         │
                         ▼
              ┌──────────────────┐
              │ Laravel App      │
              │ (PHP-FPM Pool)   │
              └────────┬─────────┘
                       │
        ┌──────────────┼──────────────┬──────────┐
        │              │              │          │
        ▼              ▼              ▼          ▼
   ┌─────────┐  ┌────────────┐  ┌─────────┐  ┌──────────┐
   │ MySQL   │  │ MultiChain │  │ S3/     │  │ SMTP     │
   │ (RDS)   │  │ Node       │  │ Spaces  │  │ Server   │
   └─────────┘  └────────────┘  └─────────┘  └──────────┘
                      │
                      │ P2P
                      ▼
              ┌────────────────┐
              │ MultiChain     │
              │ Peer Nodes     │
              └────────────────┘
```

### Scenario 3: Multi-Region Blockchain Network

```
Region 1 (Primary)          Region 2 (DR/Backup)
┌─────────────────┐         ┌─────────────────┐
│ Laravel App 1   │         │ Laravel App 2   │
│ MultiChain N1   │◄───────►│ MultiChain N2   │
└─────────────────┘  P2P    └─────────────────┘
        │                            │
        │                            │
        ▼                            ▼
    MySQL DB 1                   MySQL DB 2
    (Primary)                    (Read Replica)
```

**Geographic Distribution:**
```
                    Internet
                       │
        ┌──────────────┼──────────────┐
        │              │              │
        ▼              ▼              ▼
    Region 1       Region 2       Region 3
    (Asia)         (Americas)     (Europe)
        │              │              │
        ▼              ▼              ▼
   MultiChain    MultiChain    MultiChain
   Node (SGP)    Node (NYC)    Node (FRA)
        │              │              │
        └──────────────┴──────────────┘
               P2P Mesh Network
        (Consensus & Synchronization)
```

---

## Performance Considerations

### 1. Latency Points

**Client to Web Server:**
- Typical: 10-100ms (depends on geographic distance)
- Optimization: CDN for static assets, load balancing

**Web Server to Database:**
- Local: 1-5ms
- Network: 10-50ms
- Optimization: Connection pooling, query optimization

**Web Server to MultiChain:**
- Local: 5-20ms (RPC call)
- Network: 20-100ms
- Optimization: Retry logic, timeout tuning, async jobs

**Web Server to S3:**
- Upload: 100-500ms (depends on file size)
- Download: 50-200ms
- Optimization: Signed URLs, CDN, multipart uploads

### 2. Throughput Limits

**Web Server:**
- PHP-FPM workers: Configurable (typically 10-50 per server)
- Concurrent requests: ~1000-5000 (with load balancer)

**Database:**
- Connections: MySQL max_connections (typically 150-300)
- Queries/sec: 1000-10000 (depends on query complexity)

**MultiChain:**
- Transactions/block: ~1000
- Block time: ~15 seconds (configurable)
- Stream writes/sec: ~50-100

**Queue Workers:**
- Jobs/worker/min: 5-20
- Concurrent workers: Configurable (typically 3-10)

### 3. Scaling Strategies

**Horizontal Scaling:**
- Add more web servers behind load balancer
- Increase queue worker count
- Add MultiChain peer nodes

**Vertical Scaling:**
- Increase PHP-FPM worker pool
- Upgrade database instance
- Optimize blockchain parameters

**Caching:**
- Database query cache (Laravel cache)
- Static asset caching (Vite + CDN)
- Blockchain data cache (avoid redundant RPC calls)

---

## Monitoring & Health Checks

### Application Health Endpoints

```
GET /health                    # Overall application health
GET /health/database          # Database connectivity
GET /health/blockchain        # MultiChain node status
GET /health/storage           # S3/Spaces connectivity
```

### Metrics to Monitor

**Web Server:**
- Request rate (req/sec)
- Response time (avg, p95, p99)
- Error rate (4xx, 5xx)
- Active connections

**Database:**
- Connection pool usage
- Slow query count
- Lock wait time
- Replication lag (if applicable)

**MultiChain:**
- Block height
- Peer count
- RPC response time
- Stream item count
- Permission grants

**Queue:**
- Jobs pending
- Jobs failed
- Processing time
- Worker status

**Storage:**
- Upload success rate
- Download latency
- Storage usage
- API error rate

---

## Disaster Recovery

### Backup Strategy

**Database Backups:**
- Frequency: Daily (full), hourly (incremental)
- Retention: 30 days
- Location: Off-site S3 bucket

**Blockchain Backups:**
- Frequency: Daily
- Method: Backup `~/.multichain/procuchain/` directory
- Retention: 90 days
- Location: Encrypted off-site storage

**File Storage:**
- Built-in: S3/Spaces versioning enabled
- Lifecycle: Move to glacier after 90 days
- Retention: Indefinite (compliance requirement)

### Recovery Time Objectives (RTO)

| Component        | RTO Target | Recovery Procedure                           |
|------------------|------------|----------------------------------------------|
| Web Application  | 1 hour     | Redeploy from Git, restore .env             |
| Database         | 2 hours    | Restore from latest backup                   |
| Blockchain Node  | 4 hours    | Restore from backup, resync if needed        |
| File Storage     | N/A        | S3 inherently redundant                      |

### Recovery Point Objectives (RPO)

| Component        | RPO Target | Data Loss Tolerance                          |
|------------------|------------|----------------------------------------------|
| Database         | 1 hour     | Acceptable (incremental backups)             |
| Blockchain       | 1 day      | Acceptable (can resync from peers)           |
| File Storage     | 0          | No data loss (S3 99.999999999% durability)   |

---

## Network Diagram Summary

**Simplified End-to-End Flow:**

```
User Desktop (Browser)
    ↓ HTTPS/443
Web Server (Apache/Nginx)
    ↓ HTTP/Internal
Laravel Application (PHP)
    ↓ Multiple Connections:
    ├─→ MySQL Database (3306/TCP)
    ├─→ MultiChain Node (4786/TCP JSON-RPC)
    ├─→ Cloud Storage (443/HTTPS S3 API)
    └─→ SMTP Server (587/TLS)

MultiChain Node
    ↓ P2P Protocol
    ├─→ Peer Node 1 (6271/TCP)
    ├─→ Peer Node 2 (6271/TCP)
    └─→ Peer Node N (6271/TCP)
```

---

## Conclusion

The ProcuChain network topology is designed with security, scalability, and blockchain integrity in mind. The architecture separates concerns across multiple layers:

1. **User Layer:** Modern SPA with progressive enhancement
2. **Web Layer:** Laravel application with role-based access
3. **Data Layer:** MySQL for relational data, S3 for files
4. **Blockchain Layer:** MultiChain for immutable audit trails
5. **Service Layer:** SMTP for notifications, external integrations

Each layer communicates over well-defined protocols with appropriate security measures (TLS, authentication, authorization). The blockchain integration provides tamper-proof document verification while maintaining the flexibility of a traditional web application.

---

**Document Version:** 1.0  
**Last Updated:** October 16, 2025  
**Author:** ProcuChain Development Team  
**Contact:** leodyversemilla07@gmail.com
