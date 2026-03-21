# GCP Deployment Guide

This guide describes the dedicated-node deployment path for MultiChain on Google Cloud Platform.

Local development does not use this path. For local work, use the Dockerized bootstrap described in [Quick Start](QUICK_START.md).

## Deployment Goal

Provision a persistent MultiChain network for ProcuChain with:

- a seed/admin node
- one or more RPC nodes for the Laravel app and workers
- at least one witness/independent validator
- a backup or disaster-recovery node

## Before You Deploy

Confirm:

- the Laravel application already runs outside local Docker
- Terraform/GCP infrastructure files are prepared
- RPC access will be restricted to the app hosts
- backups, wallet handling, and operator access are defined

## Application Requirements

After infrastructure is provisioned, the Laravel app still expects the standard bootstrap flow:

```bash
php artisan multichain:setup --check --no-interaction
php artisan multichain:setup --no-interaction
```

That command is responsible for the application-level stream/bootstrap work and should remain the final initialization step from the app side.

## Required Application Streams

Ensure the deployed chain supports the current ProcuChain stream set:

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

## Recommended Deployment Flow

1. Provision the GCP network and compute infrastructure.
2. Install MultiChain on each node.
3. Create the chain on the seed node and join peers.
4. Lock down firewall and RPC rules.
5. Point Laravel to the intended RPC node or load balancer.
6. Run the Laravel bootstrap commands.
7. Verify stream creation, permissions, and app connectivity.

## Laravel Environment

Typical environment values:

```env
MULTICHAIN_CHAIN_NAME=procuchain
MULTICHAIN_RPC_HOST=<rpc-host-or-lb>
MULTICHAIN_RPC_PORT=<rpc-port>
MULTICHAIN_RPC_USERNAME=<rpc-user>
MULTICHAIN_RPC_PASSWORD=<rpc-password>
MULTICHAIN_USE_SSL=false
MULTICHAIN_VERIFY_SSL=false
```

Adjust SSL settings if the RPC endpoint is fronted with TLS.

## Post-Deployment Verification

Run both infrastructure-side and app-side checks:

```bash
multichain-cli <chain-name> getinfo
multichain-cli <chain-name> liststreams
multichain-cli <chain-name> listpermissions
php artisan multichain:setup --check --no-interaction
php artisan route:list --json --no-interaction
```

## Operational Advice

- keep admin/wallet handling separate from day-to-day app hosts
- treat RPC passwords and wallet backups as production secrets
- test recovery and migration procedures before relying on the environment
- prefer private networking and tightly scoped `rpcallowip` rules

## Related Documents

- [MultiChain Node Setup](MULTICHAIN_NODE_SETUP.md)
- [MultiChain Node Migration](MULTICHAIN_NODE_MIGRATION.md)
- [Blockchain Schema](BLOCKCHAIN_SCHEMA.md)
