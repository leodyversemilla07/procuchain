# ProcuChain

> Blockchain-backed procurement document integrity & workflow automation for BAC offices.

---

## Table of Contents

1. [Overview](#overview)
2. [Features](#features)
3. [Technology Stack](#technology-stack)
4. [Requirements](#requirements)
5. [Architecture Snapshot](#architecture-snapshot)
6. [Installation](#installation)
7. [Configuration](#configuration)
8. [MultiChain Setup](#multichain-setup)
9. [Entry Points](#entry-points)
10. [Running & Development](#running--development)
11. [Scripts](#scripts)
12. [Testing](#testing)
13. [Production Deployment](#production-deployment)
14. [Project Structure](#project-structure)
15. [Security](#security)
16. [Troubleshooting](#troubleshooting)
17. [License](#license)
18. [Contact](#contact)

---

## Overview

ProcuChain is a blockchain-powered document management system for Bids and Awards Committee (BAC) operations. It provides immutable audit trails, controlled access, and automated procurement workflow stages.

## Features

- **Secure Document Management**: Upload, store, and manage procurement documents with blockchain integrity verification
- **Blockchain-based Document Verification**: Immutable audit trails using MultiChain streams
- **Automated Workflow**: Streamlined bids and awards process with stage transitions
- **Real-time Status Tracking**: Live updates on procurement progress and document status
- **Role-based Access Control**: Granular permissions for different user roles (Admin, BAC Secretariat, BAC Chairman, HOPE)
- **Comprehensive Audit Trail**: Complete history of document changes and workflow transitions
- **On-Chain File Storage**: Files stored directly on blockchain with automatic replication across all nodes (Heroku-compatible)
- **Email Notifications**: Automated email alerts for workflow transitions and updates
- **Push Notifications**: Real-time browser notifications using WebPush/VAPID
- **Responsive Interface**: Modern React-based SPA with Inertia.js for seamless user experience

## Technology Stack

- **Backend**: Laravel 12.36.1 with PHP 8.3.27
- **Frontend**: React 19.0.0 with Inertia.js v2.2.15 for SPA experience
- **Database**: MySQL 8.0+ with database-driven sessions, cache, and queue
- **Blockchain**: MultiChain (Community Edition) for immutable document integrity and audit trails
- **File Storage**: On-chain storage directly in blockchain (zero external storage costs, automatic node replication, Heroku-compatible)
- **Styling**: Tailwind CSS v4.1.16 for responsive design
- **UI Components**: Radix UI primitives with shadcn/ui patterns
- **Build Tools**: Vite 7.1.10 for fast frontend asset compilation with HMR
- **Testing**: Pest v4.1.3 for expressive PHP testing
- **Code Quality**: Laravel Pint v1.25.1 for consistent code formatting
- **Authentication**: Laravel Fortify 1.31.2 with two-factor authentication (TOTP)
- **Authorization**: Spatie Laravel Permission 6.21 for role-based access control
- **Notifications**:
    - Resend API for transactional email notifications
    - WebPush browser notifications with VAPID protocol
- **Development**:
    - Hot module replacement with Vite
    - Database-driven development stack
    - Concurrently for multi-process management
    - Optional SSR with Inertia SSR server
    - TypeScript 5.7.2 for type safety
    - ESLint 9.17.0 & Prettier 3.4.2 for code quality

## Requirements

- PHP 8.3 or higher (8.3.27 recommended)
- Composer 2.x
- Node.js 18+ and npm
- MySQL 8.0+ (or MariaDB 10.6+)
- MultiChain 2.3.3+ (Community Edition) accessible via RPC
- SMTP service or Resend API (for email notifications)

## Architecture Snapshot

High level components:

- **Web/API Layer**: Laravel 12 + Inertia React SPA with SSR support
- **Document Storage**: On-chain storage in blockchain (file content stored as hex in `file.data` stream, metadata in `file.metadata` stream)
- **Blockchain Layer**: MultiChain with 6 streams:
  - `procurement.documents` - Document metadata and file hashes
  - `procurement.status` - Procurement status transitions
  - `procurement.events` - Audit event logs
  - `procurement.corrections` - Document correction trail
  - `file.data` - Actual file content storage (hex-encoded)
  - `file.metadata` - File metadata and integrity tracking with SHA-256 hashes
- **Service Layer**: 28 specialized services for business logic isolation
- **Job Queue**: Database-driven async job processing for blockchain operations
- **Roles / Addresses**: Distinct blockchain addresses per functional role (admin, BAC secretariat, BAC chairman, HOPE)
- **Permission Matrix**: Config-driven grants for global & per-stream rights
- **Security**: Multi-factor authentication, account lockout protection, IP blocking, and comprehensive audit logging

## Installation

### Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js and npm
- MySQL database
- MultiChain (installed and configured)
- SMTP email service (for notifications)

```bash
# Clone the repository
git clone https://github.com/leodyversemilla07/procuchain.git
cd procuchain

# Install PHP dependencies
composer install

# Install frontend dependencies
npm install && npm run build

# Configure environment variables
cp .env.example .env
# Edit your .env file with appropriate database and MultiChain settings:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=procuchain
# DB_USERNAME=root
# DB_PASSWORD=

# MULTICHAIN_CHAIN_NAME=procuchain
# MULTICHAIN_HOST=your_multichain_host
# MULTICHAIN_PORT=7000
# MULTICHAIN_USERNAME=multichainrpc
# MULTICHAIN_PASSWORD=your_multichain_password

# Generate application key
php artisan key:generate

# Create and setup the database
php artisan migrate
php artisan db:seed

# Start the development server
composer run dev
```

### MultiChain Setup

```bash
# Install MultiChain (if not already installed)
wget https://www.multichain.com/download/multichain-latest.tar.gz
tar -xvzf multichain-latest.tar.gz
cd multichain-*
mv multichaind multichain-cli multichain-util /usr/local/bin

# Create a new blockchain for ProcuChain
multichain-util create procuchain
multichaind procuchain -daemon
```

#### Application Bootstrap (Artisan Command)

After the node is up, use the built-in Artisan command to generate blockchain addresses, create streams, and grant permissions.

Check MultiChain connection:

```bash
php artisan multichain:setup --check
```

Full setup (generates addresses, creates streams, grants permissions):

```bash
php artisan multichain:setup
```

The setup command performs the following operations:

1. **Connection Check**: Verifies connectivity to the MultiChain node
2. **Address Setup**: Generates new blockchain addresses for roles that don't have them configured, or uses existing configured addresses
3. **Stream Creation**: Creates the following streams if they don't exist:
    - `procurement.documents`
    - `procurement.status`
    - `procurement.events`
    - `procurement.corrections`
4. **Permission Grants**: Assigns appropriate permissions to each role address based on the configuration
5. **Address Persistence**: Updates the `.env` file with newly generated addresses and syncs user records in the database

**Available Options:**

| Option    | Purpose                                                     |
| --------- | ----------------------------------------------------------- |
| `--check` | Only check connection to MultiChain node (no setup actions) |

**Supported Roles:**

The command manages blockchain addresses for these roles:

- `bac_secretariat` → `MULTICHAIN_BAC_SECRETARIAT_ADDRESS`
- `bac_chairman` → `MULTICHAIN_BAC_CHAIRMAN_ADDRESS`
- `hope` → `MULTICHAIN_HOPE_ADDRESS`
- `admin` → `MULTICHAIN_ADMIN_ADDRESS`

**Address Management:**

- If an address is already configured in the `.env` file, it will be reused
- If an address is missing or contains `default_`, a new address will be generated
- New addresses are automatically added to the `.env` file
- User records with matching roles are updated with the new blockchain addresses
- For security, addresses are displayed masked in the output (first 6 + last 6 characters)

**Operational Notes:**

1. Ensure your MultiChain node is running before executing the setup
2. The command will fail if it cannot connect to the MultiChain RPC endpoint
3. Generated addresses are immediately granted the necessary permissions
4. If config is cached, clear it after setup: `php artisan config:clear`
5. Keep your `.env` file secure as it contains the blockchain addresses

## Configuration

Environment variables (core subset):

| Key                                  | Purpose                    | Example                    |
| ------------------------------------ | -------------------------- | -------------------------- |
| `APP_ENV`                            | Environment name           | `local`                    |
| `APP_KEY`                            | Encryption key (generated) | _(generated)_              |
| `APP_DEBUG`                          | Debug mode                 | `true`                     |
| `APP_URL`                            | Application URL            | `http://127.0.0.1:8000`    |
| `DB_CONNECTION`                      | Database driver            | `mysql`                    |
| `DB_HOST` / `DB_PORT`                | DB host/port               | `127.0.0.1` / `3306`       |
| `DB_DATABASE`                        | Database name              | `procuchain`               |
| `DB_USERNAME` / `DB_PASSWORD`        | DB credentials             | `root` / `(empty)`         |
| `MULTICHAIN_HOST`                    | MultiChain RPC host        | `your_multichain_host`     |
| `MULTICHAIN_PORT`                    | MultiChain RPC port        | `7000`                     |
| `MULTICHAIN_CHAIN_NAME`              | Chain name                 | `procuchain`               |
| `MULTICHAIN_USERNAME`                | RPC username               | `multichainrpc`            |
| `MULTICHAIN_PASSWORD`                | RPC password               | `your_multichain_password` |
| `MULTICHAIN_USE_SSL`                 | Use SSL for RPC            | `false`                    |
| `MULTICHAIN_VERIFY_SSL`              | Verify SSL certificates    | `false`                    |
| `MULTICHAIN_CONNECTION_TIMEOUT`      | Connection timeout (sec)   | `30`                       |
| `MULTICHAIN_MAX_RETRIES`             | Max retry attempts         | `3`                        |
| `MULTICHAIN_ADMIN_ADDRESS`           | Admin blockchain address   | _(generated by setup)_     |
| `MULTICHAIN_BAC_SECRETARIAT_ADDRESS` | BAC Secretariat address    | _(generated by setup)_     |
| `MULTICHAIN_BAC_CHAIRMAN_ADDRESS`    | BAC Chairman address       | _(generated by setup)_     |
| `MULTICHAIN_HOPE_ADDRESS`            | HOPE blockchain address    | _(generated by setup)_     |

**Additional Configuration:**

- **File Storage**: Local filesystem with blockchain metadata tracking

    ```bash
    FILESYSTEM_DISK=local_blockchain
    ```

- **Email Configuration**: Resend settings for notifications

    ```bash
    MAIL_MAILER=resend
    RESEND_API_KEY=your_resend_api_key
    MAIL_FROM_ADDRESS=noreply@yourdomain.com
    MAIL_FROM_NAME="${APP_NAME}"
    ```

- **WebPush Notifications**: Browser push notifications (VAPID keys provided)

    ```bash
    VAPID_PUBLIC_KEY="your_vapid_public_key"
    VAPID_PRIVATE_KEY="your_vapid_private_key"
    VAPID_SUBJECT="mailto:admin@procuchain.com"
    ```

- **Error Tracking**: Sentry for real-time error monitoring

    ```bash
    SENTRY_LARAVEL_DSN=your_sentry_dsn
    SENTRY_TRACES_SAMPLE_RATE=1.0  # Performance monitoring (0.0 to 1.0)
    SENTRY_ENABLE_LOGS=false        # Enable log capture
    SENTRY_SEND_DEFAULT_PII=false   # Send user data (be careful with privacy)
    ```

- **Queue**: Database-driven queue system
- **Cache**: Database-driven cache system
- **Session**: Database-driven session storage

After running `php artisan multichain:setup`, the role addresses will be automatically generated and added to your `.env` file. Keep this file secure as it contains the blockchain addresses and sensitive credentials.

## Entry Points

- Frontend CSS: resources/css/app.css
- Frontend App (SPA): resources/js/app.tsx
- Server-Side Rendering (SSR): resources/js/ssr.tsx
- Public web root: public/
- Laravel entry (HTTP): public/index.php via web server (Apache/Nginx) or php artisan serve

Vite is configured in vite.config.ts with laravel-vite-plugin to handle both client and SSR builds.

## Testing

The project uses [Pest](https://pestphp.com/) for expressive tests.

Run all tests:

```bash
php artisan test
```

Filter by name:

```bash
php artisan test --filter=MultichainSetup
```

Run a single file:

```bash
php artisan test tests/Feature/SomeFeatureTest.php
```

## Running & Development

Development watcher (PHP + Vite):

```bash
composer run dev
```

Rebuild frontend assets:

```bash
npm run build
```

Format code (Laravel Pint):

```bash
vendor/bin/pint
```

## Scripts

Composer scripts:
- composer run dev — Runs PHP server, queue listener, and Vite dev server concurrently.
- composer run dev:ssr — Builds SSR and starts PHP server, queue listener, logs (pail), and Inertia SSR server.

NPM scripts:
- npm run dev — Start Vite dev server.
- npm run build — Build client and SSR bundles.
- npm run build:ssr — Build SSR (also builds client).
- npm run ssr — Run the SSR entry directly with Node (resources/js/ssr.tsx).
- npm run types — Type-check TypeScript.
- npm run lint — Lint with ESLint (auto-fix).
- npm run format — Format with Prettier.
- npm run format:check — Check formatting with Prettier.

Procfile (for platforms like Heroku):
- web: php artisan inertia:start-ssr & heroku-php-apache2 public/
- worker: php artisan queue:work --sleep=3 --tries=3 --max-time=3600

Docker (database only):
- docker-compose up -d — Starts MySQL 8.4 and phpMyAdmin (mapped to ports 3307 and 8081).

## Production Deployment

1. Install dependencies (`composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`).
2. Optimize Laravel (`php artisan config:cache && php artisan route:cache && php artisan view:cache`).
3. Boot MultiChain node & ensure RPC reachable.
4. Check connection to MultiChain node:
    ```bash
    php artisan multichain:setup --check
    ```
5. Execute setup to create addresses, streams, and permissions:
    ```bash
    php artisan multichain:setup
    ```
6. Store generated addresses securely from `.env` file; optionally re-cache config.
7. Health check (app endpoint + verify MultiChain integration).

For subsequent deployments, re-run the setup command to ensure streams exist and permissions are properly granted.

## Troubleshooting

| Symptom                                   | Cause                                          | Fix                                                       |
| ----------------------------------------- | ---------------------------------------------- | --------------------------------------------------------- |
| Setup command fails with connection error | MultiChain node not running or RPC unreachable | Check node status; verify RPC credentials in `.env`       |
| Permission grant failures                 | Node RPC issue or invalid configuration        | Check MultiChain logs; verify permission matrix in config |
| Streams already exist but no data         | Missing subscription or permissions            | Re-run setup command to ensure proper permissions         |
| Addresses not updating in application     | Config cache stale                             | `php artisan config:clear`                                |
| `.env` file not updated                   | File permissions issue                         | Check file is writable                                    |
| Connection check passes but setup fails   | Permission or stream creation issues           | Check MultiChain node logs for detailed error messages    |

## Usage

### Getting Started
1. Access the system through your web browser at the configured `APP_URL`
2. Login with authorized credentials (default seeded users or created by admin)
3. Complete two-factor authentication setup (recommended for all users)

### Role-Specific Workflows

#### Admin Users
- Manage user accounts and assign roles
- Monitor login activity and security logs
- View blockchain explorer for transaction verification
- Manage IP blocking and account lockouts
- Access comprehensive system analytics

#### BAC Secretariat
- Create new procurement projects
- Upload documents for all 15 procurement stages
- Publish documents to blockchain for immutability
- Manage workflow stage transitions
- Monitor procurement progress and blockchain publishing status

#### BAC Chairman
- Review and approve procurement documents
- Monitor procurement workflows
- View blockchain-verified document integrity
- Access audit trails for transparency

#### HOPE (Head of Procuring Entity)
- Executive oversight of all procurements
- Monitor procurement progress and status
- Review blockchain-verified audit trails
- Access high-level procurement analytics

### Document Management
- **Upload**: Securely upload documents to cloud storage (S3-compatible)
- **Blockchain Publishing**: Documents automatically published to blockchain with SHA-256 hash
- **Verification**: Verify document integrity by comparing blockchain hash with current file
- **Corrections**: Track document corrections with immutable correction trail
- **Viewing**: View documents in secure PDF viewer with access logging
- **Download**: Secure file downloads with authentication

### Workflow Stages (15 Stages)
1. Procurement Initiation
2. Pre-Procurement Conference
3. Bidding Documents
4. Pre-Bid Conference
5. Supplemental Bid Bulletin
6. Bid Opening
7. Bid Evaluation
8. Post-Qualification
9. BAC Resolution
10. Notice of Award
11. Performance Bond, Contract and PO
12. Notice to Proceed
13. Monitoring
14. Completion
15. Completed

Each stage requires specific documents and follows defined status transitions (22 statuses total).

### Blockchain Features
- **Immutable Records**: All document metadata and status changes recorded on blockchain
- **Transaction Verification**: View transaction IDs (txid) for each blockchain operation
- **Correction Tracking**: Document corrections tracked without deleting originals
- **Audit Trail**: Complete history of all procurement activities
- **Explorer**: Built-in blockchain explorer for transaction lookup and verification

## Project Structure

Project root snapshot:

```
procuchain/
├── app/                        # Laravel application code
│   ├── Console/Commands/       # Artisan commands (MultichainSetup, etc.)
│   ├── Contracts/             # Interface definitions
│   ├── Enums/                 # Business logic enums (StageEnums, StatusEnums, StreamEnums)
│   ├── Http/
│   │   ├── Controllers/       # Request handlers (Admin, BAC, HOPE, Auth, Settings)
│   │   ├── Middleware/        # Auth, RBAC, rate limiting
│   │   └── Requests/          # Form validation rules
│   ├── Jobs/                  # Queue jobs (6 blockchain/document jobs)
│   ├── Libraries/             # MultichainClient (JSON-RPC)
│   ├── Mail/                  # Email notification classes
│   ├── Models/                # Eloquent ORM models (6 models)
│   ├── Notifications/         # Email and push notifications
│   ├── Policies/              # Authorization policies
│   ├── Providers/             # Service providers
│   └── Services/              # Business logic layer (28 specialized services)
├── bootstrap/                 # Framework bootstrap files
├── config/                    # Application configuration
│   └── multichain.php         # MultiChain blockchain configuration
├── database/                  # Migrations, seeders, factories
│   ├── factories/             # Model factories for testing
│   ├── migrations/            # Database schema (20+ tables)
│   └── seeders/               # Database seeders
├── docs/                      # Project documentation
│   ├── stages.md              # 15 procurement stages documentation
│   ├── network-topology.md    # Network architecture diagram
│   └── workflows/             # Workflow documentation
├── public/                    # Web root (entry point, built assets)
├── resources/
│   ├── css/app.css            # Main stylesheet (Tailwind CSS)
│   ├── js/
│   │   ├── app.tsx            # React SPA entry (Inertia client)
│   │   ├── ssr.tsx            # SSR entry for Inertia
│   │   ├── components/        # Reusable React components
│   │   ├── layouts/           # Page layouts (Auth, Dashboard, etc.)
│   │   ├── pages/             # Inertia page components
│   │   │   ├── admin/         # Admin dashboard pages
│   │   │   ├── bac-secretariat/ # BAC Secretariat pages
│   │   │   ├── bac-chairman/  # BAC Chairman pages
│   │   │   ├── hope/          # HOPE pages
│   │   │   ├── auth/          # Authentication pages
│   │   │   ├── settings/      # User settings pages
│   │   │   └── procurements/  # Procurement management pages
│   │   ├── hooks/             # Custom React hooks
│   │   ├── lib/               # Utility functions
│   │   └── types/             # TypeScript definitions
│   └── views/                 # Blade templates (minimal, mostly for emails)
├── routes/                    # Route definitions (141 routes total)
│   ├── web.php                # Main web routes
│   ├── auth.php               # Authentication routes
│   ├── settings.php           # User settings routes
│   ├── file-uploads-ui-preview.php # File upload preview routes
│   └── console.php            # Console routes
├── scripts/                   # Installation and setup scripts
│   ├── install_procuchain.sh  # MultiChain installation script
│   └── join_procuchain.sh     # Join existing blockchain network
├── tests/                     # Pest/PHPUnit tests
│   ├── Feature/               # Feature tests
│   ├── Unit/                  # Unit tests
│   └── Pest.php               # Pest configuration
├── storage/                   # Application storage
├── vendor/                    # Composer dependencies
├── node_modules/              # Node dependencies
├── .env.example               # Environment variables template
├── boost.json                 # Laravel Boost configuration
├── components.json            # shadcn/ui configuration
├── composer.json              # PHP dependencies and scripts
├── package.json               # Frontend dependencies and scripts
├── vite.config.ts             # Vite configuration (client + SSR)
├── tsconfig.json              # TypeScript configuration
├── eslint.config.js           # ESLint configuration
├── docker-compose.yml         # Local DB and phpMyAdmin services
└── Procfile                   # Process definitions for Heroku deployment
```

### Key Directories Explained

**Services (28 total):**
- Blockchain: MultichainService, MultichainConnectionService, BlockchainOrchestratorService, BlockchainEventLoggerService, BlockchainHealthService, BlockchainMonitoringService, BlockchainCorrectionService, DocumentBlockchainService
- Document: DocumentUploadService, DocumentMetadataService, FileStorageService
- Procurement: ProcurementPublishingService, ProcurementStageTransitionService, ProcurementDataService, StatusUpdaterService
- Security: LoginService, AccountLockoutService, BlockedIpService, DeviceDetectionService
- Analytics: DashboardService, AdminAnalyticsService, LoginAnalyticsService
- Utilities: CacheStrategyService, EventTypeLabelMapper, StreamKeyService, NotificationService, DashboardCacheKeys

**Models (6 core models):**
- User (with roles, 2FA, account lockout)
- Procurement (with blockchain integration)
- ProcurementDocument (with S3 storage and blockchain anchors)
- UserLoginLog (comprehensive audit trail)
- BlockedIp (IP blocking system)
- DocumentView (document access tracking)

**Jobs (6 background jobs):**
- PublishProcurementDocumentsJob
- UpdateProcurementStatusJob
- LogBlockchainEventJob
- PublishDocumentCorrectionJob
- HandleStageTransitionJob
- DocumentValidationJob

## Security

### Authentication & Authorization
- **Multi-factor Authentication (2FA)**: Time-based OTP (TOTP) via Google2FA with encrypted recovery codes
- **Session Management**: Database-driven sessions with secure token handling
- **Password Security**: Bcrypt hashing with 12 rounds
- **CSRF Protection**: Laravel token validation on all state-changing requests
- **Rate Limiting**: Throttling on login and sensitive API endpoints

### Account Protection
- **Account Lockout**: Automatic lockout after configurable failed login attempts (default: 5 attempts)
- **Lockout Duration**: Configurable temporary lock (default: 30 minutes) with auto-unlock
- **IP Blocking**: Manual and automatic IP blocking system with expiration support
- **Login Audit Trail**: Comprehensive logging with device detection (browser, OS, platform)
- **Email Notifications**: Security alerts for account events (lockout, unlock, suspicious activity)

### Data Security
- **Encryption at Rest**: Database encryption (configurable)
- **Encryption in Transit**: TLS 1.2+ for all external connections
- **File Integrity Verification**: SHA-256 hashes stored on blockchain for tamper detection
- **Blockchain Addresses**: Masked display (first 6 + last 6 characters) in UI
- **Sensitive Data Protection**: Passwords and secrets never stored in blockchain
- **Environment Variables**: Secure credential storage in `.env` file

### Access Control
- **Role-Based Access Control (RBAC)**: Spatie Laravel Permission with 4 primary roles:
  - Admin: Full system access and user management
  - BAC Secretariat: Document management and workflow coordination
  - BAC Chairman: Approval and oversight functions
  - HOPE: Executive oversight and monitoring
- **Permission Matrix**: Granular permissions for blockchain operations
- **Principle of Least Privilege**: Minimal required permissions per role
- **Middleware Protection**: Route-level authorization checks

### Blockchain Security
- **Immutable Audit Trail**: All blockchain writes are permanent via MultiChain streams
- **Permission-Based Access**: Blockchain-level permissions for write/read/admin operations
- **Address Isolation**: Distinct addresses per role for accountability
- **Correction Tracking**: Separate correction stream maintains immutable correction history
- **No Data Deletion**: Document corrections tracked without deleting original records

### Best Practices
- Environment isolation: Never reuse production addresses in non-production environments
- Regular backups of `.env` file and MultiChain configuration (blockchain data backed up automatically by MultiChain nodes)
- Proper MultiChain node security with RPC authentication
- Secure API key storage for external services (email, push notifications)
- Database backups with encryption
- Security monitoring through login logs and audit trails
- **Note**: Files are stored on-chain, so no separate file backup needed - just ensure blockchain nodes are properly backed up

## Performance & Optimization

### Caching Strategies
- **Database Caching**: Query result caching with cache invalidation
- **Dashboard Caching**: Role-specific dashboard data caching
- **Config Caching**: Production config compilation (`php artisan config:cache`)
- **Route Caching**: Compiled route list (`php artisan route:cache`)
- **View Caching**: Compiled Blade templates (`php artisan view:cache`)

### Frontend Optimizations
- **Vite Code Splitting**: Automatic chunk splitting for optimal loading
- **Lazy Loading**: Component-level code splitting
- **Asset Optimization**: Minification and tree-shaking
- **HMR**: Hot Module Replacement for fast development
- **SSR Support**: Optional server-side rendering for improved SEO

### Database Optimizations
- **Strategic Indexes**: Indexes on frequently queried columns (blockchain_status, dates, foreign keys)
- **Eager Loading**: Prevent N+1 query problems with relationship loading
- **Connection Pooling**: Persistent database connections
- **Query Builder**: Efficient query construction with Eloquent ORM

### Blockchain Optimizations
- **Async Publishing**: Background job processing for blockchain operations
- **Retry Logic**: Exponential backoff for failed blockchain operations
- **Circuit Breaker**: Prevent cascade failures with health monitoring
- **Timeout Management**: Separate timeouts for web (12s) vs console (30s) operations

## Monitoring & Logging

### Error Tracking with Sentry
ProcuChain uses [Sentry](https://sentry.io) for real-time error tracking and performance monitoring.

**Configuration:**
```bash
# Already configured in .env
SENTRY_LARAVEL_DSN=your_sentry_dsn
SENTRY_TRACES_SAMPLE_RATE=1.0  # 100% transaction sampling for performance monitoring
```

**Features:**
- **Real-time Error Tracking**: Automatic error capture and reporting
- **Performance Monitoring**: Track slow database queries, API calls, and HTTP requests
- **Release Tracking**: Monitor errors by deployment version
- **User Context**: Track which users experience errors (when send_default_pii is enabled)
- **Environment Separation**: Separate error tracking for local, staging, and production

**Testing Sentry:**
```bash
# Send a test event to verify configuration
php artisan sentry:test
```

**Sentry Dashboard:**
Visit [sentry.io/organizations/your-org/issues](https://sentry.io) to view:
- Real-time error alerts
- Performance metrics and slow queries
- User impact analysis
- Error frequency and trends

**Best Practices:**
- Review Sentry issues daily in production
- Set up alert rules for critical errors
- Use releases to track error trends after deployments
- Configure issue assignment rules for your team

### Application Logs
- **Laravel Logs**: `storage/logs/laravel.log` for application errors and events
- **Laravel Pail**: Real-time log viewing with `php artisan pail`
- **Laravel Boost**: `browser-logs` tool for frontend error tracking
- **Sentry Logs**: Real-time error tracking with stack traces and context

### Audit Trails
- **User Login Logs**: Comprehensive tracking with device detection and IP logging
- **Document Views**: Track who viewed documents, when, and for how long
- **Blockchain Events**: All blockchain operations logged to audit stream
- **Status Changes**: Complete history of procurement status transitions

### Health Monitoring
- **Sentry Performance**: Database query performance, HTTP request timing
- **MultiChain Node**: Connection status and RPC health checks
- **Database**: Connection monitoring and query performance
- **File Storage**: On-chain storage verification with SHA-256 integrity checks
- **Queue**: Failed job tracking and retry monitoring

## API & Integration

### Available Tools (Laravel Boost MCP)
- `application-info`: Get PHP version, Laravel version, installed packages
- `database-schema`: Read complete database schema with relationships
- `database-query`: Execute read-only SQL queries
- `list-routes`: View all application routes with filters
- `list-artisan-commands`: List available Artisan commands
- `tinker`: Execute PHP code in Laravel context
- `search-docs`: Search Laravel ecosystem documentation
- `browser-logs`: Read frontend error logs
- `last-error`: Get details of last backend error

### External Services
- **Resend API**: Transactional email delivery
- **WebPush/VAPID**: Browser push notifications
- **MultiChain RPC**: Blockchain operations via JSON-RPC 2.0

## Development Guidelines

### Code Quality Standards
- **PHP**: Laravel Pint with PSR-12 compliance
- **JavaScript/TypeScript**: ESLint 9 with React plugins, Prettier formatting
- **Type Safety**: TypeScript 5.7.2 for frontend type checking
- **Documentation**: PHPDoc blocks for all public methods

### Testing
- **Framework**: Pest 3.8.4 for expressive testing
- **Types**: Feature tests and unit tests
- **Factories**: Model factories for test data generation
- **Coverage**: Test all happy paths, failure paths, and edge cases

### Git Workflow
- **Branch**: `main` (protected branch)
- **Repository**: `leodyversemilla07/procuchain`
- **Commits**: Descriptive commit messages
- **PR Reviews**: Code review before merging

## Database Schema Overview

### Core Tables (22 total)
- **users** (19 columns): User accounts with blockchain addresses, 2FA, account lockout
- **procurements** (13 columns): Core procurement records with blockchain integration
- **procurement_documents** (21 columns): Document metadata with blockchain anchors including `data_txid` and `metadata_txid` for on-chain file retrieval
- **user_login_logs** (13 columns): Comprehensive login audit trail
- **document_views** (13 columns): Document access tracking
- **blocked_ips** (8 columns): IP blocking system
- **permissions** & **roles** & **model_has_permissions** & **model_has_roles** & **role_has_permissions**: Spatie permission system (5 tables)
- **notifications**: In-app notification storage
- **push_subscriptions**: WebPush subscription management
- **sessions**: Database-driven session storage
- **cache** & **cache_locks**: Database-driven cache system (2 tables)
- **jobs** & **failed_jobs** & **job_batches**: Queue system (3 tables)
- **password_reset_tokens**: Password reset functionality
- **migrations**: Database migration tracking

### Blockchain Integration Fields
- `blockchain_txid`: Transaction ID from MultiChain for documents stream
- `data_txid`: Transaction ID for file content on file.data stream (critical for file retrieval)
- `metadata_txid`: Transaction ID for file metadata on file.metadata stream
- `blockchain_status`: Enum (pending, confirmed, failed)
- `blockchain_status_updated_at`: Timestamp of last status update
- `blockchain_error`: Error message if operation failed
- `blockchain_retry_count`: Number of retry attempts
- Document corrections tracked separately with full audit trail

## Known Issues & Future Enhancements

### Planned Enhancements
- API documentation with OpenAPI/Swagger
- Automated backup scheduling for blockchain and database
- Application Performance Monitoring (APM) integration
- Load testing benchmarks for high-volume scenarios
- Full-stack Docker Compose environment
- CI/CD pipeline automation
- Feature flag system for gradual rollouts
- Real-time analytics dashboard
- Multi-node blockchain setup documentation

### Contributing
Contributions are welcome! Please:
1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Run tests and code quality checks
4. Commit your changes with descriptive messages
5. Push to the branch
6. Open a Pull Request

## License

MIT License

Copyright (c) 2025 Leodyver Semilla

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

## Contact & Support

**Project Maintainer:** Leodyver Semilla  
**Email:** [leodyversemilla07@gmail.com](mailto:leodyversemilla07@gmail.com)  
**Repository:** [github.com/leodyversemilla07/procuchain](https://github.com/leodyversemilla07/procuchain)

For bug reports, feature requests, or general inquiries, please open an issue on GitHub or contact the maintainer directly.

---

**Built with ❤️ for transparent and accountable public procurement in the Philippines**
