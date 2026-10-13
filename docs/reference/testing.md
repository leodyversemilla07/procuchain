# Testing Guide

This document describes the current verification surface for ProcuChain.

## Test Stack

- Pest on top of PHPUnit
- PHP feature and unit tests under `tests/Feature` and `tests/Unit`
- lightweight TypeScript tests under `tests/js`
- optional browser tests under `tests/Browser`

## Current Defaults

`phpunit.xml` configures the test environment to avoid external dependencies where possible:

- SQLite in-memory database
- `array` cache/session
- `sync` queue
- `array` mailer

## Day-to-Day Commands

### Backend

```bash
php artisan test --compact
php artisan test --compact --filter=Dashboard
php artisan test --compact tests/Feature/ProcurementListControllerTest.php
```

### Frontend and Tooling

```bash
npm run lint
npm run lint:fix
npm run format:check
npm run types
npm run test:js
```

### PHP Formatting

```bash
vendor/bin/pint --dirty --format agent
```

## Important Script Behavior

- `npm run lint` is check-only and must not mutate files
- `npm run lint:fix` is the local autofix command
- `npm run types` regenerates Wayfinder artifacts before type-checking
- `npm run test:js` regenerates Wayfinder artifacts and runs the TypeScript test entrypoints

## CI Workflows

Required GitHub Actions:

- `linter`
- `tests`

Non-blocking workflow:

- browser tests/manual browser workflow

The required linter flow checks:

- Pint in test mode
- Prettier format check
- ESLint
- TypeScript
- JS tests

## Recommended Verification Strategy

Use the smallest relevant test scope first.

Examples:

- controller or middleware change -> targeted feature test file
- service or DTO change -> targeted unit test file
- route or shared frontend type change -> `npm run types` plus targeted PHP test
- docs-only change -> command-level verification such as `php artisan route:list --json --no-interaction`

## Browser Tests

Browser tests live in `tests/Browser`.

They are useful for:

- scoped access checks
- UI workflows that span multiple pages
- confirming browser-only behavior around PDF viewing and navigation

They are not currently part of required CI.

## Areas With Strong Existing Coverage

Representative high-value tests include:

- procurement list/detail flows
- dashboard services and dashboard rendering
- blockchain write job handlers
- workflow definition precedence and sync defaults
- security headers and shared Inertia auth payloads
- reporting and export flows

## Troubleshooting

### TypeScript Cannot Resolve `@/routes` or `@/actions`

Run:

```bash
npm run types
```

Those artifacts are generated as part of the script.

### Tests Hit External Services

Most tests should mock MultiChain- or service-level dependencies. If a test is unexpectedly trying to reach external infrastructure, check the mocked bindings and test environment assumptions first.

### Vite Manifest Errors During Manual Testing

Run one of:

```bash
composer run dev
npm run build
```

## Minimal Safe Verification Set

For a typical feature or refactor PR:

```bash
npm run lint
npm run format:check
npm run types
npm run test:js
php artisan test --compact <relevant-test-file>
```

## Codebase Stats

| Metric | Count |
|--------|-------|
| PHP Files | ~290 |
| TypeScript/React Files | ~440 |
| Controllers | 43 |
| Models | 23 |
| Services | ~70 |
| Tests | 128 |
| Lines of PHP | ~50K |
| Lines of TypeScript | ~67K |
