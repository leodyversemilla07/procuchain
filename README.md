<p align="center">
  <img src="public/logo.png" alt="ProcuChain Logo" width="120">
</p>

<h1 align="center">ProcuChain</h1>

<p align="center">
  Blockchain-backed procurement workflow, document integrity, and role-based oversight for BAC operations (RA 12009 / NGPA).
</p>

<p align="center">
  <a href="#overview">Overview</a> •
  <a href="#features">Features</a> •
  <a href="#getting-started">Getting started</a> •
  <a href="#project-structure">Project structure</a> •
  <a href="#documentation">Documentation</a>
</p>

## Overview

ProcuChain is a Laravel 13 + Inertia v3 + React 19 application for managing public procurement workflows with an immutable MultiChain audit trail.

It uses two persistence layers:

- **MySQL** — users, auth state, roles/permissions, workflow configuration, notifications, audit/security records, queue state, and normalized procurement query cache.
- **MultiChain** (local node, single RPC endpoint) — procurement metadata, document metadata, status transitions, audit events, corrections, archive flags, and on-chain file content.

> [!NOTE]
> There is no hosted deployment. The app runs locally against your own MySQL and MultiChain node.

## Features

- Role-based dashboards for `admin`, `bac_secretariat`, `bac_chairman`, and `hope`
- Configurable workflow and document requirements per procurement mode (NGPA, 11 modes)
- Procurement stage upload, completion, skip, and repeat flows
- Automatic integrity verification of MySQL records against the chain
- Blockchain explorer and node health monitoring
- Reports and filtered procurement search (keyword, not vector)
- PDF viewing, document verification, corrections, and archive flows
- Audit logs, user invitations, account lockouts, blocked IPs, and login analytics
- 2FA, Web Push notifications, and Resend email

## Tech stack

| Layer      | Technology                                  |
| ---------- | ------------------------------------------- |
| Backend    | PHP 8.4 / Laravel 13 / Fortify (auth + 2FA) |
| Frontend   | Inertia v3 / React 19 / TypeScript / Tailwind v4 / shadcn |
| Database   | MySQL                                       |
| Blockchain | MultiChain 2.3.3 (local node)               |
| AuthZ      | Spatie Laravel Permission                   |
| Typing     | Laravel Wayfinder (generated routes/actions)|
| Testing    | Pest 4 / PHPUnit, ESLint, Prettier, `tsc`   |
| Monitoring | Sentry                                      |

## Getting started

### Prerequisites

- PHP 8.4+ with `bcmath, json, curl, mbstring, xml, dom, pdo_mysql, fileinfo, openssl`
- Composer
- Node.js 18+
- MySQL
- A running MultiChain 2.3.3 node (see `MULTICHAIN_*` in `.env.example`)

### Install

```bash
composer install
npm install
```

### Configure

```bash
copy .env.example .env
php artisan key:generate
```

Edit `.env` — at minimum `DB_*`, `MULTICHAIN_RPC_*`, `MAIL_*`, and `APP_URL`.

### Prepare the database and blockchain

```bash
php artisan migrate --seed --no-interaction
php artisan multichain:setup --check --no-interaction
php artisan multichain:setup --no-interaction
php artisan workflow:sync-defaults --no-interaction
```

> [!TIP]
> `multichain:setup` checks RPC connectivity, creates the application streams, generates or reuses role blockchain addresses, grants permissions, and syncs addresses back to Laravel users.

### Run

```bash
composer run dev
```

This starts the Laravel server, queue worker, and Vite dev server concurrently. If frontend assets look stale, run `npm run build`.

## Workflow configuration

Workflow rules resolve through `App\Services\WorkflowDefinitionService`:

1. Active database configuration from `procurement_workflow_configs` and `stage_document_configs`
2. Hardcoded application defaults from enums and requirement services

```bash
php artisan workflow:sync-defaults --no-interaction
```

This materializes missing default rows without overwriting admin-managed records.

## Commands

```bash
composer run dev          # serve + queue + vite
npm run build             # production frontend build
npm run lint              # eslint (zero warnings)
npm run format:check      # prettier check
npm run types             # wayfinder + tsc --noEmit
npm run test:js           # frontend unit tests
php artisan test --compact            # backend tests
php artisan test --compact --filter=X # single area
vendor/bin/pint --dirty --format agent # PHP style
```

Required CI workflows are `linter` and `tests`. Browser tests in `tests/Browser` are intentionally non-blocking.

## Project structure

```text
app/
  Http/Controllers/   # thin controllers, incl. Procurement/*, Admin/*, Auth/*, Settings/*
  Services/           # business logic, Publishers/* (chain writes), Integrity/*
  Support/            # workflow/document requirement configs, NodeClientFactory
  Enums/              # ProcurementMode (11 NGPA modes), StageEnums (17 stages), Stream, UserRole
  Models/             # Procurement mirror + stages/documents/events/corrections, users, audit
  Policies/Observers/Jobs/Listeners/  # RBAC, immutable audit, async integrity work
config/
  multichain.php blockchain.php integrity.php procurement-actions.php
routes/
  web.php web/*.php auth.php settings.php console.php  # 157 named routes, no api.php
resources/js/
  pages/              # Inertia pages per role + procurement domain
  components/         # shadcn ui/* + feature folders
  layouts/ hooks/ lib/ types/  # shell, permissions, Wayfinder helpers
database/             # migrations (normalized procurement schema), factories, seeders
docs/
  index.md tutorials/ how-to/ reference/ explanation/ archive/
  ngpa/               # RA 12009 IRR rules 01-23 + README
tests/                # Feature, Unit, Browser (Pest 4)
```

## Documentation

Start at the [documentation index](docs/index.md) (Tutorials / How-to / Reference / Explanation / Archive).

- [Quick Start](docs/tutorials/quick-start.md)
- [Architecture](docs/explanation/architecture.md)
- [Route Reference](docs/reference/routes.md)
- [Blockchain Schema](docs/reference/blockchain-schema.md)
- [Database Schema](docs/reference/database-schema.md)
- [Testing Guide](docs/reference/testing.md)
- [Reporting Subsystem](docs/explanation/reporting-architecture.md)
- [NGPA Rules](docs/ngpa/README.md)

> [!NOTE]
> `docs/archive/SEMANTIC_SEARCH_README.md` is a legacy filename. Search is filtered keyword search over procurement data — no embeddings or vector search.

## Security

- Fortify authentication, 2FA, and password reset flows
- Spatie role/permission authorization on every route group
- Account lockouts, blocked IPs, and login anomaly detection
- Audit logging and document view logging
- Environment-aware CSP and security headers
- Explicit shared Inertia auth props (the full user model is never exposed)

> [!WARNING]
> Seeded demo users and empty default secrets in `.env.example` must be replaced before any shared use. Change seeded passwords immediately.
