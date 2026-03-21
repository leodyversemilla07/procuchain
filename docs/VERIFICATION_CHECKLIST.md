# Verification Checklist

This checklist reflects the current codebase and deployment shape.

## Core Local Verification

```bash
php artisan multichain:setup --check --no-interaction
php artisan route:list --json --no-interaction
npm run lint
npm run format:check
npm run types
npm run test:js
php artisan test --compact
```

## Before Merging a Feature

- confirm the relevant PHP test file passes
- confirm `npm run types` passes if route/shared-prop/frontend types changed
- confirm `npm run lint` and `npm run format:check` pass for JS/TS changes
- confirm `vendor/bin/pint --dirty --format agent` was run for PHP changes
- confirm route changes were reflected through Wayfinder generation, not manual edits to generated files

## Before Releasing

- verify MultiChain connectivity with `multichain:setup --check`
- verify dashboards and procurement lists render for expected roles
- verify report generation and export
- verify document upload, verification, and PDF viewing
- verify admin blockchain explorer and workflow config screens

## If You Need Historical Rollout Notes

See:

- `IMPLEMENTATION_SUMMARY.md`
- `IMPLEMENTATION_COMPLETE.txt`

Those files are archival references only.
