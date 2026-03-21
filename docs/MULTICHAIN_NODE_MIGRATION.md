# MultiChain Node Migration

This guide is for persistent MultiChain environments.

If you only need a brand-new local development chain, do not migrate. Reset the local Docker volumes instead:

```bash
docker compose down -v
docker compose up -d --build
php artisan multichain:setup --no-interaction
```

## Use Cases

Use migration procedures when you need to:

- move a persistent node to new infrastructure
- replace failed hardware
- promote a backup node
- rotate RPC hosts without losing chain data or wallet ownership

## Critical Assets

The most sensitive MultiChain assets are:

- `wallet.dat`
- `params.dat`
- the blockchain data directories
- `multichain.conf`

`wallet.dat` is the most critical file because it holds private keys and operational authority.

## Migration Approaches

### Full node migration

Use when the destination does not already have the chain data.

High-level flow:

1. stop the source node
2. back up the full chain data and wallet
3. transfer to the destination securely
4. install the same MultiChain version
5. restore files and review `multichain.conf`
6. start the destination node
7. verify peers, block count, addresses, and permissions
8. update Laravel RPC targets if needed

### Wallet-focused migration

Use when the destination already has the chain but needs wallet/admin authority.

High-level flow:

1. securely export/import the required private key material
2. rescan if needed
3. verify addresses and permissions
4. test an administrative operation carefully

## App-Side Verification After Migration

Always finish with Laravel-side checks:

```bash
php artisan multichain:setup --check --no-interaction
php artisan tinker --execute "dump(app(App\\Services\\Manager::class)->getinfo());"
```

Then verify key role addresses are still represented correctly in the Laravel users table.

## Current Application Stream Set

When validating a migrated node, confirm these streams still exist and are readable:

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

## Safety Notes

- never treat local development reset steps as a production migration strategy
- handle `wallet.dat` as a secret
- verify block height and peer health before switching Laravel traffic
- keep backups encrypted and access-controlled

## Related Documents

- [MultiChain Node Setup](MULTICHAIN_NODE_SETUP.md)
- [GCP Deployment](GCP_DEPLOYMENT.md)
- [Blockchain Schema](BLOCKCHAIN_SCHEMA.md)
