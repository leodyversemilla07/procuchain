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
- **Cloud Storage Integration**: Secure document storage using AWS S3-compatible services (DigitalOcean Spaces)
- **Email Notifications**: Automated email alerts for workflow transitions and updates
- **Push Notifications**: Real-time browser notifications using WebPush/VAPID
- **Responsive Interface**: Modern React-based SPA with Inertia.js for seamless user experience

## Technology Stack

- **Backend**: Laravel 12 with PHP 8.2+
- **Frontend**: React 19 with Inertia.js v2 for SPA experience
- **Database**: MySQL with database-driven sessions, cache, and queue
- **Blockchain**: MultiChain for immutable document integrity and audit trails
- **File Storage**: AWS S3-compatible storage (DigitalOcean Spaces)
- **Styling**: Tailwind CSS v4 for responsive design
- **Build Tools**: Vite for fast frontend asset compilation
- **Testing**: Pest v3 for expressive PHP testing
- **Code Quality**: Laravel Pint for consistent code formatting
- **Notifications**:
    - SMTP email notifications
    - WebPush browser notifications with VAPID
- **Development**:
    - Hot module replacement with Vite
    - Database-driven development stack

## Requirements

- PHP 8.2 or higher
- Composer
- Node.js and npm
- MySQL 8 (or compatible)
- MultiChain node accessible via RPC (for blockchain features)
- SMTP service (for email notifications)
- Optional: AWS S3–compatible storage (e.g., DigitalOcean Spaces) for file storage

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
- SMTP email service (for notifications)
- AWS S3-compatible storage or DigitalOcean Spaces (for file storage)

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

- **File Storage**: Uses AWS S3-compatible storage (DigitalOcean Spaces)

    ```bash
    AWS_ACCESS_KEY_ID=your_access_key_id
    AWS_SECRET_ACCESS_KEY=your_secret_access_key
    AWS_DEFAULT_REGION=sgp1
    AWS_BUCKET=your_bucket_name
    AWS_ENDPOINT=https://sgp1.digitaloceanspaces.com
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

1. Access the system through your web browser
2. Login with authorized credentials
3. Follow the intuitive interface to manage documents
4. Track and verify documents using blockchain features

## Project Structure

Project root snapshot:

- app/ — Laravel application code (Controllers, Models, Services, Jobs, Console, etc.)
- bootstrap/ — Framework bootstrap files
- config/ — Application configuration (includes multichain.php)
- database/ — Migrations, seeders, factories
- public/ — Web root (index.php, built assets)
- resources/
  - css/app.css — Main stylesheet (Tailwind)
  - js/app.tsx — React SPA entry (Inertia)
  - js/ssr.tsx — SSR entry for Inertia
  - views/ — Blade templates (if any)
- routes/ — web.php, api.php route definitions
- scripts/ — Project scripts/utilities
- tests/ — Pest/PHPUnit tests
- vendor/ — Composer dependencies
- node_modules/ — Node dependencies
- vite.config.ts — Vite configuration (client + SSR)
- package.json — Frontend dependencies and scripts
- composer.json — PHP dependencies and composer scripts
- docker-compose.yml — Local DB and phpMyAdmin services
- Procfile — Process definitions for deployment (e.g., Heroku)

## Security

Core practices:

- Role-based access enforcement (app + blockchain).
- Immutable audit anchors via MultiChain streams.
- Principle of least privilege in permission matrix.
- Environment isolation: never reuse production addresses in non-prod.
- Secure storage of blockchain addresses in `.env` file.
- Addresses are displayed masked by default (first 6 + last 6 characters) for security.
- Regular backup of `.env` file and MultiChain configuration.
- Proper MultiChain node security and access controls.

## License

MIT License

## Contact

For support or inquiries, please contact [leodyversemilla07@gmail.com]
