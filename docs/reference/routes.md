# Route Reference

This document summarizes the current route surface of ProcuChain.

The route inventory was generated from:

```bash
php artisan route:list --json --no-interaction
```

Current snapshot: 157 routes.

## Route Families

| Family | Approx. Count | Purpose |
| --- | ---: | --- |
| Public and other framework routes | 28 | marketing pages, invitations, auth, error previews |
| Shared authenticated routes | 18 | notifications, files, PDF viewer, verification, corrections, blockchain job polling |
| Reports and search | 4 | reporting page, report generation/export, filtered search |
| Settings | 21 | profile, password, 2FA, appearance, push/email notification settings |
| BAC Secretariat | 37 | dashboard, list/detail views, stage pages, document guides, completion checks, validation |
| BAC Chairman | 3 | dashboard and procurement list/detail views |
| HOPE | 3 | dashboard and procurement list/detail views |
| Admin | 43 | dashboard, explorer, audit log, users, invitations, workflow config, stage docs, security tools |

## Public Routes

Defined in `routes/web.php`:

- `/`
- `/about`
- `/workflow`
- `/team`
- `/contact`
- `/privacy`
- `/terms`
- invitation acceptance routes

## Authentication Routes

Defined in `routes/auth.php`:

- `login`
- `forgot-password`
- `reset-password`
- `logout`

Authentication is Fortify-backed, but the project uses custom controllers for login/session handling and account lockout logging.

## Settings Routes

Defined in `routes/settings.php`:

- `settings/profile`
- `settings/password`
- `settings/push-notification`
- `settings/email-notification`
- `settings/appearance`
- `settings/two-factor`

These are all authenticated routes.

## Shared Authenticated Routes

Available to authenticated users, with additional role checks where needed:

- notifications page and mark-as-read actions
- file download and PDF viewer routes
- procurement blockchain job polling
- procurement/document verification routes
- reporting and search routes
- procurement correction history/check routes

Additional role restrictions apply to:

- procurement correction submission
- document correction pages/actions
- procurement archive/restore actions

## Reports and Search

Current reporting/search endpoints:

- `GET /reports` -> reports page
- `POST /reports/generate`
- `POST /reports/export`
- `POST /search`

Important: the current search implementation is filtered keyword search. The route naming remains from the original feature rollout.

## BAC Secretariat Routes

Prefix: `/bac-secretariat`

Key capabilities:

- dashboard
- procurement list/detail pages
- procurement initiation page and commands
- pre-procurement stage pages and commands
- procurement phase stage pages and commands
- post-procurement stage pages and commands
- document guides, completion checks, and upload validation

Write-heavy blockchain routes are additionally protected by:

- `role:bac_secretariat`
- `throttle:blockchain_writes`

## BAC Chairman Routes

Prefix: `/bac-chairman`

Available routes:

- dashboard
- procurement list
- procurement detail

## HOPE Routes

Prefix: `/hope`

Available routes:

- dashboard
- procurement list
- procurement detail

## Admin Routes

Prefix: `/admin`

Admin modules include:

- dashboard
- audit log
- procurement list/detail views
- user management
- user invitations
- login logs and blocked IP management
- account lockout management
- blockchain explorer and circuit-breaker reset
- workflow configuration
- stage document configuration

## Named Route Patterns

Common route naming conventions:

- `bac-secretariat.procurement.*`
- `bac-chairman.procurements.*`
- `hope.procurements.*`
- `admin.users.*`
- `admin.blockchain.explorer.*`
- `admin.workflow-config.*`
- `admin.stage-documents.*`
- `settings.*`
- `notifications.*`

## Source Files

The route surface is split across:

- `routes/web.php`
- `routes/auth.php`
- `routes/settings.php`

When route changes affect the frontend, regenerate Wayfinder artifacts with the project's existing generation flow instead of editing generated files directly.
