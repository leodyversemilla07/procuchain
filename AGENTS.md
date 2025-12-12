# AGENTS.md

A guide for AI coding agents working on the Procuchain Laravel application.

## Project Overview

Procuchain is a Laravel 12 application that implements a blockchain-based procurement system using Inertia.js with React, Tailwind CSS v4, and Pest for testing. The application integrates with MultiChain for blockchain operations and follows Philippine Government Procurement Policy Board (GPPB) regulations.

### Tech Stack
- PHP 8.3.28
- Laravel 12.42.0
- Inertia.js v2.0.14 (Laravel) & v2.2.21 (React)
- React 19.2.1
- Tailwind CSS v4.1.17
- Pest v4.1.6 (Testing)
- PHPUnit v12.4.4
- Laravel Fortify v1.32.1 (Authentication)
- Laravel MCP v0.4.2 (Model Context Protocol)
- Laravel Wayfinder v0.1.12 (Laravel) & v0.1.7 (Vite)
- Laravel Prompts v0.3.8
- Laravel Pint v1.26.0
- Laravel Sail v1.51.0
- ESLint v9.39.1
- Prettier v3.7.4
- MultiChain (Blockchain integration)
- MySQL (Database)

## Project Structure

```
app/
├── Console/           # Artisan commands
├── Contracts/         # Interfaces
├── DataTransferObjects/ # DTOs
├── Enums/            # PHP enums
├── Http/
│   ├── Controllers/  # Controllers
│   └── Middleware/   # Custom middleware
├── Models/           # Eloquent models
├── Policies/         # Authorization policies
├── Repositories/     # Repository pattern implementations
└── Services/         # Business logic services

resources/
├── js/
│   ├── pages/        # Inertia.js page components (kebab-case)
│   └── components/   # Reusable React components (kebab-case)
├── css/              # Styles
└── views/            # Blade views (minimal, mostly for MCP/auth)

routes/
├── web.php           # Web routes
├── auth.php          # Authentication routes
├── settings.php      # Settings routes
└── console.php       # Console routes

tests/
├── Feature/          # Feature tests (most tests go here)
├── Unit/             # Unit tests
└── Browser/          # Browser tests (Pest v4)
```

## Database Schema

### Key Tables
- **users** - User accounts with blockchain addresses, 2FA, and account lockout features
- **roles** & **permissions** - Role-based access control (Spatie Permission)
- **blocked_ips** - IP blocking for security
- **user_login_logs** - Comprehensive login tracking (IP, device, browser, platform, location)
- **document_views** - Document viewing analytics with metadata
- **notifications** - User notifications
- **push_subscriptions** - Web push notification subscriptions
- **sessions** - User session management
- **jobs**, **job_batches**, **failed_jobs** - Queue system
- **cache**, **cache_locks** - Cache management

### Security Features
- Account lockout mechanism after failed login attempts
- IP blocking and tracking
- Two-factor authentication (2FA) support
- Comprehensive audit logging

### User Roles
The application uses Spatie Permission package with the following primary roles:
- **admin** - Full system access
- **bac_secretariat** - BAC Secretariat role (procurement workflow management)
- **bac_chairman** - BAC Chairman role (approval authority)
- **hope** - Head of Procuring Entity

## Setup Commands

### Initial Setup
```bash
# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Copy environment file (if not exists)
cp .env.example .env

# Generate application key
php artisan key:generate

# Run database migrations
php artisan migrate

# Seed database (optional)
php artisan db:seed
```

### Development
```bash
# Start Laravel development server
php artisan serve

# Start Vite dev server (for frontend hot reload)
npm run dev

# OR use composer script for both
composer run dev

# Build frontend assets for production
npm run build
```

### MultiChain/Blockchain Setup
```bash
# Install MultiChain (if needed)
./scripts/install_procuchain.sh

# Join existing blockchain network
./scripts/join_procuchain.sh
```

## Key Application Features

### Procurement Workflow
The application implements a comprehensive procurement workflow with multiple stages:

**Pre-Procurement Phase**
- Procurement initiation and planning
- Pre-procurement conferences

**Procurement/Bidding Phase**
- Invitation to bid
- Pre-bid conferences
- Bid submission and opening
- Bid evaluation
- Post-qualification
- Award and contract signing

**Post-Procurement Phase**
- Contract implementation
- Delivery tracking
- Acceptance and payment

### Blockchain Integration
- MultiChain-based document immutability
- Blockchain explorer for transparency
- Document verification system
- Audit trail for all procurement activities
- Smart contract support

### Document Management
- Secure file upload with chunking support (configurable threshold)
- Document correction workflows
- Document verification and integrity checks
- PDF viewer integration
- Document viewing analytics

