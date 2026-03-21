# MultiChain Node Setup

This guide covers the current MultiChain setup model used by ProcuChain.

## Which Setup Path to Use

### Local development

Use the Dockerized local stack documented in [Quick Start](QUICK_START.md):

```bash
docker compose up -d --build
php artisan multichain:setup --no-interaction
```

### Dedicated environments

Use this guide when you are provisioning persistent MultiChain nodes outside local Docker development.

## Current Application Expectations

The Laravel app expects:

- a reachable MultiChain RPC endpoint
- role-scoped blockchain addresses for `admin`, `bac_secretariat`, `bac_chairman`, and `hope`
- the application streams created and subscribed
- permissions aligned with `config/multichain.php`

The canonical bootstrap command remains:

```bash
php artisan multichain:setup --check --no-interaction
php artisan multichain:setup --no-interaction
```

## Application Streams

The app-level streams currently used by ProcuChain are:

- `procurement.metadata`
- `procurement.documents`
- `procurement.status`
- `procurement.events`
- `procurement.corrections`
- `procurement.metadata.corrections`
- `procurement.archive`
- `file.data`
- `file.metadata`
- `file.chunks`

## Suggested Production Topology

Minimum recommended roles:

- one administrative/seed node
- one app-facing RPC node
- one secondary app/worker node
- one witness or independent validator node
- one backup/disaster-recovery node

## Setup Sequence

1. Install MultiChain on each node.
2. Create or join the target chain.
3. Restrict RPC access to application hosts and operators only.
4. Point Laravel to the intended RPC endpoint.
5. Run:

```bash
php artisan multichain:setup --check --no-interaction
php artisan multichain:setup --no-interaction
```

6. Verify:

- chain is reachable
- streams exist
- role addresses exist
- permissions are applied
- Laravel users received blockchain addresses

## Laravel Configuration

The application reads MultiChain settings from `config/multichain.php`.

Typical environment values include:

- `MULTICHAIN_CHAIN_NAME`
- `MULTICHAIN_RPC_HOST`
- `MULTICHAIN_RPC_PORT`
- `MULTICHAIN_RPC_USERNAME`
- `MULTICHAIN_RPC_PASSWORD`
- `MULTICHAIN_USE_SSL`
- `MULTICHAIN_VERIFY_SSL`

Do not use `env()` directly outside config files.

## Verification Commands

```bash
php artisan multichain:setup --check --no-interaction
php artisan tinker --execute "dump(app(App\\Services\\Manager::class)->getinfo());"
```

Direct CLI checks on a node:

```bash
multichain-cli <chain-name> getinfo
multichain-cli <chain-name> liststreams
multichain-cli <chain-name> getaddresses
multichain-cli <chain-name> listpermissions
```

## Operational Notes

- local development can be reset freely with `docker compose down -v`
- dedicated nodes should be migrated, backed up, and rotated carefully instead of destroyed
- after a new chain is created, application dashboards will remain empty until new procurement data is written to the chain

## Related Documents

- [Quick Start](QUICK_START.md)
- [Blockchain Schema](BLOCKCHAIN_SCHEMA.md)
- [GCP Deployment](GCP_DEPLOYMENT.md)
- [MultiChain Node Migration](MULTICHAIN_NODE_MIGRATION.md)
