# ProcuChain Developer Guide

**Target Audience:** Developers contributing to or extending ProcuChain  
**Prerequisites:** Familiarity with Laravel, React, and blockchain concepts  
**Last Updated:** November 15, 2025

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Development Workflow](#development-workflow)
3. [Code Standards](#code-standards)
4. [Working with Blockchain](#working-with-blockchain)
5. [Adding New Features](#adding-new-features)
6. [Testing Guidelines](#testing-guidelines)
7. [Common Tasks](#common-tasks)
8. [Troubleshooting](#troubleshooting)

---

## Getting Started

### Initial Setup

```bash
# Clone the repository
git clone https://github.com/leodyversemilla07/procuchain.git
cd procuchain

# Install dependencies
composer install
npm install

# Configure environment
cp .env.example .env
# Edit .env with your database and MultiChain settings

# Generate app key
php artisan key:generate

# Run migrations and seeders
php artisan migrate
php artisan db:seed

# Setup blockchain
php artisan multichain:setup

# Build frontend
npm run build
```

### Development Servers

**Option 1: Concurrent (Recommended)**
```bash
composer run dev
# Runs: PHP server + Queue worker + Vite dev server
```

**Option 2: With SSR**
```bash
composer run dev:ssr
# Runs: PHP server + Queue worker + Logs + Inertia SSR server
```

**Option 3: Manual**
```bash
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Queue worker
php artisan queue:work

# Terminal 3: Vite dev server
npm run dev

# Terminal 4 (optional): SSR server
npm run ssr
```

### IDE Setup

**VS Code Extensions:**
- Laravel Extension Pack
- Intelephense (PHP)
- ESLint
- Prettier
- Tailwind CSS IntelliSense
- Pest Snippets

**PHPStorm:**
- Laravel Idea plugin (paid)
- Pest plugin
- TypeScript support

---

## Development Workflow

### Branch Strategy

```
main (production)
  └── develop (staging)
       └── feature/your-feature-name
       └── bugfix/issue-description
       └── hotfix/critical-fix
```

### Workflow Steps

1. **Create Feature Branch**
   ```bash
   git checkout develop
   git pull origin develop
   git checkout -b feature/procurement-stage-validation
   ```

2. **Make Changes**
   - Follow code standards
   - Write tests
   - Update documentation

3. **Test Changes**
   ```bash
   # Run tests
   php artisan test
   
   # Code formatting
   vendor/bin/pint
   
   # Frontend linting
   npm run lint
   npm run format
   ```

4. **Commit Changes**
   ```bash
   git add .
   git commit -m "feat: add procurement stage validation"
   ```

   **Commit Message Format:**
   - `feat:` - New feature
   - `fix:` - Bug fix
   - `docs:` - Documentation only
   - `style:` - Code formatting
   - `refactor:` - Code restructuring
   - `test:` - Adding tests
   - `chore:` - Maintenance tasks

5. **Push and Create PR**
   ```bash
   git push origin feature/procurement-stage-validation
   # Create pull request on GitHub
   ```

---

## Code Standards

### PHP Standards (PSR-12 + Laravel)

**Follow Laravel Pint Configuration:**
```bash
# Format code
vendor/bin/pint

# Check formatting
vendor/bin/pint --test
```

**Key Rules:**
- Use type declarations for method parameters and return types
- Constructor property promotion for PHP 8.3+
- Prefer enums over string constants
- Use named arguments for clarity
- No empty constructors

**Example:**
```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;

class ProcurementService
{
    public function __construct(
        private MultichainClient $blockchain,
        private CacheStrategyService $cache,
    ) {}

    public function createProcurement(
        string $prNumber,
        StageEnums $stage,
        StatusEnums $status
    ): Procurement {
        // Implementation
    }
}
```

### TypeScript Standards

**Configuration:** `tsconfig.json`, `eslint.config.js`

```bash
# Lint TypeScript
npm run lint

# Format code
npm run format

# Type check
npm run types
```

**Key Rules:**
- Use explicit types (avoid `any`)
- Prefer `interface` over `type` for object shapes
- Use enums for constant values
- Follow React best practices

**Example:**
```typescript
import { router } from '@inertiajs/react'
import { StageEnums } from '@/types'

interface ProcurementFormProps {
  procurement: Procurement
  stages: StageEnums[]
  onSuccess?: () => void
}

export default function ProcurementForm({ 
  procurement, 
  stages,
  onSuccess 
}: ProcurementFormProps) {
  const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    // Implementation
  }

  return (
    <form onSubmit={handleSubmit}>
      {/* Form fields */}
    </form>
  )
}
```

### Component Standards

**React Component Structure:**
```tsx
// 1. Imports
import { router, usePage } from '@inertiajs/react'
import { Button } from '@/components/ui/button'

// 2. Type definitions
interface ComponentProps {
  data: DataType
}

// 3. Component
export default function ComponentName({ data }: ComponentProps) {
  // 4. Hooks
  const { auth } = usePage().props
  
  // 5. State
  const [isOpen, setIsOpen] = useState(false)
  
  // 6. Effects
  useEffect(() => {
    // Side effects
  }, [])
  
  // 7. Handlers
  const handleClick = () => {
    // Handler logic
  }
  
  // 8. Render
  return (
    <div>
      {/* JSX */}
    </div>
  )
}
```

---

## Working with Blockchain

### MultiChain Client Usage

**Service Layer (Preferred):**
```php
use App\Services\Publishers\DocumentPublisher;

class YourService
{
    public function __construct(
        private DocumentPublisher $publisher
    ) {}
    
    public function publishDocument(UploadedFile $file): array
    {
        return $this->publisher->publish(
            prNumber: 'PR-2025-001',
            procurementTitle: 'Office Supplies',
            userAddress: auth()->user()->blockchain_address,
            stage: StageEnums::BIDDING_DOCUMENTS,
            status: 'pending',
            documentType: DocumentTypeEnums::BIDDING_DOCUMENT,
            file: $file,
            uploadedBy: auth()->user()->name,
        );
    }
}
```

**Direct Client Access (Rarely Needed):**
```php
use App\Libraries\MultiChain\Client;

$client = app(Client::class);

// List streams
$streams = $client->listStreams();

// Publish to stream
$txid = $client->publish(
    stream: 'procurement.documents',
    key: 'PR-2025-001',
    data: json_encode($data),
    address: config('multichain.admin_address')
);
```

### Creating a local development blockchain

To keep production data isolated, use the included script `scripts/install_procuchain_dev.sh` to create an isolated development chain:

```bash
chmod +x scripts/install_procuchain_dev.sh
./scripts/install_procuchain_dev.sh
```

The script prints `MULTICHAIN_` variables to add to your `.env` (for example `MULTICHAIN_CHAIN_NAME=procuchain-dev` and `MULTICHAIN_RPC_PORT=7450`). Do not use these values in production.


### Blockchain Status Management

All blockchain writes track status:

```php
// In migration
$table->enum('blockchain_status', ['pending', 'published', 'failed'])
    ->default('pending');
$table->string('blockchain_txid')->nullable();
$table->text('blockchain_error')->nullable();
$table->tinyInteger('blockchain_retry_count')->default(0);
```

**Status Flow:**
1. `pending` - Queued for publishing
2. `published` - Successfully written to blockchain (has txid)
3. `failed` - Publishing failed (has error message)

### Creating New Publishers

```php
<?php

namespace App\Services\Publishers;

use App\Libraries\MultiChain\Client;
use App\Enums\StreamEnums;

class YourPublisher
{
    public function __construct(
        private Client $blockchain
    ) {}
    
    public function publish(array $data): array
    {
        try {
            $txid = $this->blockchain->publish(
                stream: StreamEnums::YOUR_STREAM->value,
                key: $data['key'],
                data: json_encode($data),
                address: $data['user_address']
            );
            
            return [
                'success' => true,
                'txid' => $txid,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
```

---

## Adding New Features

### Adding a New Procurement Stage

1. **Update StageEnums:**
```php
// app/Enums/StageEnums.php
enum StageEnums: string
{
    // ... existing stages
    case YOUR_NEW_STAGE = 'your_new_stage';
    
    public function getDisplayName(): string
    {
        return match ($this) {
            // ... existing cases
            self::YOUR_NEW_STAGE => 'Your New Stage',
        };
    }
    
    // Update getNextStages(), getPhase(), etc.
}
```

2. **Update StatusEnums:**
```php
// app/Enums/StatusEnums.php
enum StatusEnums: string
{
    // ... existing statuses
    case YOUR_NEW_STATUS = 'your_new_status';
}
```

3. **Add Document Requirements:**
```php
// app/Services/StageDocumentRequirements.php
public function getRequiredDocuments(StageEnums $stage): array
{
    return match ($stage) {
        // ... existing cases
        StageEnums::YOUR_NEW_STAGE => [
            'document_type_1',
            'document_type_2',
        ],
    };
}
```

4. **Create Controller Methods:**
```php
// app/Http/Controllers/BacSecretariatController.php
public function yourNewStage(string $prNumber)
{
    return Inertia::render('BacSecretariat/YourNewStage', [
        'procurement' => $procurement,
    ]);
}
```

5. **Add Routes:**
```php
// routes/web.php
Route::get('/bac-secretariat/your-new-stage/{pr_number}', [BacSecretariatController::class, 'yourNewStage'])
    ->name('bac-secretariat.your-new-stage');
```

6. **Create Frontend Page:**
```tsx
// resources/js/pages/bac-secretariat/your-new-stage.tsx
export default function YourNewStage({ procurement }: Props) {
  return (
    <DashboardLayout>
      <h1>Your New Stage</h1>
      {/* Page content */}
    </DashboardLayout>
  )
}
```

7. **Write Tests:**
```php
// tests/Feature/YourNewStageTest.php
it('can access your new stage page', function () {
    $user = User::factory()->create();
    $user->assignRole('bac_secretariat');
    
    $this->actingAs($user)
        ->get('/bac-secretariat/your-new-stage/PR-2025-001')
        ->assertOk();
});
```

### Adding a New Role

1. **Update UserRoleEnums:**
```php
// app/Enums/UserRoleEnums.php
enum UserRoleEnums: string
{
    // ... existing roles
    case YOUR_ROLE = 'your_role';
}
```

2. **Create Role Seeder:**
```php
// database/seeders/DatabaseSeeder.php
Role::create(['name' => 'your_role']);
```

3. **Add Permissions:**
```php
$role = Role::findByName('your_role');
$role->givePermissionTo(['permission1', 'permission2']);
```

4. **Add Routes:**
```php
Route::middleware(['auth', 'role:your_role'])->prefix('your-role')->group(function () {
    Route::get('/dashboard', [YourRoleController::class, 'index'])
        ->name('your-role.dashboard');
});
```

5. **Create Dashboard:**
```tsx
// resources/js/pages/your-role/dashboard.tsx
export default function YourRoleDashboard() {
  return <div>Your Role Dashboard</div>
}
```

---

## Testing Guidelines

### Test Structure

```
tests/
├── Pest.php              # Pest configuration
├── TestCase.php          # Base test case
├── Feature/              # Feature tests
│   ├── Admin/
│   ├── BacSecretariat/
│   └── Auth/
├── Unit/                 # Unit tests
│   ├── Services/
│   └── Enums/
└── Browser/              # Browser tests (Pest v4)
```

### Writing Pest Tests

**Feature Test Example:**
```php
<?php

use App\Models\User;

it('can create procurement', function () {
    $user = User::factory()->create();
    $user->assignRole('bac_secretariat');
    
    $this->actingAs($user)
        ->post('/bac-secretariat/initiate-procurement', [
            'pr_number' => 'PR-2025-001',
            'title' => 'Office Supplies',
            'stage' => 'procurement_initiation',
        ])
        ->assertRedirect();
    
    $this->assertDatabaseHas('procurements', [
        'id' => 'PR-2025-001',
        'title' => 'Office Supplies',
    ]);
});
```

**Unit Test Example:**
```php
<?php

use App\Enums\StageEnums;

it('returns correct next stage', function () {
    $stage = StageEnums::PROCUREMENT_INITIATION;
    $nextStage = $stage->getNextStage();
    
    expect($nextStage)->toBe(StageEnums::PRE_PROCUREMENT_CONFERENCE);
});
```

**Browser Test Example (Pest v4):**
```php
<?php

use App\Models\User;

it('can navigate to dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole('bac_secretariat');
    
    $this->actingAs($user);
    
    $page = visit('/bac-secretariat/dashboard');
    
    $page->assertSee('Dashboard')
        ->assertNoJavascriptErrors();
});
```

### Test Database

Tests use RefreshDatabase trait:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates user', function () {
    $user = User::factory()->create();
    
    expect($user)->toBeInstanceOf(User::class);
});
```

---

## Common Tasks

### Clearing Caches

```bash
# Clear all caches
php artisan optimize:clear

# Individual caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Database Operations

```bash
# Fresh migration with seeding
php artisan migrate:fresh --seed

# Rollback last migration
php artisan migrate:rollback

# Create migration
php artisan make:migration create_your_table

# Create seeder
php artisan make:seeder YourSeeder
```

### Queue Operations

```bash
# Process jobs (development)
php artisan queue:work

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush

# List failed jobs
php artisan queue:failed
```

### Blockchain Operations

```bash
# Setup blockchain
php artisan multichain:setup

# Check permissions
php artisan multichain:permission-status

# Initialize storage
php artisan multichain:initialize-storage

# Reconcile status
php artisan multichain:reconcile-status
```

---

## Troubleshooting

### Common Issues

#### 1. Blockchain Connection Failed

**Error:** "Could not connect to MultiChain node"

**Solutions:**
- Check MultiChain node is running: `multichain-cli procuchain getinfo`
- Verify RPC credentials in `.env`
- Check firewall/network connectivity
- Verify port is correct (default: 7000)

#### 2. Queue Jobs Not Processing

**Error:** Jobs stuck in "pending" status

**Solutions:**
- Start queue worker: `php artisan queue:work`
- Check failed jobs: `php artisan queue:failed`
- Clear queue: `php artisan queue:flush`
- Check database connection

#### 3. Vite Not Building

**Error:** "Manifest not found"

**Solutions:**
- Run `npm install`
- Run `npm run build`
- Check `public/build/manifest.json` exists
- Clear browser cache

#### 4. Inertia Version Mismatch

**Error:** "Inertia version mismatch"

**Solutions:**
- Clear cache: `php artisan optimize:clear`
- Rebuild frontend: `npm run build`
- Hard refresh browser (Ctrl+Shift+R)

#### 5. Permission Denied

**Error:** Storage/cache not writable

**Solutions:**
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Debug Tools

**Laravel Telescope (Optional):**
```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

**Laravel Debugbar (Optional):**
```bash
composer require barryvdh/laravel-debugbar --dev
```

**Xdebug:**
Configure in `php.ini`:
```ini
zend_extension=xdebug
xdebug.mode=debug
xdebug.start_with_request=yes
```

---

## Additional Resources

### Documentation Links

- [Laravel 12 Documentation](https://laravel.com/docs/12.x)
- [Inertia.js Documentation](https://inertiajs.com/)
- [React 19 Documentation](https://react.dev/)
- [Pest Documentation](https://pestphp.com/)
- [MultiChain Documentation](https://www.multichain.com/developers/)
- [Tailwind CSS v4 Documentation](https://tailwindcss.com/)

### Internal Documentation

- [Architecture Documentation](./ARCHITECTURE.md)
- [Procurement Stages](./stages.md)
- [Deployment Guide](./DEPLOYMENT_GUIDE.md)
- [Monitoring Guide](./MONITORING_GUIDE.md)

### Support

- GitHub Issues: https://github.com/leodyversemilla07/procuchain/issues
- Internal Wiki: [Link to internal wiki]
- Team Contact: [Your contact information]

---

**Happy Coding! 🚀**
