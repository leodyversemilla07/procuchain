# ProcuChain

> Blockchain-backed procurement document integrity & workflow automation for BAC offices.

---

## Table of Contents

1. [Overview](#overview)
2. [Features](#features)
3. [Technology Stack](#technology-stack)
4. [Architecture Snapshot](#architecture-snapshot)
5. [Installation](#installation)
6. [Configuration](#configuration)
7. [MultiChain Setup](#multichain-setup)
8. [Testing](#testing)
9. [Running & Development](#running--development)
10. [Production Deployment](#production-deployment)
11. [Security Notes](#security)
12. [Troubleshooting](#troubleshooting)
13. [License](#license)
14. [Contact](#contact)

---

## Overview

ProcuChain is a blockchain-powered document management system for Bids and Awards Committee (BAC) operations. It provides immutable audit trails, controlled access, and automated procurement workflow stages.

## Features

- Secure document storage and management
- Blockchain-based document verification
- Automated workflow for bids and awards processes
- Real-time tracking of procurement status
- Access control and user management
- Audit trail and document history

## Technology Stack

- Laravel 12
- PHP 8.2+
- Blockchain Integration
- MultiChain
- MySQL Database

## Architecture Snapshot

High level components:

- Web/API Layer: Laravel 12 + Inertia React SPA.
- Document Storage: Application storage with blockchain-published metadata hashes (integrity anchor).
- Blockchain Layer: MultiChain streams (documents, status, events, corrections).
- Roles / Addresses: Distinct blockchain addresses per functional role (admin, BAC secretariat, BAC chairman, HOPE).
- Permission Matrix: Config-driven grants for global & per-stream rights.

## Installation

### Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js and npm
- MySQL database
- MultiChain (installed and configured)

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
#
# MULTICHAIN_HOST=localhost
# MULTICHAIN_PORT=7447
# MULTICHAIN_CHAIN=procuchain
# MULTICHAIN_USER=multichainrpc
# MULTICHAIN_PASS=your-rpc-password

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

After the node is up, use the built-in Artisan command to generate or sync blockchain addresses, streams, and permissions. Always perform a dry run first.

Dry run (no changes):

```bash
php artisan multichain:setup --dry-run --strict --json-summary --no-progress
```

Initial real bootstrap (generates all role addresses, creates streams, grants permissions, creates admin user, writes .env):

```bash
php artisan multichain:setup \
	--regenerate-addresses \
	--admin-email=admin@yourdomain.com \
	--strict \
	--json-summary \
	--addresses-json=storage/app/multichain-bootstrap.json \
	--no-progress
```

Common follow-up scenarios:
| Scenario | Command |
|---------|---------|
| Permissions matrix changed only | `php artisan multichain:setup --only-permissions --strict --json-summary --no-progress` |
| Add new streams only | `php artisan multichain:setup --only-streams --json-summary --no-progress` |
| Rotate a subset of roles (example) | `php artisan multichain:setup --regenerate-only=hope,bac_chairman --strict --json-summary --addresses-json=storage/app/multichain-rotate.json --no-progress` |
| Full address rotation (rare) | `php artisan multichain:setup --regenerate-addresses --strict --json-summary --no-progress` |

Key flags:
| Flag | Purpose |
|------|---------|
| `--dry-run` | Preview actions without side effects |
| `--regenerate-addresses` | Force generation of all role addresses |
| `--regenerate-only=role1,role2` | Rotate only specified roles |
| `--only-permissions` / `--only-streams` | Scope operation to one concern |
| `--admin-email=` | Ensure/create admin user and sync its blockchain address |
| `--json-summary` | Emit machine-readable JSON to stdout |
| `--addresses-json=path` | Persist addresses + metadata to a file |
| `--show-addresses` | Disable masking (avoid in shared logs) |
| `--strict` | Fail on incomplete permission matrix or placeholders |
| `--continue-on-error` | Attempt remaining steps after failures |
| `--no-env-write` | Prevent .env mutation (use external secrets manager) |
| `--no-progress` | Cleaner CI logs |

Operational tips:

1. Always run a dry run in CI and parse the JSON summary (fail if any error counts > 0).
2. After generating new addresses, move them into your secret manager and limit exposure.
3. If config was cached, clear it: `php artisan config:clear`.
4. Use selective rotation (`--regenerate-only`) instead of full regeneration where possible.
5. Keep the exported metadata JSON (`--addresses-json`) in a secure, access-controlled location for audit.

Rollback: retain a previous `.env` copy. If rotation causes issues, restore it and re-run `php artisan multichain:setup --only-permissions --strict` to re-apply grants.

Security: Avoid committing or sharing full unmasked addresses. Default output masks them; only use `--show-addresses` locally.

## Configuration

Environment variables (core subset):

| Key                                   | Purpose                    | Example                 |
| ------------------------------------- | -------------------------- | ----------------------- |
| `APP_ENV`                             | Environment name           | `production`            |
| `APP_KEY`                             | Encryption key (generated) | _(generated)_           |
| `DB_CONNECTION`                       | Database driver            | `mysql`                 |
| `DB_HOST` / `DB_PORT`                 | DB host/port               | `127.0.0.1` / `3306`    |
| `DB_DATABASE`                         | Database name              | `procuchain`            |
| `DB_USERNAME` / `DB_PASSWORD`         | DB credentials             | `procuchain` / `secret` |
| `MULTICHAIN_HOST`                     | RPC host                   | `localhost`             |
| `MULTICHAIN_PORT`                     | RPC port                   | `7447`                  |
| `MULTICHAIN_CHAIN`                    | Chain name                 | `procuchain`            |
| `MULTICHAIN_USER` / `MULTICHAIN_PASS` | RPC auth                   | `multichainrpc` / `***` |
| `MULTICHAIN_ADMIN_ADDRESS`            | Generated by setup         | _(generated)_           |
| Other role addresses                  | BAC addresses              | _(generated)_           |

After first successful `multichain:setup`, move addresses into your secret manager and avoid exposing them in logs.

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

## Production Deployment

1. Install dependencies (`composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`).
2. Optimize Laravel (`php artisan config:cache && php artisan route:cache && php artisan view:cache`).
3. Boot MultiChain node & ensure RPC reachable.
4. Run dry run of setup (fail pipeline if errors):
    ```bash
    php artisan multichain:setup --dry-run --strict --json-summary --no-progress
    ```
5. Execute real bootstrap if first time:
    ```bash
    php artisan multichain:setup --regenerate-addresses --admin-email=admin@yourdomain.com --strict --json-summary --no-progress
    ```
6. Store generated addresses securely; optionally re-cache config.
7. Health check (app endpoint + test publish if applicable).

Rolling updates: use `--only-permissions` after permission matrix changes or `--regenerate-only` for partial rotations.

## Troubleshooting

| Symptom                                  | Cause                                | Fix                                                               |
| ---------------------------------------- | ------------------------------------ | ----------------------------------------------------------------- |
| `permission_grant_failure` errors        | Node RPC issue or invalid permission | Validate node logs; retry with `--continue-on-error` then inspect |
| Streams reported as existing but no data | Subscription permissions missing     | Re-run with `--only-permissions --strict`                         |
| Addresses not updating in app            | Config cache stale                   | `php artisan config:clear`                                        |
| `.env` not updated                       | File permissions or `--no-env-write` | Fix permissions or remove flag                                    |
| Masked addresses hide debugging          | Masking default                      | Use `--show-addresses` locally only                               |
| Invalid address in permissions phase     | Placeholder or rotated mid-run       | Regenerate selectively or fix config                              |

## Security

## Usage

1. Access the system through your web browser
2. Login with authorized credentials
3. Follow the intuitive interface to manage documents
4. Track and verify documents using blockchain features

Core practices:

- Role-based access enforcement (app + blockchain).
- Immutable audit anchors via MultiChain streams.
- Principle of least privilege in permission matrix.
- Environment isolation: never reuse production addresses in non-prod.
- Secrets rotation using `--regenerate-only` when feasible.
- Avoid logging full addresses (masking default).

## License

MIT License

## Contact

For support or inquiries, please contact [admin@example.com]
