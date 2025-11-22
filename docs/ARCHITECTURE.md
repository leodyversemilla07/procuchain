# ProcuChain Architecture Documentation

**Document Version:** 1.0  
**Last Updated:** November 15, 2025  
**Application Version:** Laravel 12.38.1

---

## Table of Contents

1. [System Overview](#system-overview)
2. [Technology Stack](#technology-stack)
3. [Application Architecture](#application-architecture)
4. [Database Architecture](#database-architecture)
5. [Blockchain Integration](#blockchain-integration)
6. [Service Layer Architecture](#service-layer-architecture)
7. [Frontend Architecture](#frontend-architecture)
8. [Security Architecture](#security-architecture)
9. [API & Routes](#api--routes)
10. [Queue & Background Jobs](#queue--background-jobs)
11. [Testing Strategy](#testing-strategy)
12. [Deployment Architecture](#deployment-architecture)

---

## System Overview

ProcuChain is a blockchain-backed procurement document management system designed for Bids and Awards Committee (BAC) operations in the Philippines. The system provides immutable audit trails, controlled access, and automated procurement workflow stages following RA 9184 (Government Procurement Reform Act).

### Core Features

- **Blockchain-Backed Document Integrity**: All documents stored on-chain with SHA-256 hash verification
- **15-Stage Procurement Workflow**: Complete government procurement lifecycle management
- **Role-Based Access Control**: 4 primary roles with granular permissions
- **Multi-Factor Authentication**: TOTP-based 2FA with recovery codes
- **Real-Time Notifications**: Email and browser push notifications
- **Comprehensive Audit Trail**: Immutable blockchain records of all activities
- **Document Correction Tracking**: Non-destructive correction history
- **Advanced Security**: Account lockout, IP blocking, device detection

### System Constraints

- **On-Chain Storage**: All files stored directly on blockchain (no external file storage)
- **MultiChain Community Edition**: Permissioned blockchain (not public)
- **MySQL Database**: Primary data store with blockchain references
- **No Node.js Backend**: Pure Laravel with Inertia.js SSR
- **Queue-Based Publishing**: Async blockchain operations to prevent timeout

---

## Technology Stack

### Backend Stack

| Technology | Version | Purpose |
|------------|---------|---------|
| PHP | 8.3.27 | Server-side runtime |
| Laravel | 12.38.1 | Web application framework |
| MySQL | 8.0+ | Relational database |
| MultiChain | 2.3.3+ | Permissioned blockchain |
| Composer | 2.x | PHP dependency management |

### Frontend Stack

| Technology | Version | Purpose |
|------------|---------|---------|
| React | 19.2.0 | UI framework |
| Inertia.js | 2.2.16 | SPA framework (Laravel ↔ React bridge) |
| TypeScript | Latest | Type-safe JavaScript |
| Tailwind CSS | 4.1.17 | Utility-first CSS framework |
| Vite | 7.1.10 | Build tool with HMR |
| shadcn/ui | Latest | Component library (Radix UI) |

### Key Laravel Packages

| Package | Version | Purpose |
|---------|---------|---------|
| laravel/fortify | 1.31.3 | Authentication (including 2FA) |
| laravel/wayfinder | 0.1.12 | Type-safe route generation for TypeScript |
| spatie/laravel-permission | 6.21 | Role & permission management |
| pestphp/pest | 4.1.3 | Testing framework |
| laravel/pint | 1.25.1 | Code formatter |
| laravel/mcp | 0.3.3 | Model Context Protocol server |
| sentry/sentry-laravel | Latest | Error tracking & monitoring |

### Infrastructure

| Service | Purpose |
|---------|---------|
| Resend API | Transactional emails |
| WebPush/VAPID | Browser push notifications |
| Sentry | Real-time error tracking |
| Docker | Local MySQL development |
| Heroku | Production deployment (optional) |

---

## Application Architecture

### Architecture Pattern

ProcuChain follows a **Layered Architecture** with clear separation of concerns:

```
┌─────────────────────────────────────────────────────────────┐
│                      Presentation Layer                      │
│  (Inertia Pages, React Components, Layouts)                 │
└──────────────────────────┬──────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────┐
│                      Controller Layer                        │
│  (HTTP Controllers, Form Requests, Middleware)              │
└──────────────────────────┬──────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────┐
│                       Service Layer                          │
│  (Business Logic, Publishers, Orchestrators)                │
└──────────────────────────┬──────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────┐
│                       Data Layer                             │
│  (Eloquent Models, Repositories, DTOs)                      │
└──────────────────────────┬──────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────┐
│                    Infrastructure Layer                      │
│  (MultiChain Client, Database, Queue, Cache, Storage)       │
└─────────────────────────────────────────────────────────────┘
```

### Directory Structure

```
app/
├── Console/Commands/          # Artisan commands (6 commands)
│   ├── MultichainSetup.php    # Blockchain initialization
│   ├── PermissionStatus.php   # Permission diagnostics
│   ├── InitializeBlockchainStorage.php
│   ├── ReconcileBlockchainStatus.php
│   ├── CleanupDatabaseCache.php
│   └── SmartContractSetup.php
├── Contracts/                 # Interface definitions
├── DataTransferObjects/       # DTOs for data transfer
├── Enums/                     # Business logic enums
│   ├── StageEnums.php         # 15 procurement stages
│   ├── StatusEnums.php        # 26 procurement statuses
│   ├── StreamEnums.php        # 8 blockchain streams
│   ├── DocumentTypeEnums.php
│   ├── UserRoleEnums.php
│   └── ProcurementModeEnums.php
├── Http/
│   ├── Controllers/           # Request handlers (18+ controllers)
│   │   ├── Admin/
│   │   ├── Auth/
│   │   ├── Procurement/
│   │   └── Settings/
│   ├── Middleware/            # HTTP middleware
│   └── Requests/              # Form validation
├── Libraries/
│   └── MultiChain/            # MultiChain JSON-RPC client
│       ├── Client.php         # Core RPC communication
│       ├── Manager.php        # Connection management
│       └── README.md
├── Mail/                      # Email notification classes
├── Models/                    # Eloquent ORM models (4 primary models)
│   ├── User.php
│   ├── UserLoginLog.php
│   ├── BlockedIp.php
│   └── DocumentView.php
├── Notifications/             # Notification classes
├── Policies/                  # Authorization policies
├── Providers/                 # Service providers
├── Repositories/              # Data access layer
├── Services/                  # Business logic (20+ services)
│   ├── Publishers/            # Blockchain publishers
│   ├── Blockchain/            # Blockchain utilities
│   └── [Various Services]
└── [Other directories]

resources/
├── css/
│   └── app.css               # Tailwind CSS entry point
└── js/
    ├── app.tsx               # React client-side entry
    ├── ssr.tsx               # Server-side rendering entry
    ├── components/           # Reusable React components (60+ components)
    ├── layouts/              # Page layouts
    ├── pages/                # Inertia page components
    │   ├── admin/
    │   ├── bac-secretariat/
    │   ├── bac-chairman/
    │   ├── hope/
    │   ├── auth/
    │   ├── settings/
    │   └── procurements/
    ├── hooks/                # Custom React hooks
    ├── lib/                  # Utility functions
    ├── types/                # TypeScript definitions
    └── utils/                # Helper functions
```

### Key Architectural Decisions

1. **Inertia.js over Traditional API**: Eliminates need for separate frontend API, provides seamless SPA experience with Laravel backend
2. **Service Layer Pattern**: Business logic isolated in service classes, not in controllers
3. **Publisher Pattern**: Blockchain operations handled by specialized publisher services
4. **Enum-Driven Logic**: All workflow stages and statuses defined as enums with business logic
5. **Queue-Based Blockchain Writes**: Prevents HTTP timeout on blockchain operations
6. **On-Chain File Storage**: Files stored directly in blockchain (hex-encoded), no external storage needed

---

## Database Architecture

### Database Schema Overview

**Total Tables**: 21  
**Database Engine**: MySQL 8.0+  
**Character Set**: utf8mb4  
**Collation**: utf8mb4_unicode_ci

### Core Tables

#### 1. `users` (Authentication & User Management)

Primary user table with authentication and security features.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | Primary key |
| name | varchar | User's full name |
| email | varchar UNIQUE | User email (login) |
| blockchain_address | varchar | User's blockchain address |
| password | varchar | Bcrypt hashed password |
| account_locked | tinyint | Account lock status (0/1) |
| locked_at | timestamp | When account was locked |
| lock_expires_at | timestamp | When lock expires (auto-unlock) |
| failed_login_attempts | int | Failed login counter |
| last_failed_login_at | timestamp | Last failed login time |
| locked_reason | varchar | Why account was locked |
| two_factor_secret | text | TOTP secret (encrypted) |
| two_factor_recovery_codes | text | Recovery codes (encrypted) |
| two_factor_confirmed_at | timestamp | When 2FA was enabled |
| email_notifications_enabled | tinyint | Email notification preference |

**Relationships:**
- Has many: `user_login_logs`, `document_views`, `blocked_ips`
- Has many roles (via `model_has_roles`)

#### 2. `procurements` (Procurement Management)

Stores procurement project data with blockchain integration.

| Column | Type | Description |
|--------|------|-------------|
| id | varchar PK | Procurement ID (PR number) |
| title | varchar | Procurement title |
| stage | varchar | Current stage (enum) |
| current_status | varchar | Current status (enum) |
| user_address | varchar | Blockchain address |
| document_count | int | Number of documents |
| last_updated | timestamp | Last activity timestamp |
| blockchain_txid | varchar | Transaction ID |
| blockchain_status | enum | pending/published/failed |
| blockchain_status_updated_at | timestamp | Status change time |
| blockchain_error | text | Error message if failed |
| blockchain_retry_count | tinyint | Retry attempts |

**Indexes:**
- Primary: `id`
- Index: `stage`, `current_status`, `last_updated`, `blockchain_status`

**Note:** Procurement documents are stored directly in blockchain streams for single source of truth. No database storage is used for document metadata.

#### 3. `user_login_logs` (Security Audit Trail)

Comprehensive login activity logging with device detection.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | Primary key |
| user_id | bigint FK | User reference |
| ip_address | varchar | Client IP |
| user_agent | text | Browser user agent |
| device_type | varchar | mobile/desktop/tablet/unknown |
| browser | varchar | Browser name |
| platform | varchar | OS name |
| location | varchar | Geographic location (if available) |
| successful | tinyint | Login success (1) or failure (0) |
| login_at | timestamp | Login timestamp |
| logout_at | timestamp | Logout timestamp |

**Indexes:**
- Primary: `id`
- Foreign: `user_id` → `users.id` (cascade delete)
- Index: `login_at`, `ip_address`
- Composite: `(user_id, login_at)`

**Security Features:**
- Failed login tracking for account lockout
- Device fingerprinting for suspicious activity detection
- IP-based login pattern analysis

#### 5. `blocked_ips` (IP Blocking System)

Manages blocked IP addresses for security.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | Primary key |
| ip_address | varchar UNIQUE | Blocked IP address |
| blocked_by | bigint FK | Admin who blocked |
| reason | varchar | Block reason |
| expires_at | timestamp | Expiration time (null = permanent) |
| is_active | tinyint | Active status |

**Indexes:**
- Primary: `id`
- Unique: `ip_address`
- Foreign: `blocked_by` → `users.id` (set null on delete)
- Index: `is_active`, `expires_at`

#### 6. `document_views` (Document Access Tracking)

Tracks who viewed documents for audit purposes.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | Primary key |
| user_id | bigint FK | User who viewed |
| file_key | varchar | Document identifier |
| procurement_id | varchar | Procurement reference |
| procurement_title | varchar | Procurement title |
| document_type | varchar | Document type |
| stage | varchar | Stage when viewed |
| ip_address | varchar | Client IP |
| user_agent | varchar | Browser info |
| view_duration | int | Time spent viewing (seconds) |
| metadata | json | Additional tracking data |
| viewed_at | timestamp | View timestamp |

**Indexes:**
- Primary: `id`
- Foreign: `user_id` → `users.id` (cascade delete)
- Index: `viewed_at`
- Composite: `(user_id, file_key)`, `(file_key, viewed_at)`, `(procurement_id, viewed_at)`

### Permission Tables (Spatie Laravel Permission)

#### 7. `roles`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | Primary key |
| name | varchar | Role name |
| guard_name | varchar | Guard (web) |

**Unique:** `(name, guard_name)`

**Seeded Roles:**
- `admin` - Full system access
- `bac_secretariat` - BAC Secretary operations
- `bac_chairman` - BAC Chairman oversight
- `hope` - Head of Procuring Entity

#### 8. `permissions`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | Primary key |
| name | varchar | Permission name |
| guard_name | varchar | Guard (web) |

**Unique:** `(name, guard_name)`

#### 9. `model_has_roles` (User ↔ Role Pivot)

Links users to roles (many-to-many).

| Column | Type | Description |
|--------|------|-------------|
| role_id | bigint FK | Role reference |
| model_type | varchar | Polymorphic type (User) |
| model_id | bigint | User ID |

**Composite Primary:** `(role_id, model_id, model_type)`

### Laravel System Tables

#### 10. `cache` / `cache_locks`
Database-backed cache storage.

#### 11. `sessions`
Database-driven session storage.

| Column | Type | Description |
|--------|------|-------------|
| id | varchar PK | Session ID |
| user_id | bigint | User reference (nullable) |
| ip_address | varchar | Client IP |
| user_agent | text | Browser info |
| payload | longtext | Session data |
| last_activity | int | Unix timestamp |

#### 12. `jobs` / `failed_jobs` / `job_batches`
Queue system tables for async job processing.

#### 13. `notifications`
Database notifications (polymorphic).

#### 14. `push_subscriptions`
WebPush notification subscriptions.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | Primary key |
| subscribable_type | varchar | Polymorphic type |
| subscribable_id | bigint | User ID |
| endpoint | varchar UNIQUE | Push endpoint URL |
| public_key | varchar | VAPID public key |
| auth_token | varchar | Auth token |
| content_encoding | varchar | Encoding (aes128gcm) |

### Database Performance Optimizations

1. **Strategic Indexes:**
   - Composite indexes on frequently queried columns
   - `blockchain_status` indexed for queue processing
   - Timestamp indexes for date-range queries

2. **Foreign Key Constraints:**
   - Cascade deletes for dependent records
   - Set null for optional relationships

3. **Connection Pooling:**
   - Persistent connections enabled
   - Max connections: 100 (configurable)

4. **Query Optimization:**
   - Eager loading prevents N+1 queries
   - Chunked queries for large datasets

---

## Blockchain Integration

### MultiChain Overview

**Blockchain Platform**: MultiChain Community Edition 2.3.3+  
**Consensus**: Round-robin mining (permissioned)  
**Network Type**: Private permissioned blockchain  
**RPC Protocol**: JSON-RPC 2.0

### Blockchain Streams

ProcuChain uses **8 MultiChain streams** to organize data:

| Stream | Purpose | Data Type |
|--------|---------|-----------|
| `procurement.metadata` | Core procurement metadata | JSON |
| `procurement.documents` | Document metadata & hashes | JSON |
| `procurement.status` | Status change history | JSON |
| `procurement.events` | Workflow events | JSON |
| `procurement.corrections` | Document corrections | JSON |
| `file.data` | Raw file binary data (hex) | Binary |
| `file.metadata` | File integrity & storage info | JSON |
| `file.chunks` | Large file chunks | Binary |

### Blockchain Address Roles

Each role has a dedicated blockchain address with specific permissions:

| Role | Address Env Var | Global Permissions | Stream Permissions |
|------|-----------------|--------------------|--------------------|
| Admin | `MULTICHAIN_ADMIN_ADDRESS` | admin, send, receive, create, issue, mine, activate | admin, write, read |
| BAC Secretariat | `MULTICHAIN_BAC_SECRETARIAT_ADDRESS` | send, receive, create, issue, activate | admin, write, read |
| BAC Chairman | `MULTICHAIN_BAC_CHAIRMAN_ADDRESS` | send, receive | write, read |
| HOPE | `MULTICHAIN_HOPE_ADDRESS` | send, receive | write, read |

### MultiChain Client Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                      Application Layer                       │
│  (Services, Publishers, Controllers)                        │
└──────────────────────────┬───────────────────────────────────┘
                           │
┌──────────────────────────▼───────────────────────────────────┐
│                   MultiChain Manager                         │
│  (Libraries/MultiChain/Manager.php)                         │
│  - Connection pooling                                       │
│  - Retry logic with exponential backoff                    │
│  - Circuit breaker pattern                                 │
└──────────────────────────┬───────────────────────────────────┘
                           │
┌──────────────────────────▼───────────────────────────────────┐
│                   MultiChain Client                          │
│  (Libraries/MultiChain/Client.php)                          │
│  - JSON-RPC 2.0 implementation                             │
│  - HTTP Basic authentication                               │
│  - Error handling & validation                             │
└──────────────────────────┬───────────────────────────────────┘
                           │
┌──────────────────────────▼───────────────────────────────────┐
│                   MultiChain Node                            │
│  - RPC server (port 7000 default)                          │
│  - Blockchain storage                                       │
│  - Stream management                                        │
└──────────────────────────────────────────────────────────────┘
```

### Publishing Architecture

#### Publisher Pattern

All blockchain writes go through specialized publisher services:

```
Publishers/
├── DocumentPublisher.php      # Document publishing
├── StatusPublisher.php        # Status change publishing
├── EventPublisher.php         # Event logging
├── CorrectionPublisher.php    # Document corrections
└── ProcurementOrchestrator.php # Atomic multi-publisher operations
```

#### Orchestrated Publishing

The `ProcurementOrchestrator` coordinates atomic operations:

```php
// Example: Atomic document workflow
$orchestrator->publishDocumentWorkflow(
    procurementData: [
        'pr_number' => 'PR-2025-001',
        'procurement_title' => 'Office Supplies',
        'user_address' => $userBlockchainAddress,
    ],
    file: $uploadedFile,
    documentData: [...],
    statusData: [...],
    eventData: [...]
);
```

**Atomic Guarantees:**
- All publishers succeed or all fail
- Transaction IDs tracked for rollback
- Error state captured for retry

### Blockchain Status Lifecycle

Every blockchain write goes through this lifecycle:

```
pending → publishing → published
                ↓
             failed (retryable)
```

**Database Tracking:**
- `blockchain_status` enum field
- `blockchain_txid` stores transaction ID
- `blockchain_error` stores error message
- `blockchain_retry_count` tracks attempts
- `blockchain_status_updated_at` timestamp

### On-Chain File Storage

Files are stored directly on the blockchain:

1. **File Read**: File content read from upload
2. **Hash Generation**: SHA-256 hash computed
3. **Hex Encoding**: File converted to hex string
4. **Stream Write**: Hex data written to `file.data` stream
5. **Metadata Write**: File info written to `file.metadata` stream
6. **Document Link**: Document record links to blockchain txids

**Benefits:**
- Zero external storage costs
- Automatic replication across all nodes
- Immutable file integrity
- Heroku-compatible (no file storage needed)

**Limitations:**
- File size limited by blockchain block size
- Larger files may need chunking
- Slower than traditional file storage

### Blockchain Commands

#### Setup Command

```bash
php artisan multichain:setup
```

**Operations:**
1. Checks MultiChain node connectivity
2. Generates blockchain addresses for roles
3. Creates required streams
4. Grants permissions per role
5. Updates `.env` with addresses
6. Syncs user records with addresses

#### Permission Status

```bash
php artisan multichain:permission-status
```

Displays permission matrix for all roles and streams.

#### Storage Initialization

```bash
php artisan multichain:initialize-storage
```

Ensures blockchain streams are properly created.

#### Status Reconciliation

```bash
php artisan multichain:reconcile-status
```

Syncs blockchain status between database and blockchain.

---

## Service Layer Architecture

### Service Categories

ProcuChain has **20+ specialized services** organized by responsibility:

#### 1. Blockchain Services

| Service | Purpose |
|---------|---------|
| `BlockchainHealthService` | Node health monitoring |
| `BlockchainMonitoringService` | Transaction monitoring |
| `BlockchainStorageService` | On-chain storage operations |

#### 2. Publisher Services

| Service | Purpose |
|---------|---------|
| `DocumentPublisher` | Publish documents to blockchain |
| `StatusPublisher` | Publish status changes |
| `EventPublisher` | Publish workflow events |
| `CorrectionPublisher` | Publish document corrections |
| `ProcurementOrchestrator` | Atomic multi-publisher operations |

#### 3. Procurement Services

| Service | Purpose |
|---------|---------|
| `ProcurementDataService` | Procurement data management |
| `ProcurementStageTransitionService` | Stage workflow logic |
| `StageDocumentRequirements` | Document requirement validation |
| `DocumentValidationService` | Document integrity checks |

#### 4. Security Services

| Service | Purpose |
|---------|---------|
| `LoginService` | Authentication logic |
| `AccountLockoutService` | Account locking/unlocking |
| `BlockedIpService` | IP blocking management |
| `DeviceDetectionService` | Device fingerprinting |
| `LoginLoggerService` | Login activity logging |

#### 5. Analytics Services

| Service | Purpose |
|---------|---------|
| `AdminAnalyticsService` | Admin dashboard analytics |
| `LoginAnalyticsService` | Login statistics |
| `DashboardService` | Dashboard data aggregation |

#### 6. Utility Services

| Service | Purpose |
|---------|---------|
| `FileStorageService` | File operations |
| `CacheStrategyService` | Cache management |
| `NotificationService` | Email & push notifications |
| `DashboardCacheKeys` | Cache key generation |
| `UserService` | User management |

### Service Pattern Example

```php
// Services follow dependency injection
class ProcurementDataService
{
    public function __construct(
        private MultiChainClient $blockchain,
        private CacheStrategyService $cache
    ) {}

    public function getProcurementByPrNumber(string $prNumber): ?Procurement
    {
        return $this->cache->remember(
            key: "procurement:{$prNumber}",
            ttl: 3600,
            callback: fn() => Procurement::find($prNumber)
        );
    }
}
```

---

## Frontend Architecture

### React + Inertia.js Architecture

ProcuChain uses **Inertia.js** to build a single-page application (SPA) without a separate API:

```
┌────────────────────────────────────────────────────────────┐
│                    Browser (React)                         │
│  - Components render                                       │
│  - User interactions                                       │
│  - Inertia router handles navigation                       │
└────────────────┬───────────────────────────────────────────┘
                 │ Inertia visit (AJAX)
                 ▼
┌────────────────────────────────────────────────────────────┐
│                Laravel Backend                             │
│  - Controllers return Inertia::render()                   │
│  - Data serialized to JSON                                │
└────────────────┬───────────────────────────────────────────┘
                 │ JSON response
                 ▼
┌────────────────────────────────────────────────────────────┐
│                 Inertia.js Adapter                         │
│  - Swaps React components                                 │
│  - Updates props                                          │
│  - No page reload                                         │
└────────────────────────────────────────────────────────────┘
```

### Component Structure

```
resources/js/
├── components/              # 60+ reusable components
│   ├── ui/                  # shadcn/ui components
│   ├── admin/               # Admin-specific components
│   ├── dashboard/           # Dashboard widgets
│   ├── procurement/         # Procurement components
│   ├── documents/           # Document viewers/uploaders
│   ├── blockchain/          # Blockchain status displays
│   ├── auth/                # Auth forms
│   └── pdf-viewer/          # PDF viewing components
├── layouts/                 # Page layouts
│   ├── AuthLayout.tsx       # Authentication pages
│   ├── DashboardLayout.tsx  # Main dashboard
│   └── GuestLayout.tsx      # Public pages
├── pages/                   # Inertia page components
│   ├── admin/               # Admin pages (15+ pages)
│   ├── bac-secretariat/     # BAC Secretariat pages (30+ pages)
│   ├── bac-chairman/        # BAC Chairman pages (5+ pages)
│   ├── hope/                # HOPE pages (5+ pages)
│   ├── auth/                # Auth pages (login, 2FA, etc.)
│   ├── settings/            # User settings pages
│   └── procurements/        # Procurement pages
├── hooks/                   # Custom React hooks
├── lib/                     # Utilities & helpers
├── types/                   # TypeScript definitions
├── utils/                   # Helper functions
└── wayfinder/               # Type-safe routes (generated)
```

### Key Frontend Technologies

#### Tailwind CSS v4

```css
/* resources/css/app.css */
@import "tailwindcss";

@theme {
  --color-brand: oklch(0.72 0.11 178);
}
```

#### shadcn/ui Components

Pre-built, accessible components based on Radix UI:
- Dialog, Sheet, Dropdown Menu, Select
- Table, Pagination, Tabs
- Form components with validation
- Toast notifications

#### Laravel Wayfinder

Type-safe route generation for TypeScript:

```typescript
// Automatically generated from Laravel routes
import { show } from '@/actions/App/Http/Controllers/ProcurementController'

// Type-safe route usage
const procurementUrl = show.url('PR-2025-001') // "/procurements/PR-2025-001"

// With Inertia
router.visit(show('PR-2025-001'))
```

#### React 19 Features

- Concurrent rendering
- Automatic batching
- Improved Suspense support

### State Management

ProcuChain uses **Inertia.js props** for state management (no Redux/Zustand):

```tsx
// Page component receives props from Laravel
export default function Dashboard({ 
  auth,           // Current user + permissions
  procurements,   // Procurement data
  analytics,      // Dashboard stats
}: PageProps<{
  procurements: Procurement[];
  analytics: Analytics;
}>) {
  // Props are reactive and update on navigation
  return <DashboardView data={analytics} />
}
```

### Form Handling

Using Inertia's `<Form>` component:

```tsx
import { Form } from '@inertiajs/react'
import { store } from '@/actions/.../ProcurementController'

export default function CreateForm() {
  return (
    <Form {...store.form()}>
      {({ errors, processing }) => (
        <>
          <input name="title" />
          {errors.title && <div>{errors.title}</div>}
          <button disabled={processing}>Create</button>
        </>
      )}
    </Form>
  )
}
```

---

## Security Architecture

### Authentication & Authorization

#### Multi-Factor Authentication (2FA)

**Implementation**: Laravel Fortify + Google2FA

**Flow:**
1. User enables 2FA in settings
2. QR code generated with TOTP secret
3. Secret encrypted and stored in database
4. Recovery codes generated (encrypted)
5. User confirms 2FA with OTP code
6. Future logins require OTP after password

**Recovery:**
- 10 single-use recovery codes
- Encrypted with Laravel encryption
- Can be regenerated

#### Account Lockout Protection

**Configuration** (in `AccountLockoutService`):
- Max failed attempts: 5 (configurable)
- Lockout duration: 30 minutes (configurable)
- Auto-unlock: Yes (via scheduled task)

**Features:**
- Progressive delay (exponential backoff)
- Email notification on lockout
- Admin can unlock accounts
- Bulk unlock capability

**Implementation:**
```php
if ($user->account_locked && now()->lessThan($user->lock_expires_at)) {
    throw new AccountLockedException($user);
}
```

#### IP Blocking

**Types:**
- Manual blocking by admin
- Automatic blocking on suspicious activity
- Temporary blocks with expiration
- Permanent blocks

**Bypass:**
- Admin role bypasses IP blocks
- Whitelist configuration available

### Role-Based Access Control (RBAC)

**Package**: Spatie Laravel Permission 6.21

**Roles:**
1. **Admin**: Full system access
   - User management
   - Blockchain explorer
   - IP blocking
   - Account lockout management
   
2. **BAC Secretariat**: Primary procurement role
   - Create procurements
   - Upload documents
   - Publish to blockchain
   - Manage workflow
   
3. **BAC Chairman**: Oversight role
   - Review documents
   - Approve transitions
   - View audit trails
   
4. **HOPE**: Executive role
   - High-level monitoring
   - Analytics access
   - Blockchain verification

**Permission Checking:**
```php
// In controller
if (!auth()->user()->hasRole('admin')) {
    abort(403);
}

// In Blade/Inertia
@can('manage-users')
    // Admin content
@endcan
```

### Data Security

#### Encryption

**At Rest:**
- Passwords: Bcrypt (12 rounds)
- 2FA secrets: Laravel encryption (AES-256-CBC)
- Recovery codes: Laravel encryption
- Sensitive config: `.env` file (not in repo)

**In Transit:**
- HTTPS/TLS 1.2+ in production
- Blockchain RPC: HTTP Basic Auth
- API: Session-based auth

#### File Integrity

**SHA-256 Hashing:**
- All files hashed before blockchain storage
- Hash stored in `file.metadata` stream
- Verification on file download
- Corruption detection

**Verification Flow:**
1. File uploaded
2. SHA-256 hash computed
3. Hash stored on blockchain
4. Download: Recompute hash and compare
5. Alert if mismatch detected

### Audit Trail

#### Login Logging

**Captured Data:**
- IP address
- User agent (browser, OS)
- Device type (mobile/desktop/tablet)
- Location (if available)
- Success/failure
- Login/logout timestamps

#### Document Access Logging

**Tracked in `document_views`:**
- User who viewed
- Document identifier
- Procurement context
- View duration
- IP address
- Timestamp

#### Blockchain Audit

**Immutable Records:**
- All document uploads
- Status transitions
- Corrections
- Events

**Query Interface:**
- Admin blockchain explorer
- Transaction lookup by txid
- Address transaction history
- Stream item browsing

### Security Monitoring

#### Sentry Integration

**Error Tracking:**
- Real-time exception capture
- Stack traces with context
- User identification
- Performance monitoring
- Release tracking

**Configuration:**
```bash
SENTRY_LARAVEL_DSN=your_dsn
SENTRY_TRACES_SAMPLE_RATE=1.0
```

#### Failed Job Monitoring

Database-driven queue tracks failures:
- Job payload
- Exception details
- Failed timestamp
- Retry attempts

---

## API & Routes

### Route Overview

**Total Routes**: 151  
**Route Files**: 4 files
- `routes/web.php` - Main application routes
- `routes/auth.php` - Authentication routes
- `routes/settings.php` - User settings routes
- `routes/file-uploads-ui-preview.php` - File upload preview

### Route Groups

#### 1. Public Routes

```
GET  /                     # Home page
GET  /about                # About page
GET  /contact              # Contact page
GET  /team                 # Team page
GET  /search               # Search interface
GET  /terms.pdf            # Terms of service
GET  /privacy.pdf          # Privacy policy
```

#### 2. Authentication Routes

```
GET   /login                           # Login form
POST  /login                           # Process login
POST  /logout                          # Logout
GET   /two-factor-challenge            # 2FA verification
POST  /two-factor-challenge            # Submit 2FA code
GET   /forgot-password                 # Password reset request
POST  /forgot-password                 # Send reset link
GET   /reset-password/{token}          # Password reset form
POST  /reset-password                  # Process password reset
```

#### 3. Admin Routes

Prefix: `/admin`, Middleware: `auth`, `role:admin`

```
GET   /admin/dashboard                 # Admin dashboard
GET   /admin/users                     # User list
POST  /admin/users                     # Create user
PUT   /admin/users/{user}              # Update user
DELETE /admin/users/{user}             # Delete user
POST  /admin/users/{user}/reset-password

# Account Lockout Management
GET   /admin/accounts/locked           # Locked accounts list
POST  /admin/accounts/{user}/lock      # Lock account
POST  /admin/accounts/{user}/unlock    # Unlock account
POST  /admin/accounts/bulk-unlock      # Unlock multiple

# Login Logs & Security
GET   /admin/login-logs                # Login history
GET   /admin/login-logs/statistics     # Login analytics
POST  /admin/login-logs/block-ip       # Block IP address
POST  /admin/login-logs/unblock-ip     # Unblock IP

# Blockchain Explorer
GET   /admin/blockchain-explorer       # Explorer home
GET   /admin/blockchain-explorer/transaction
GET   /admin/blockchain-explorer/block
GET   /admin/blockchain-explorer/address/{address}
GET   /admin/blockchain-explorer/stream/{stream}/items

# Procurement Viewing
GET   /admin/procurements-list         # View all procurements
GET   /admin/procurements-list/{id}    # View procurement detail
```

#### 4. BAC Secretariat Routes

Prefix: `/bac-secretariat`, Middleware: `auth`, `role:bac_secretariat`

```
GET   /bac-secretariat/dashboard       # Dashboard

# Procurement Initiation
GET   /bac-secretariat/procurement-initiation
POST  /bac-secretariat/initiate-procurement
GET   /bac-secretariat/procurement-initiation-list
GET   /bac-secretariat/procurement-initiation/{id}

# Stage-Specific Upload Pages (15 stages)
GET   /bac-secretariat/pre-procurement/{pr_number}/{stage}
POST  /bac-secretariat/pre-procurement/{pr_number}/{stage}/upload-document
POST  /bac-secretariat/pre-procurement/{pr_number}/{stage}/complete

GET   /bac-secretariat/procurement/{pr_number}/{stage}
POST  /bac-secretariat/procurement/{pr_number}/{stage}/upload-document
POST  /bac-secretariat/procurement/{pr_number}/{stage}/complete

GET   /bac-secretariat/post-procurement/{pr_number}/{stage}
POST  /bac-secretariat/post-procurement/{pr_number}/{stage}/upload-document
POST  /bac-secretariat/post-procurement/{pr_number}/{stage}/complete

# Blockchain Publishing
GET   /bac-secretariat/blockchain/publishing-status/{id}

# Procurement List
GET   /bac-secretariat/procurements-list
GET   /bac-secretariat/procurements-list/{id}
```

#### 5. BAC Chairman Routes

Prefix: `/bac-chairman`, Middleware: `auth`, `role:bac_chairman`

```
GET   /bac-chairman/dashboard          # Dashboard
GET   /bac-chairman/procurements-list  # View procurements
GET   /bac-chairman/procurements-list/{id}
```

#### 6. HOPE Routes

Prefix: `/hope`, Middleware: `auth`, `role:hope`

```
GET   /hope/dashboard                  # Dashboard
GET   /hope/procurements-list          # View procurements
GET   /hope/procurements-list/{id}
```

#### 7. Settings Routes

Prefix: `/settings`, Middleware: `auth`

```
GET   /settings/profile                # Profile settings
PATCH /settings/profile                # Update profile
DELETE /settings/profile               # Delete account

GET   /settings/password               # Password change
PUT   /settings/password               # Update password

GET   /settings/two-factor             # 2FA settings
POST  /settings/two-factor-authentication        # Enable 2FA
DELETE /settings/two-factor-authentication       # Disable 2FA
GET   /settings/two-factor-qr-code               # Get QR code
GET   /settings/two-factor-recovery-codes        # Get recovery codes
POST  /settings/two-factor-recovery-codes        # Regenerate codes
POST  /settings/confirmed-two-factor-authentication # Confirm 2FA

GET   /settings/email-notification     # Email preferences
PATCH /settings/email-notification     # Update preferences

GET   /settings/push-notification      # Push notification settings
POST  /settings/push-notification/subscribe
DELETE /settings/push-notification/unsubscribe
```

#### 8. Document Routes

```
GET   /files/{fileKey}                 # Download file
GET   /pdf-viewer/{fileKey}            # View PDF in browser
```

#### 9. Procurement Routes

```
GET   /procurements/{id}/blockchain-status
GET   /procurements/{id}/corrections
GET   /procurements/{procurement}/corrections-history
POST  /documents/{document}/correct    # Submit correction
GET   /corrections/check/{txid}        # Verify correction
```

#### 10. Notification Routes

```
GET   /notifications                   # Notification page
POST  /notifications/{id}/mark-as-read
POST  /notifications/mark-all-as-read
```

### API Response Format

Inertia.js returns JSON for AJAX requests:

```json
{
  "component": "BacSecretariat/Dashboard",
  "props": {
    "auth": {
      "user": { "id": 1, "name": "John Doe", "email": "john@example.com" }
    },
    "procurements": [...],
    "flash": {
      "success": "Procurement created successfully"
    }
  },
  "url": "/bac-secretariat/dashboard",
  "version": "abc123"
}
```

---

## Queue & Background Jobs

### Queue Configuration

**Driver**: Database  
**Connection**: mysql  
**Queue Table**: `jobs`  
**Failed Jobs Table**: `failed_jobs`

### Why Queue-Based Blockchain Operations?

**Problem**: Blockchain writes can take 5-30 seconds, causing HTTP timeouts.

**Solution**: Async job processing:
1. User submits document
2. Document saved to database (instant)
3. Job dispatched to queue
4. User sees "Publishing..." status
5. Queue worker processes job in background
6. Status updated to "Published" when complete

### Job Classes

While no dedicated `app/Jobs` directory exists, job logic is likely handled through:
- Service layer methods
- Event listeners
- Laravel's `dispatch()` helper

**Typical Job Flow:**
```php
// Dispatch job
dispatch(function() use ($procurement) {
    app(ProcurementOrchestrator::class)->publishDocumentWorkflow(...);
});

// Or using queued closure
Bus::chain([
    function() { /* Step 1 */ },
    function() { /* Step 2 */ },
])->dispatch();
```

### Queue Worker

**Development:**
```bash
php artisan queue:work --sleep=3 --tries=3
```

**Production (via Procfile):**
```
worker: php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

### Failed Job Handling

**Retry Strategy:**
- Max retries: 3 (configurable)
- Exponential backoff
- Circuit breaker on repeated failures

**Manual Retry:**
```bash
php artisan queue:retry all
php artisan queue:retry {uuid}
```

---

## Testing Strategy

### Testing Framework

**Primary**: Pest v4.1.3  
**Fallback**: PHPUnit 12.4.1

### Test Structure

```
tests/
├── Pest.php                  # Pest configuration
├── TestCase.php              # Base test case
├── SeedsPermissions.php      # Permission seeding helper
├── Feature/                  # Feature tests (integration)
│   ├── Admin/
│   ├── Auth/
│   ├── BacSecretariat/
│   └── Settings/
├── Unit/                     # Unit tests (isolated)
│   ├── Services/
│   ├── Enums/
│   └── Libraries/
├── Browser/                  # Browser tests (Pest v4)
│   └── [Browser tests]
└── js/                       # Frontend tests
    └── [Vitest tests]
```

### Pest v4 Features

#### 1. Browser Testing

```php
it('can create procurement', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/bac-secretariat/procurement-initiation');

    $page->fill('pr_number', 'PR-2025-001')
        ->fill('title', 'Test Procurement')
        ->click('Submit')
        ->assertSee('Procurement created successfully');
});
```

#### 2. Smoke Testing

```php
$pages = visit([
    '/login',
    '/about',
    '/contact',
]);

$pages->assertNoJavascriptErrors()
    ->assertNoConsoleLogs();
```

#### 3. Visual Regression Testing

```php
it('matches dashboard screenshot', function () {
    visit('/bac-secretariat/dashboard')
        ->screenshot('dashboard')
        ->assertScreenshotMatches();
});
```

### Test Database

**In-Memory SQLite** or **Dedicated Test Database**

```php
// phpunit.xml or Pest.php
'DB_CONNECTION' => 'sqlite',
'DB_DATABASE' => ':memory:',
```

### Running Tests

```bash
# All tests
php artisan test

# Specific file
php artisan test tests/Feature/AdminTest.php

# Filter by name
php artisan test --filter=login

# With coverage
php artisan test --coverage
```

### Testing Best Practices

1. **Use Factories**: All models have factories
2. **RefreshDatabase**: Each test starts with clean DB
3. **Fake External Services**: `Event::fake()`, `Notification::fake()`
4. **Test Happy + Sad Paths**: Success and failure scenarios
5. **Integration over Unit**: Prefer feature tests for workflows

---

## Deployment Architecture

### Deployment Targets

1. **Local Development** (Herd/Valet)
2. **Heroku** (Primary production)
3. **Custom VPS** (Alternative)

### Heroku Deployment

#### Procfile

```
web: php artisan inertia:start-ssr & heroku-php-apache2 public/
worker: php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

#### Required Add-ons

- **ClearDB MySQL** or **JawsDB MySQL** - Database
- **Heroku Scheduler** - Scheduled tasks (optional)
- **Papertrail** - Log management (optional)

#### Environment Variables

All from `.env` file, set via Heroku dashboard or CLI:

```bash
heroku config:set APP_KEY=base64:...
heroku config:set DB_CONNECTION=mysql
heroku config:set DB_HOST=xxx
heroku config:set MULTICHAIN_HOST=xxx
# ... etc
```

#### Build Process

```bash
# Install dependencies
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# Optimize Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
php artisan migrate --force
```

#### MultiChain Setup

**On First Deploy:**
```bash
heroku run php artisan multichain:setup
```

This will:
- Generate blockchain addresses
- Create streams
- Grant permissions
- Update database

**Important**: MultiChain node must be accessible from Heroku dyno via RPC.

### Custom VPS Deployment

#### Server Requirements

- PHP 8.3+ with extensions (bcmath, ctype, curl, json, mbstring, openssl, pdo_mysql, tokenizer, xml)
- Nginx or Apache
- MySQL 8.0+
- Node.js 18+ (for build)
- MultiChain node (same server or remote)
- Supervisor (for queue worker)

#### Nginx Configuration

```nginx
server {
    listen 80;
    server_name procuchain.example.com;
    root /var/www/procuchain/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

#### Supervisor Configuration

```ini
[program:procuchain-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/procuchain/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
numprocs=4
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/procuchain/storage/logs/queue.log
```

### Deployment Checklist

- [ ] Set all environment variables
- [ ] Generate `APP_KEY`
- [ ] Run `composer install --no-dev --optimize-autoloader`
- [ ] Run `npm ci && npm run build`
- [ ] Run `php artisan migrate --force`
- [ ] Run `php artisan multichain:setup`
- [ ] Run `php artisan config:cache`
- [ ] Run `php artisan route:cache`
- [ ] Run `php artisan view:cache`
- [ ] Verify MultiChain connectivity
- [ ] Start queue worker
- [ ] Set up SSL certificate (Let's Encrypt)
- [ ] Configure firewall rules
- [ ] Set up monitoring (Sentry)

---

## Conclusion

ProcuChain is a comprehensive blockchain-backed procurement management system that leverages Laravel 12, React 19, Inertia.js, and MultiChain to provide secure, transparent, and auditable government procurement processes. The architecture emphasizes separation of concerns, security, and compliance with Philippine procurement regulations (RA 9184).

For implementation details, refer to:
- [stages.md](./stages.md) - 15 procurement stages
- [PROCUREMENT_PHASE_RESTRUCTURE.md](./PROCUREMENT_PHASE_RESTRUCTURE.md) - Phase organization
- [TRANSACTION_BOUNDARIES_ARCHITECTURE.md](./TRANSACTION_BOUNDARIES_ARCHITECTURE.md) - Blockchain boundaries

---

**Document Maintained By:** Development Team  
**Review Frequency:** Quarterly or on major releases