### User Management & Security
- Role-based access control (RBAC)
- Two-factor authentication (2FA)
- Account lockout after failed attempts
- IP blocking system
- Login tracking and analytics
- Session management

### Notifications
- Database notifications
- Email notifications (configurable per user)
- Web push notifications
- Real-time updates for procurement activities

### Dashboard System
Role-specific dashboards with:
- Recent activities
- Procurement statistics
- Priority actions
- Performance analytics
- Configurable cache TTL for performance

## Code Conventions

### General Rules
- Follow **all** existing code conventions in the codebase
- Check sibling files for correct structure, approach, and naming before creating new files
- Use descriptive names: `isRegisteredForDiscounts` not `discount()`
- Check for existing components to reuse before writing new ones
- Stick to existing directory structure - don't create new base folders without approval

### PHP Conventions
- Always use curly braces for control structures, even one-liners
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) {}`
- No empty `__construct()` methods with zero parameters
- Always use explicit return type declarations for methods and functions
- Use appropriate PHP type hints for method parameters
- Prefer PHPDoc blocks over inline comments
- Enum keys should be TitleCase: `FavoritePerson`, `BestLake`, `Monthly`

### Laravel Best Practices
- Use `php artisan make:*` commands to create new files (controllers, models, migrations, etc.)
- Pass `--no-interaction` flag to all Artisan commands
- Use Eloquent relationships with return type hints - prefer over raw queries
- Avoid `DB::`; prefer `Model::query()`
- Use eager loading to prevent N+1 query problems
- Create Form Request classes for validation (not inline in controllers)
- Use queued jobs with `ShouldQueue` for time-consuming operations
- Use `config('app.name')` never `env('APP_NAME')` outside config files
- Prefer named routes and `route()` function for URL generation

### Frontend Conventions (Inertia + React)
- **Use kebab-case for all React file and folder names** (e.g., `user-profile.jsx`, `admin-dashboard/`)
- Inertia components go in `resources/js/pages/`
- Reusable components go in `resources/js/components/`
- Use `Inertia::render()` for server-side routing
- Use `router.visit()` or `<Link>` for client-side navigation
- Use `<Form>` component from Inertia for forms (recommended)
- Check `search-docs` tool for Inertia v2 features: polling, prefetching, deferred props, infinite scrolling
- When using deferred props, add skeleton/loading states

### Tailwind CSS v4
- Use Tailwind classes following existing project conventions
- Use `gap` utilities for spacing (not margins) when listing items
- Support dark mode with `dark:` prefix if existing pages do
- Configuration is CSS-first using `@theme` directive (no `tailwind.config.js`)
- Import Tailwind: `@import "tailwindcss";` (not `@tailwind` directives)
- Use new v4 utilities, not deprecated ones (e.g., `shrink-*` not `flex-shrink-*`)

### Wayfinder (Type-safe Routing)
- Import controller methods from `@/actions/` for type-safe routes
- Prefer named imports for tree-shaking: `import { show } from '@/actions/...'`
- Use `.form()` with forms: `<form {...store.form()}>`
- Use `.url()` to get URL string: `show.url(1)` → `"/posts/1"`
- Run `php artisan wayfinder:generate` after route changes

## Testing Instructions

### Running Tests
```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/ExampleTest.php

# Run tests matching a filter
php artisan test --filter=testName

# Run tests with coverage
php artisan test --coverage
```

### Writing Tests (Pest)
- **All tests must use Pest** (not PHPUnit syntax)
- Use `php artisan make:test --pest <name>` for feature tests
- Add `--unit` flag for unit tests: `php artisan make:test --pest --unit <name>`
- Most tests should be **feature tests**, not unit tests
- **Never remove test files without approval** - they are core to the application
- Test happy paths, failure paths, and edge cases
- Use factories for creating models: `User::factory()->create()`
- Use specific assertions: `assertSuccessful()`, `assertForbidden()`, `assertNotFound()` (not `assertStatus()`)
- Use datasets to simplify tests with duplicated data
- Import Pest helpers: `use function Pest\Laravel\mock;`

### Pest v4 Browser Testing
- Browser tests go in `tests/Browser/`
- Can use Laravel features: `Event::fake()`, `assertAuthenticated()`, `RefreshDatabase`
- Interact with page: `click()`, `type()`, `scroll()`, `select()`, `submit()`
- Check for errors: `assertNoJavascriptErrors()`, `assertNoConsoleLogs()`

### Test Enforcement
**Every change must be programmatically tested.** Write a new test or update an existing test, then run the affected tests to ensure they pass. Run the minimum number of tests needed using filters.

## Build & Deployment

### Building Assets
```bash
# Development build
npm run dev

# Production build
npm run build

# Build and watch
npm run watch
```

### Code Quality
```bash
# Run Laravel Pint (code formatter)
vendor/bin/pint --dirty

