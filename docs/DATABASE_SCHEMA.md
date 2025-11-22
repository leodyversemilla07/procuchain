# ProcuChain Database Schema Documentation

**Database Engine:** MySQL 8.0+  
**Character Set:** utf8mb4  
**Collation:** utf8mb4_unicode_ci  
**Total Tables:** 21  
**Last Updated:** November 15, 2025

---

## Table of Contents

1. [Schema Overview](#schema-overview)
2. [User & Authentication Tables](#user--authentication-tables)
3. [Procurement Tables](#procurement-tables)
4. [Security & Audit Tables](#security--audit-tables)
5. [Permission Tables](#permission-tables)
6. [Laravel System Tables](#laravel-system-tables)
7. [Entity Relationships](#entity-relationships)
8. [Indexes & Performance](#indexes--performance)

---

## Schema Overview

### Table Categories

| Category | Tables | Purpose |
|----------|--------|---------|
| **User Management** | users, user_login_logs | User accounts and login tracking |
| **Procurement** | procurements | Procurement data with blockchain references |
| **Security** | blocked_ips, document_views | IP blocking and access tracking |
| **Permissions** | roles, permissions, model_has_roles, role_has_permissions, model_has_permissions | Spatie RBAC |
| **Laravel System** | cache, cache_locks, sessions, jobs, failed_jobs, job_batches, notifications, push_subscriptions, password_reset_tokens, migrations | Framework tables |

---

## User & Authentication Tables

### Table: `users`

Primary user authentication and profile table.

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    blockchain_address VARCHAR(255),
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    
    -- Account Lockout
    account_locked TINYINT(1) DEFAULT 0,
    locked_at TIMESTAMP NULL,
    lock_expires_at TIMESTAMP NULL,
    failed_login_attempts INT DEFAULT 0,
    last_failed_login_at TIMESTAMP NULL,
    locked_reason VARCHAR(255) NULL,
    
    -- Two-Factor Authentication
    two_factor_secret TEXT NULL,
    two_factor_recovery_codes TEXT NULL,
    two_factor_confirmed_at TIMESTAMP NULL,
    
    -- Preferences
    email_notifications_enabled TINYINT(1) DEFAULT 1,
    
    -- Laravel Defaults
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

**Key Columns:**
- `blockchain_address` - User's address on the MultiChain blockchain
- `account_locked` - Boolean flag for account lockout status
- `lock_expires_at` - When automatic unlock occurs
- `two_factor_secret` - Encrypted TOTP secret
- `two_factor_recovery_codes` - Encrypted backup codes (JSON array)

**Relationships:**
- Has many `user_login_logs`
- Has many `document_views`
- Has many roles (via `model_has_roles`)
- Has many permissions (via `model_has_permissions`)

**Encryption:**
- `password` - Bcrypt hashed (12 rounds)
- `two_factor_secret` - Laravel encryption (AES-256-CBC)
- `two_factor_recovery_codes` - Laravel encryption

---

## Procurement Tables

### Table: `procurements`

Core procurement project data with blockchain integration.

```sql
CREATE TABLE procurements (
    id VARCHAR(255) PRIMARY KEY,  -- PR number (e.g., "PR-2025-001")
    title VARCHAR(255) NOT NULL,
    stage VARCHAR(255) NOT NULL,  -- StageEnums value
    current_status VARCHAR(255) NOT NULL,  -- StatusEnums value
    user_address VARCHAR(255) NOT NULL,
    document_count INT DEFAULT 0,
    last_updated TIMESTAMP NOT NULL,
    
    -- Blockchain Integration
    blockchain_txid VARCHAR(255) NULL,
    blockchain_status ENUM('pending', 'published', 'failed') DEFAULT 'pending',
    blockchain_status_updated_at TIMESTAMP NULL,
    blockchain_error TEXT NULL,
    blockchain_retry_count TINYINT DEFAULT 0,
    
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_stage (stage),
    INDEX idx_current_status (current_status),
    INDEX idx_last_updated (last_updated),
    INDEX idx_blockchain_status (blockchain_status)
);
```

**Key Features:**
- **String Primary Key:** Uses PR number as ID (e.g., "PR-2025-001")
- **Blockchain Status Tracking:** All writes tracked through lifecycle
- **Composite Indexes:** Optimized for common queries

**Blockchain Streams Used:**
- `procurement.metadata` - Core procurement data
- `procurement.status` - Status transition history
- `procurement.events` - Workflow events

**Note:** Procurement documents are stored directly in blockchain streams for single source of truth. No database storage is used for document metadata.

---

## Security & Audit Tables

### Table: `user_login_logs`

Comprehensive login activity tracking for security auditing.

```sql
CREATE TABLE user_login_logs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NOT NULL,
    
    -- Device Detection
    device_type VARCHAR(50) NULL,  -- 'mobile', 'desktop', 'tablet', 'unknown'
    browser VARCHAR(100) NULL,
    platform VARCHAR(100) NULL,
    location VARCHAR(255) NULL,
    
    -- Login Status
    successful TINYINT(1) NOT NULL,
    login_at TIMESTAMP NOT NULL,
    logout_at TIMESTAMP NULL,
    
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_login_at (login_at),
    INDEX idx_ip_address (ip_address),
    INDEX idx_user_login (user_id, login_at),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

**Used For:**
- Account lockout detection (count failed attempts)
- Suspicious activity detection (unusual IP, location, device)
- Login analytics (by time, location, device)
- Compliance auditing

**Device Detection Service:**
- Parses `user_agent` string
- Identifies browser (Chrome, Firefox, Safari, etc.)
- Identifies platform (Windows, macOS, Linux, etc.)
- Classifies device type (mobile, desktop, tablet)

### Table: `blocked_ips`

IP address blocking system for security.

```sql
CREATE TABLE blocked_ips (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    blocked_by BIGINT UNSIGNED NULL,
    reason VARCHAR(255) NULL,
    expires_at TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_is_active (is_active),
    INDEX idx_expires_at (expires_at),
    
    FOREIGN KEY (blocked_by) REFERENCES users(id) ON DELETE SET NULL
);
```

**Block Types:**
- **Permanent:** `expires_at = NULL`
- **Temporary:** `expires_at` set to future timestamp
- **Inactive:** `is_active = 0` (unblocked but logged)

**Middleware Check:**
```php
if (BlockedIp::where('ip_address', $request->ip())
    ->where('is_active', 1)
    ->where(function ($q) {
        $q->whereNull('expires_at')
          ->orWhere('expires_at', '>', now());
    })
    ->exists()) {
    abort(403, 'Your IP address has been blocked.');
}
```

### Table: `document_views`

Tracks document access for audit trail.

```sql
CREATE TABLE document_views (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    file_key VARCHAR(255) NOT NULL,
    procurement_id VARCHAR(255) NOT NULL,
    procurement_title VARCHAR(255) NOT NULL,
    document_type VARCHAR(255) NOT NULL,
    stage VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    view_duration INT NULL,  -- Seconds
    metadata JSON NULL,
    viewed_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_viewed_at (viewed_at),
    INDEX idx_user_file (user_id, file_key),
    INDEX idx_file_viewed (file_key, viewed_at),
    INDEX idx_proc_viewed (procurement_id, viewed_at),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

**Analytics Queries:**
- Most viewed documents
- Document access by user
- Access patterns by time
- Procurement document views

---

## Permission Tables

ProcuChain uses **Spatie Laravel Permission** for RBAC.

### Table: `roles`

```sql
CREATE TABLE roles (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    UNIQUE KEY roles_name_guard_name_unique (name, guard_name)
);
```

**Seeded Roles:**
- `admin` - Full system access
- `bac_secretariat` - BAC Secretary operations
- `bac_chairman` - BAC Chairman oversight
- `hope` - Head of Procuring Entity

### Table: `permissions`

```sql
CREATE TABLE permissions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    UNIQUE KEY permissions_name_guard_name_unique (name, guard_name)
);
```

### Table: `model_has_roles`

Many-to-many relationship between users and roles.

```sql
CREATE TABLE model_has_roles (
    role_id BIGINT UNSIGNED NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    model_id BIGINT UNSIGNED NOT NULL,
    
    PRIMARY KEY (role_id, model_id, model_type),
    INDEX model_has_roles_model_id_model_type_index (model_id, model_type),
    
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);
```

### Table: `role_has_permissions`

Many-to-many relationship between roles and permissions.

```sql
CREATE TABLE role_has_permissions (
    permission_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    
    PRIMARY KEY (permission_id, role_id),
    
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);
```

### Table: `model_has_permissions`

Direct permissions for models (rarely used, prefer role-based).

```sql
CREATE TABLE model_has_permissions (
    permission_id BIGINT UNSIGNED NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    model_id BIGINT UNSIGNED NOT NULL,
    
    PRIMARY KEY (permission_id, model_id, model_type),
    INDEX model_has_permissions_model_id_model_type_index (model_id, model_type),
    
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);
```

---

## Laravel System Tables

### Cache Tables

**Table: `cache`**
```sql
CREATE TABLE cache (
    key VARCHAR(255) PRIMARY KEY,
    value MEDIUMTEXT NOT NULL,
    expiration INT NOT NULL
);
```

**Table: `cache_locks`**
```sql
CREATE TABLE cache_locks (
    key VARCHAR(255) PRIMARY KEY,
    owner VARCHAR(255) NOT NULL,
    expiration INT NOT NULL
);
```

### Session Table

**Table: `sessions`**
```sql
CREATE TABLE sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    payload LONGTEXT NOT NULL,
    last_activity INT NOT NULL,
    
    INDEX sessions_user_id_index (user_id),
    INDEX sessions_last_activity_index (last_activity)
);
```

### Queue Tables

**Table: `jobs`**
```sql
CREATE TABLE jobs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    queue VARCHAR(255) NOT NULL,
    payload LONGTEXT NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL,
    reserved_at INT UNSIGNED NULL,
    available_at INT UNSIGNED NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    
    INDEX jobs_queue_index (queue)
);
```

**Table: `failed_jobs`**
```sql
CREATE TABLE failed_jobs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid VARCHAR(255) NOT NULL UNIQUE,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload LONGTEXT NOT NULL,
    exception LONGTEXT NOT NULL,
    failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Table: `job_batches`**
```sql
CREATE TABLE job_batches (
    id VARCHAR(255) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    total_jobs INT NOT NULL,
    pending_jobs INT NOT NULL,
    failed_jobs INT NOT NULL,
    failed_job_ids LONGTEXT NOT NULL,
    options MEDIUMTEXT NULL,
    cancelled_at INT NULL,
    created_at INT NOT NULL,
    finished_at INT NULL
);
```

### Notification Tables

**Table: `notifications`**
```sql
CREATE TABLE notifications (
    id CHAR(36) PRIMARY KEY,
    type VARCHAR(255) NOT NULL,
    notifiable_type VARCHAR(255) NOT NULL,
    notifiable_id BIGINT UNSIGNED NOT NULL,
    data TEXT NOT NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX notifications_notifiable_type_notifiable_id_index (notifiable_type, notifiable_id)
);
```

**Table: `push_subscriptions`**
```sql
CREATE TABLE push_subscriptions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    subscribable_type VARCHAR(255) NOT NULL,
    subscribable_id BIGINT UNSIGNED NOT NULL,
    endpoint VARCHAR(500) NOT NULL UNIQUE,
    public_key VARCHAR(255) NULL,
    auth_token VARCHAR(255) NULL,
    content_encoding VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX push_subscriptions_subscribable_type_subscribable_id_index (subscribable_type, subscribable_id)
);
```

### Other System Tables

**Table: `password_reset_tokens`**
```sql
CREATE TABLE password_reset_tokens (
    email VARCHAR(255) PRIMARY KEY,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL
);
```

**Table: `migrations`**
```sql
CREATE TABLE migrations (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    migration VARCHAR(255) NOT NULL,
    batch INT NOT NULL
);
```

---

## Entity Relationships

### Entity Relationship Diagram

```
┌────────────┐
│   users    │
└─────┬──────┘
      │
      │ 1:N
      ├──────────────┐
      │              │
      ▼              ▼
┌─────────────┐  ┌──────────────┐
│user_login   │  │document_views│
│   _logs     │  └──────────────┘
└─────────────┘
      │
      │ 1:N
      ▼
┌──────────────┐
│ blocked_ips  │
└──────────────┘

┌──────────────┐
│ procurements │
└──────┬───────┘
       │
       │ 1:N
       ▼
┌────────────────────┐
│procurement_documents│
└────────────────────┘

┌────────┐        ┌─────────────┐
│ roles  │───N:N──│permissions  │
└───┬────┘        └─────────────┘
    │
    │ N:N (via model_has_roles)
    ▼
┌────────┐
│ users  │
└────────┘
```

---

## Indexes & Performance

### Index Strategy

**Primary Keys:**
- All tables have primary key
- `procurements` uses string PK (PR number)
- Others use auto-incrementing BIGINT

**Foreign Keys:**
- Cascade deletes for dependent records
- Set null for optional relationships
- Indexed automatically

**Performance Indexes:**

**Frequently Queried Columns:**
- Timestamps: `created_at`, `login_at`, `viewed_at`, `last_updated`
- Status flags: `blockchain_status`, `is_active`, `successful`
- Identifiers: `procurement_id`, `file_key`, `ip_address`

**Composite Indexes:**
```sql
-- Procurement document filtering
INDEX idx_proc_docs_blockchain_status (procurement_id, blockchain_status)

-- User login history
INDEX idx_user_login (user_id, login_at)

-- Document view tracking
INDEX idx_user_file (user_id, file_key)
INDEX idx_file_viewed (file_key, viewed_at)
```

### Query Optimization Tips

**Avoid N+1 Queries:**
```php
// Bad
$procurements = Procurement::all();
foreach ($procurements as $procurement) {
    echo $procurement->documents->count(); // N+1 query
}

// Good
$procurements = Procurement::withCount('documents')->get();
foreach ($procurements as $procurement) {
    echo $procurement->documents_count; // No additional query
}
```

**Use Indexes:**
```php
// Indexed query
Procurement::where('blockchain_status', 'pending')->get();

// Not indexed (slow)
Procurement::where('title', 'LIKE', '%Office%')->get();
```

**Chunk Large Datasets:**
```php
// Process 100 records at a time
Procurement::chunk(100, function ($procurements) {
    foreach ($procurements as $procurement) {
        // Process
    }
});
```

---

## Database Maintenance

### Backup Strategy

**Daily Backups:**
```bash
mysqldump -u root -p procuchain > backup-$(date +%Y%m%d).sql
```

**Restore:**
```bash
mysql -u root -p procuchain < backup-20251115.sql
```

### Table Optimization

```sql
-- Optimize all tables
OPTIMIZE TABLE users, procurements;

-- Analyze for query optimizer
ANALYZE TABLE users, procurements;

-- Check table integrity
CHECK TABLE procurements;
```

### Index Maintenance

```sql
-- View index usage
SHOW INDEX FROM procurements;

-- Check index cardinality
SELECT * FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = 'procuchain' 
AND TABLE_NAME = 'procurements';
```

---

## Conclusion

The ProcuChain database schema is designed for:
- **Performance**: Strategic indexes on frequently queried columns
- **Security**: Comprehensive audit trails and access tracking
- **Integrity**: Foreign key constraints and blockchain references
- **Scalability**: Efficient data types and normalized structure

For application-level details, see [ARCHITECTURE.md](./ARCHITECTURE.md).

---

**Document Maintained By:** Database Team  
**Review Frequency:** Quarterly or on schema changes
