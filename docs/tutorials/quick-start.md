# Quick Start

This guide gets a local ProcuChain instance running.

## Prerequisites

- PHP 8.4+
- Composer
- Node.js 18+
- MySQL

## First Boot

1. Install dependencies.

```bash
composer install
npm install
```

2. Configure Laravel.

```bash
copy .env.example .env
php artisan key:generate
```

3. Prepare the database and blockchain.

```bash
php artisan migrate --seed --no-interaction
php artisan multichain:setup --check --no-interaction
php artisan multichain:setup --no-interaction
php artisan workflow:sync-defaults --no-interaction
```

4. Start the application.

```bash
composer run dev
```

## What the Setup Command Does

`php artisan multichain:setup` is the canonical local setup command. It:

- checks RPC connectivity
- generates or reuses role blockchain addresses
- creates required application streams
- grants permissions
- syncs addresses back to Laravel users

## Local Verification

Run these checks after the first boot:

```bash
php artisan multichain:setup --check --no-interaction
php artisan route:list --json --no-interaction
npm run types
npm run test:js
php artisan test --compact
```

## Common Daily Commands

```bash
composer run dev
npm run build
npm run lint
npm run format:check
npm run types
php artisan test --compact --filter=Dashboard
```

## Fresh Blockchain Reset

If you need a brand-new local blockchain with no on-chain data, clear the
chain data directory on your MultiChain node and re-run:

```bash
php artisan multichain:setup --no-interaction
```

## If the UI Looks Stale

Likely fixes:

- keep `composer run dev` running during development
- run `npm run build` if you need a production build
- clear Laravel caches if route/config changes are not reflected

```bash
php artisan optimize:clear
```

## If MultiChain Looks Broken

Start with:

```bash
php artisan multichain:setup --check --no-interaction
```

Then review:

- [Blockchain Schema](../reference/blockchain-schema.md)