# Run ESLint
npm run lint

# Fix ESLint issues
npm run lint:fix

# Check frontend formatting
npm run format:check
```

**Before git add, commit, and push, you MUST run:**
1. `vendor/bin/pint --test` (checks PHP code style - matches CI)
2. `npm run format:check` (checks frontend formatting - matches CI)
3. `npm run lint` (checks frontend code - matches CI)
4. `php artisan test --filter=<relevant>` (runs tests - matches CI)

**These commands match the GitHub Actions workflows** (`.github/workflows/lint.yml` and `.github/workflows/tests.yml`) that run on push/PR to `develop` and `main` branches. Running them locally ensures your code will pass CI checks.

**Workflow Summary:**
- **Lint Workflow** - Runs Pint (check only), frontend formatting check, and ESLint
- **Tests Workflow** - Runs full test suite with Redis service

### Docker/Production
```bash
# Build Docker image
docker build -f Dockerfile.multichain -t procuchain .

# Start services
docker-compose up -d
```

## MCP (Model Context Protocol) Tools

This project uses Laravel MCP. Available tools:
- **Laravel Boost**: Database schema access, Artisan commands, error logs, Tinker execution, semantic documentation search
- Use `list-artisan-commands` tool to check available Artisan parameters
- Use `tinker` tool to execute PHP for debugging
- Use `database-query` tool to read from database
- Use `browser-logs` tool to read browser errors (recent logs only)
- **Use `search-docs` tool before making changes** - gets version-specific Laravel ecosystem documentation

## Documentation Search

Before implementing features, **always search documentation**:
```
search-docs tool with queries like:
- ['rate limiting', 'routing rate limiting', 'routing']
- ['form component', 'useForm helper']
- ['deferred props', 'infinite scroll']
```

Use multiple broad, simple, topic-based queries. Don't include package names in queries.

## Important Files & Conventions

### Configuration
- `config/blockchain.php` - Blockchain/MultiChain settings
- `config/multichain.php` - MultiChain specific configuration
- `config/fortify.php` - Authentication features
- `bootstrap/app.php` - Middleware, exceptions, routing registration
- `bootstrap/providers.php` - Service providers

### Laravel 12 Structure Changes
- **No** `app/Console/Kernel.php` - use `bootstrap/app.php` or `routes/console.php`
- **No** `app/Http/Kernel.php` - middleware in `bootstrap/app.php`
- Commands in `app/Console/Commands/` auto-register
- Middleware registered in `bootstrap/app.php`
- Model casts use `casts()` method (not `$casts` property) - follow existing conventions

### Database
- When modifying columns, include **all** previously defined attributes (or they'll be dropped)
- Use Laravel 11+ native eager loading limits: `$query->latest()->limit(10)`

### URL Generation
- Use `get-absolute-url` tool to ensure correct scheme/domain/port when sharing URLs with users

### Important Routes

**Authentication Routes** (`routes/auth.php`)
- Login/Logout: `/login`, `/logout`
- Password Reset: `/forgot-password`, `/reset-password/{token}`
- Email Verification: `/verify-email`, `/verify-email/{id}/{hash}`
- Two-Factor: `/two-factor-challenge`, `/settings/two-factor*`

**Admin Routes** (prefix: `/admin`)
- Dashboard: `/admin/dashboard`
- User Management: `/admin/users` (CRUD operations)
- Account Lockout: `/admin/accounts/locked`
- Login Logs: `/admin/login-logs` (with blocking/unblocking)
- Blockchain Explorer: `/admin/blockchain-explorer`
- Procurement List: `/admin/procurements-list`

**BAC Secretariat Routes** (prefix: `/bac-secretariat`)
- Dashboard: `/bac-secretariat/dashboard`
- Procurement Initiation: `/bac-secretariat/initiate-procurement`
- Pre-Procurement: `/bac-secretariat/pre-procurement/{pr_number}/{stage}`
- Procurement/Bidding: `/bac-secretariat/procurement/{pr_number}/{stage}`
- Post-Procurement: `/bac-secretariat/post-procurement/{pr_number}/{stage}`
- Document uploads, completion, and validation endpoints

**BAC Chairman Routes** (prefix: `/bac-chairman`)
- Dashboard: `/bac-chairman/dashboard`
- Procurement List: `/bac-chairman/procurements-list`

**HOPE Routes** (prefix: `/hope`)
- Dashboard: `/hope/dashboard`
- Procurement List: `/hope/procurements-list`

**Document Management Routes**
- Document Download: `/files/{fileKey}`
- PDF Viewer: `/pdf-viewer/{fileKey}`
- Document Correction: `/documents/{document}/correct`
- Document Verification: `/documents/{fileKey}/verify`
- Procurement Verification: `/procurement/{pr_number}/verification`

**Settings Routes** (prefix: `/settings`)
- Profile: `/settings/profile`
- Password: `/settings/password`
- Two-Factor: `/settings/two-factor`
- Email Notifications: `/settings/email-notification`
- Push Notifications: `/settings/push-notification`
- Appearance: `/settings/appearance`

**API Routes**
- Procurement Actions: `/api/procurements/{pr_number}/actions`

**Public Routes**
- Home: `/`
- About: `/about`
- Contact: `/contact`
- Team: `/team`
- Workflow: `/workflow`
- Privacy Policy: `/privacy`
- Terms of Service: `/terms`

### Available Artisan Commands

**Custom Commands**
- `multichain:setup` - Setup MultiChain blockchain for procurement
- `smartcontract:setup` - Deploy and manage smart contracts
- `cache:cleanup` - Clean up old cache and session data

**Laravel Boost Commands**
- `boost:mcp` - Start Laravel Boost MCP server
- `boost:install` - Install Laravel Boost
- `boost:update` - Update Laravel Boost guidelines

**Permission Management**
- `permission:create-role` - Create a new role
- `permission:create-permission` - Create a new permission
- `permission:assign-role` - Assign a role to a user
- `permission:show` - Show roles and permissions
- `permission:cache-reset` - Reset permission cache

**Inertia Commands**
- `inertia:start-ssr` - Start SSR server
- `inertia:stop-ssr` - Stop SSR server
- `inertia:check-ssr` - Check SSR server health

**Wayfinder**
- `wayfinder:generate` - Generate type-safe route definitions

**Testing**
- `test` - Run application tests
- `pest:test` - Create new Pest test
- `pest:dataset` - Create new dataset file

## Security & Compliance

- This application implements Philippine GPPB regulations (see `ngpa/` directory)
- Authentication via Laravel Fortify
- Authorization via Laravel Policies (in `app/Policies/`)
- Blockchain operations logged for audit trail
- Follow secure coding practices for government procurement systems

## Common Tasks

### Creating a New Feature
1. Use `search-docs` to understand best practices
2. Use `php artisan make:*` commands to scaffold files
3. Follow existing code conventions in sibling files
4. Write/update tests in `tests/Feature/`
5. Run tests: `php artisan test --filter=<feature>`
6. Format code: `vendor/bin/pint --dirty`
7. Lint frontend: `npm run lint`

### Adding a New Model
```bash
php artisan make:model Post -mfsc --pest
# -m (migration) -f (factory) -s (seeder) -c (controller) --pest (Pest tests)
```

### Adding Form Validation
1. Create Form Request: `php artisan make:request StorePostRequest`
2. Add validation rules and custom error messages
3. Check sibling Form Requests for array vs string validation rules
4. Use in controller: `public function store(StorePostRequest $request)`

### Debugging
- Use `tinker` tool for PHP debugging
- Use `database-query` tool for database queries
- Use `browser-logs` tool for frontend errors
- Check `storage/logs/laravel.log` for backend errors

## Gotchas & Common Issues

### Vite Error
If you see "Unable to locate file in Vite manifest" error:
- Run `npm run build` OR
- Ask user to run `npm run dev` or `composer run dev`

### Frontend Changes Not Showing
User may need to run:
- `npm run build`
- `npm run dev`
- `composer run dev`

### Migration Issues
When modifying columns, **always** include all previous attributes or they'll be lost.

## Contributing Guidelines

### Commit Messages
- Be descriptive and concise
- Use present tense: "Add feature" not "Added feature"
- Reference issue numbers when applicable

### Pull Request Format
- Title: Clear description of changes
- Always run `vendor/bin/pint` and `php artisan test` before committing
- Include test coverage for new features
- Update documentation if needed

### Code Review Checklist
- [ ] Lint workflow passes: `vendor/bin/pint --test` & `npm run format:check` & `npm run lint`
- [ ] Tests pass: `php artisan test`
- [ ] Follows existing conventions
- [ ] Documentation updated (if needed)
- [ ] No security vulnerabilities introduced
- [ ] GitHub Actions workflows will pass (lint.yml & tests.yml)

## Additional Resources

- Laravel Documentation: https://laravel.com/docs
- Inertia.js Documentation: https://inertiajs.com
- Pest Documentation: https://pestphp.com
- React Documentation: https://react.dev
- Tailwind CSS v4: https://tailwindcss.com
- Laravel MCP: https://laravel.com/docs/mcp
- Model Context Protocol: https://modelcontextprotocol.io

---

**Note for Agents**: This is a living document. When in doubt, check existing code conventions, use `search-docs` tool for Laravel ecosystem guidance, and prioritize writing tests to verify your changes work correctly.
