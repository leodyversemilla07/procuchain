# Database Schema Documentation

This document outlines the database schema for the ProcuChain application.

## Core Tables

### `users`
Stores user account information.

| Column | Type | Nullable | Description |
| :--- | :--- | :--- | :--- |
| `id` | bigint | No | Primary Key |
| `name` | string | No | User's full name |
| `email` | string | No | Unique email address |
| `blockchain_address` | string | Yes | MultiChain address associated with the user role |
| `email_verified_at` | timestamp | Yes | Timestamp of email verification |
| `password` | string | No | Hashed password |
| `account_locked` | boolean | No | Default: `false`. Indicates if account is locked |
| `locked_at` | timestamp | Yes | When the account was locked |
| `lock_expires_at` | timestamp | Yes | When the lock expires |
| `failed_login_attempts` | integer | No | Default: 0. Counter for failed logins |
| `last_failed_login_at` | timestamp | Yes | Timestamp of last failed attempt |
| `locked_reason` | string | Yes | Reason for account locking |
| `two_factor_secret` | text | Yes | 2FA secret key |
| `two_factor_recovery_codes` | text | Yes | 2FA recovery codes |
| `two_factor_confirmed_at` | timestamp | Yes | When 2FA was confirmed |
| `email_notifications_enabled` | boolean | No | Default: `true`. |
| `remember_token` | string | Yes | "Remember me" token |
| `created_at` | timestamp | Yes | Creation timestamp |
| `updated_at` | timestamp | Yes | Update timestamp |

### `procurement_workflow_configs`
Stores workflow configuration for each procurement mode.

| Column | Type | Nullable | Description |
| :--- | :--- | :--- | :--- |
| `id` | bigint | No | Primary Key |
| `procurement_mode` | string | No | Unique identifier for mode (e.g., 'public_bidding') |
| `display_name` | string | No | Human-readable name |
| `description` | text | Yes | Description of the mode |
| `stages` | json | No | Array of stage identifiers |
| `optional_stages` | json | Yes | Array of optional stage identifiers |
| `is_active` | boolean | No | Default: `true`. |
| `updated_by` | foreignId | Yes | User ID who last updated this config (set null on delete) |
| `created_at` | timestamp | Yes | Creation timestamp |
| `updated_at` | timestamp | Yes | Update timestamp |

### `stage_document_configs`
Stores document requirements for each stage/mode combination.

| Column | Type | Nullable | Description |
| :--- | :--- | :--- | :--- |
| `id` | bigint | No | Primary Key |
| `stage` | string | No | Stage identifier |
| `procurement_mode` | string | No | Procurement mode identifier |
| `stage_display_name` | string | No | Display name for the stage |
| `required_documents` | json | No | List of required document types |
| `optional_documents` | json | Yes | List of optional document types |
| `is_active` | boolean | No | Default: `true`. |
| `updated_by` | foreignId | Yes | User ID who last updated this config (set null on delete) |
| `created_at` | timestamp | Yes | Creation timestamp |
| `updated_at` | timestamp | Yes | Update timestamp |

## Permission Tables (Spatie Permission)

The application uses the `spatie/laravel-permission` package.

### `permissions`
| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | bigint | Primary Key |
| `name` | string | Permission name |
| `guard_name` | string | Guard name (usually 'web') |
| `created_at`, `updated_at`| | |

### `roles`
| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | bigint | Primary Key |
| `name` | string | Role name (e.g., 'admin', 'bac_secretariat') |
| `guard_name` | string | Guard name |
| `created_at`, `updated_at`| | |

### Pivot Tables
- `model_has_permissions`: Assigns permissions to users.
- `model_has_roles`: Assigns roles to users.
- `role_has_permissions`: Assigns permissions to roles.

## Other Tables

- `password_reset_tokens`: Stores tokens for password reset requests.
- `sessions`: database driver for storing user sessions.
