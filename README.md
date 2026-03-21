<p align="center">
  <img src="public/logo.png" alt="ProcuChain Logo" width="120">
</p>

<h1 align="center">ProcuChain</h1>

<p align="center">
  Blockchain-backed procurement workflow, document integrity, and role-based oversight for BAC operations.
</p>

## Overview

ProcuChain is a Laravel 13 + Inertia React application for managing public procurement workflows with an immutable MultiChain audit trail.

At runtime the platform is split across two persistence layers:

- MySQL stores users, authentication state, permissions, workflow configuration, notifications, audit/security records, queue state, and application settings.
- MultiChain stores procurement metadata, document metadata, workflow status transitions, audit events, correction records, archive flags, and on-chain file storage metadata/content.

The application currently includes:

- role-based dashboards for `admin`, `bac_secretariat`, `bac_chairman`, and `hope`
- configurable workflow and document requirements per procurement mode
- procurement stage upload and completion flows
- blockchain explorer and operational health monitoring
- reports and filtered procurement search
- PDF viewing, document verification, corrections, audit logs, invitations, and security tooling

## Documentation

- [Quick Start](docs/QUICK_START.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Route Reference](docs/ROUTES.md)
- [Blockchain Schema](docs/BLOCKCHAIN_SCHEMA.md)
- [Database Schema](docs/DATABASE_SCHEMA.md)
- [Testing Guide](docs/TESTING.md)
- [Reporting Subsystem](docs/REPORTING_ARCHITECTURE.md)
- [Local and Production MultiChain Setup](docs/MULTICHAIN_NODE_SETUP.md)

Historical implementation notes for the original reporting/search feature are still included in `docs/`, but they are explicitly marked as archival references rather than current architecture guides.

## Stack

- PHP 8.4 / Laravel 13
- Inertia v2 + React 19 + TypeScript
- Tailwind CSS v4
- MySQL
- MultiChain
- Laravel Fortify
- Spatie Laravel Permission
- Laravel Wayfinder
- Pest / PHPUnit

## Local Quick Start

1. Install dependencies.

```bash
composer install
npm install
```

2. Configure the application.

```bash
copy .env.example .env
php artisan key:generate
```

3. Start the local infrastructure.

```bash
docker compose up -d --build
php artisan migrate --seed --no-interaction
php artisan multichain:setup --check --no-interaction
php artisan multichain:setup --no-interaction
php artisan workflow:sync-defaults --no-interaction
```

4. Start the application.

```bash
composer run dev
```

If frontend assets are missing, run `npm run build` or keep `composer run dev` running for Vite.

## Local Blockchain Notes

The local Docker stack provisions MultiChain and HAProxy for development. `php artisan multichain:setup` is the canonical setup command and is responsible for:

- checking RPC connectivity
- creating the required application streams
- generating or reusing role blockchain addresses
- granting stream and chain permissions
- syncing generated addresses back to Laravel users

To reset the local blockchain to a fresh empty chain:

```bash
docker compose down -v
docker compose up -d --build
php artisan multichain:setup --no-interaction
```

This deletes existing local on-chain procurement data.

## Workflow Configuration

Workflow rules are resolved through `App\Services\WorkflowDefinitionService`.

Resolution order:

1. active database configuration from `procurement_workflow_configs` and `stage_document_configs`
2. hardcoded application defaults from enums and requirement services

Use `php artisan workflow:sync-defaults` to materialize missing default configuration rows without overwriting existing admin-managed records.

## Development Commands

```bash
composer run dev
npm run build
npm run lint
npm run lint:fix
npm run format:check
npm run types
npm run test:js
php artisan test --compact
vendor/bin/pint --dirty --format agent
```

## Testing

For day-to-day verification:

```bash
npm run lint
npm run format:check
npm run types
npm run test:js
php artisan test --compact
```

The required CI workflows are:

- `linter`
- `tests`

Browser tests exist in `tests/Browser`, but they are intentionally non-blocking and run separately from required CI.

## Reporting and Search

The reporting module lives under `/reports` and `/search`.

Important: despite legacy file names such as `SEMANTIC_SEARCH_README.md`, the current implementation is filtered keyword search over procurement data. It does not use embeddings or vector search.

## Route Surface

The application currently exposes 157 named routes across:

- public marketing and invitation acceptance pages
- authentication and account recovery
- authenticated shared routes for files, verification, notifications, reporting, and corrections
- role-scoped dashboard and procurement flows
- admin management modules
- user settings and notification preferences

See [Route Reference](docs/ROUTES.md) for the current grouped route map.

## Security

Key application controls include:

- Fortify authentication and 2FA
- Spatie role/permission authorization
- account lockouts and blocked IP management
- audit logging and document view logging
- environment-aware CSP and security headers
- explicit shared Inertia auth props instead of exposing the full user model

## Deployment

Local development uses Dockerized MultiChain.

For dedicated node deployments and migrations, see:

- [MultiChain Node Setup](docs/MULTICHAIN_NODE_SETUP.md)
- [GCP Deployment](docs/GCP_DEPLOYMENT.md)
- [MultiChain Node Migration](docs/MULTICHAIN_NODE_MIGRATION.md)

## License

MIT
