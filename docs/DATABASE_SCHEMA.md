# Database Schema

This document summarizes the MySQL schema used by ProcuChain.

## Overview

MySQL stores mutable application state. Immutable procurement history and file integrity records live on MultiChain instead.

At a high level, MySQL is responsible for:

- identity and access management
- workflow/document configuration
- notifications and user preferences
- queues, cache, and sessions
- security and audit records

## Core Application Tables

### `users`

Primary application users.

Important fields include:

- `name`
- `email`
- `password`
- `blockchain_address`
- account lockout fields
- two-factor fields
- notification preference fields

This table links Laravel identities to role-based blockchain addresses.

### `procurement_workflow_configs`

Admin-managed workflow definitions per procurement mode.

Key columns:

- `procurement_mode`
- `display_name`
- `description`
- `stages`
- `optional_stages`
- `is_active`
- `updated_by`

### `stage_document_configs`

Admin-managed document requirements per stage and procurement mode.

Key columns:

- `stage`
- `procurement_mode`
- `stage_display_name`
- `required_documents`
- `optional_documents`
- `is_active`
- `updated_by`

These two configuration tables are the first lookup layer for `WorkflowDefinitionService`.

## Authorization Tables

Managed through Spatie Laravel Permission:

- `roles`
- `permissions`
- `model_has_roles`
- `model_has_permissions`
- `role_has_permissions`

## Security and Audit Tables

- `audit_logs`
- `user_login_logs`
- `blocked_ips`
- `password_reset_tokens`

These support account monitoring, security operations, and admin review tooling.

## Notification and User Preference Tables

- `notifications`
- `push_subscriptions`
- `user_invitations`

These support in-app notifications, browser push notifications, and invitation-based user onboarding.

## Laravel Infrastructure Tables

- `migrations`
- `jobs`
- `job_batches`
- `failed_jobs`
- `sessions`
- `cache`
- `cache_locks`

The application currently uses database-backed queue, cache, and session infrastructure in normal operation.

## What Is Not in MySQL

Procurement records themselves are not treated as a primary MySQL dataset. The immutable procurement state lives on MultiChain:

- procurement metadata
- status history
- document metadata
- events
- corrections
- archive records
- file storage records

MySQL stores the configuration and operational context around those records, not the authoritative immutable history.

## How Schema and Runtime Interact

Important runtime relationships:

- `users.blockchain_address` ties Laravel users to chain permissions and audit identity
- workflow configuration tables override enum/service defaults through `WorkflowDefinitionService`
- notifications, invitations, and security tables power admin and settings screens
- queue/cache/session tables support asynchronous blockchain publishing and UI responsiveness

## Related Documents

- [Architecture](ARCHITECTURE.md)
- [Blockchain Schema](BLOCKCHAIN_SCHEMA.md)
- [Route Reference](ROUTES.md)
