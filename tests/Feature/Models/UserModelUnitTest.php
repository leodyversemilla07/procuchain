<?php

use App\Models\User;
use App\Models\UserLoginLog;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

describe('User Model - Configuration', function () {
    test('has correct fillable fields', function () {
        $user = new User;
        $expectedFillable = [
            'name',
            'email',
            'email_verified_at',
            'blockchain_address',
            'email_notifications_enabled',
            'notification_preferences',
        ];

        expect($user->getFillable())->toBe($expectedFillable);
    });

    test('hides sensitive fields', function () {
        $user = User::factory()->create();

        $array = $user->toArray();

        expect($array)->not->toHaveKey('password');
        expect($array)->not->toHaveKey('remember_token');
    });

    test('appends primary_role attribute', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $array = $user->toArray();

        expect($array)->toHaveKey('primary_role');
        expect($array['primary_role'])->toBe('admin');
    });

    test('casts attributes correctly', function () {
        $user = User::factory()->create([
            'account_locked' => true,
            'locked_at' => now(),
            'lock_expires_at' => now()->addMinutes(30),
            'email_notifications_enabled' => true,
        ]);

        expect($user->account_locked)->toBeBool();
        expect($user->locked_at)->toBeInstanceOf(Carbon::class);
        expect($user->lock_expires_at)->toBeInstanceOf(Carbon::class);
        expect($user->email_notifications_enabled)->toBeBool();
    });
});

describe('User Model - Relationships', function () {
    test('has many login logs', function () {
        $user = User::factory()->create();

        UserLoginLog::factory()->count(5)->create([
            'user_id' => $user->id,
        ]);

        expect($user->loginLogs)->toHaveCount(5);
        expect($user->loginLogs->first())->toBeInstanceOf(UserLoginLog::class);
    });

    test('can get recent login logs', function () {
        $user = User::factory()->create();

        UserLoginLog::factory()->count(15)->create([
            'user_id' => $user->id,
            'login_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);

        $recentLogs = $user->recentLoginLogs(5)->get();

        expect($recentLogs)->toHaveCount(5);
    });

    test('can eager load login logs', function () {
        $user = User::factory()->create();

        UserLoginLog::factory()->count(3)->create([
            'user_id' => $user->id,
        ]);

        $loadedUser = User::with('loginLogs')->find($user->id);

        expect($loadedUser->relationLoaded('loginLogs'))->toBeTrue();
        expect($loadedUser->loginLogs)->toHaveCount(3);
    });
});

describe('User Model - Role Checks', function () {
    beforeEach(function () {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'bac_secretariat']);
        Role::firstOrCreate(['name' => 'bac_chairman']);
        Role::firstOrCreate(['name' => 'hope']);
    });

    test('isAdmin returns true for admin role', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');

        expect($user->isAdmin())->toBeTrue();
    });

    test('isAdmin returns false for non-admin', function () {
        $user = User::factory()->create();
        $user->assignRole('bac_secretariat');

        expect($user->isAdmin())->toBeFalse();
    });

    test('isBacSecretariat returns true for bac_secretariat role', function () {
        $user = User::factory()->create();
        $user->assignRole('bac_secretariat');

        expect($user->isBacSecretariat())->toBeTrue();
    });

    test('isBacChairman returns true for bac_chairman role', function () {
        $user = User::factory()->create();
        $user->assignRole('bac_chairman');

        expect($user->isBacChairman())->toBeTrue();
    });

    test('isHope returns true for hope role', function () {
        $user = User::factory()->create();
        $user->assignRole('hope');

        expect($user->isHope())->toBeTrue();
    });

    test('can get primary role', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');

        expect($user->getPrimaryRole())->toBe('admin');
    });

    test('returns null for user with no roles', function () {
        $user = User::factory()->create();

        expect($user->getPrimaryRole())->toBeNull();
    });

    test('can get all assigned roles', function () {
        $user = User::factory()->create();
        $user->assignRole(['admin', 'bac_secretariat']);

        $roles = $user->getAssignedRoles();

        expect($roles)->toBeArray();
        expect($roles)->toContain('admin');
        expect($roles)->toContain('bac_secretariat');
    });
});

describe('User Model - Permission Checks', function () {
    beforeEach(function () {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'bac_secretariat']);

        Permission::firstOrCreate(['name' => 'create procurement']);
        Permission::firstOrCreate(['name' => 'edit procurement']);
        Permission::firstOrCreate(['name' => 'approve procurement']);
        Permission::firstOrCreate(['name' => 'upload documents']);
        Permission::firstOrCreate(['name' => 'view documents']);
        Permission::firstOrCreate(['name' => 'view blockchain transactions']);
        Permission::firstOrCreate(['name' => 'manage users']);
    });

    test('canManageProcurement checks procurement permissions', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('create procurement');

        expect($user->canManageProcurement())->toBeTrue();
    });

    test('canApproveProcurement checks approval permission', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('approve procurement');

        expect($user->canApproveProcurement())->toBeTrue();
    });

    test('canManageDocuments checks document permissions', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('upload documents');

        expect($user->canManageDocuments())->toBeTrue();
    });

    test('canViewDocuments checks view document permission', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('view documents');

        expect($user->canViewDocuments())->toBeTrue();
    });

    test('canAccessBlockchain checks blockchain permissions', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('view blockchain transactions');

        expect($user->canAccessBlockchain())->toBeTrue();
    });

    test('canManageUsers checks user management permissions', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('manage users');

        expect($user->canManageUsers())->toBeTrue();
    });

    test('can get all allowed permissions', function () {
        $user = User::factory()->create();
        $user->givePermissionTo(['create procurement', 'view documents']);

        $permissions = $user->getAllowedPermissions();

        expect($permissions)->toBeArray();
        expect($permissions)->toContain('create procurement');
        expect($permissions)->toContain('view documents');
    });
});

describe('User Model - Account Locking', function () {
    test('lockAccount sets lock attributes correctly', function () {
        $user = User::factory()->create([
            'account_locked' => false,
        ]);

        $user->lockAccount('Multiple failed login attempts', 30);

        $user->refresh();

        expect($user->account_locked)->toBeTrue();
        expect($user->locked_at)->not->toBeNull();
        expect($user->lock_expires_at)->not->toBeNull();
        expect($user->locked_reason)->toBe('Multiple failed login attempts');
    });

    test('isAccountLocked returns false for unlocked account', function () {
        $user = User::factory()->create([
            'account_locked' => false,
        ]);

        expect($user->isAccountLocked())->toBeFalse();
    });

    test('isAccountLocked returns true for locked account', function () {
        $user = User::factory()->create([
            'account_locked' => true,
            'locked_at' => now(),
            'lock_expires_at' => now()->addMinutes(30),
        ]);

        expect($user->isAccountLocked())->toBeTrue();
    });

    test('isAccountLocked auto-unlocks expired locks', function () {
        $user = User::factory()->create([
            'account_locked' => true,
            'locked_at' => now()->subMinutes(60),
            'lock_expires_at' => now()->subMinutes(30),
            'email_notifications_enabled' => false, // Disable to avoid mail sending
        ]);

        $isLocked = $user->isAccountLocked();

        expect($isLocked)->toBeFalse();
        expect($user->fresh()->account_locked)->toBeFalse();
    });

    test('incrementFailedLoginAttempts increases counter', function () {
        $user = User::factory()->create([
            'failed_login_attempts' => 0,
        ]);

        $user->incrementFailedLoginAttempts();

        expect($user->fresh()->failed_login_attempts)->toBe(1);
        expect($user->fresh()->last_failed_login_at)->not->toBeNull();
    });

    test('resetFailedLoginAttempts clears counter', function () {
        $user = User::factory()->create([
            'failed_login_attempts' => 5,
            'last_failed_login_at' => now(),
        ]);

        $user->resetFailedLoginAttempts();

        $user->refresh();

        expect($user->failed_login_attempts)->toBe(0);
        expect($user->last_failed_login_at)->toBeNull();
    });

    test('getRemainingLockTimeAttribute returns 0 for unlocked account', function () {
        $user = User::factory()->create([
            'account_locked' => false,
        ]);

        expect($user->getRemainingLockTimeAttribute())->toBe(0);
    });

    test('getRemainingLockTimeAttribute calculates remaining minutes', function () {
        $user = User::factory()->create([
            'account_locked' => true,
            'locked_at' => now(),
            'lock_expires_at' => now()->addMinutes(30),
        ]);

        $remaining = $user->getRemainingLockTimeAttribute();

        expect($remaining)->toBeGreaterThan(0);
        expect($remaining)->toBeLessThanOrEqual(30);
    });

    test('getLockTimeRemaining returns formatted time string', function () {
        $user = User::factory()->create([
            'account_locked' => true,
            'locked_at' => now(),
            'lock_expires_at' => now()->addMinutes(45),
        ]);

        $timeString = $user->getLockTimeRemaining();

        expect($timeString)->toBeString();
        expect($timeString)->toContain('minute');
    });

    test('getLockTimeRemaining returns null for unlocked account', function () {
        $user = User::factory()->create([
            'account_locked' => false,
        ]);

        expect($user->getLockTimeRemaining())->toBeNull();
    });
});

describe('User Model - Dashboard Access', function () {
    beforeEach(function () {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'bac_secretariat']);
        Role::firstOrCreate(['name' => 'bac_chairman']);
        Role::firstOrCreate(['name' => 'hope']);

        Permission::firstOrCreate(['name' => 'view admin dashboard']);
        Permission::firstOrCreate(['name' => 'view bac-secretariat dashboard']);
        Permission::firstOrCreate(['name' => 'view bac-chairman dashboard']);
        Permission::firstOrCreate(['name' => 'view hope dashboard']);
    });

    test('hasDashboardAccess returns true with dashboard permission', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('view admin dashboard');

        expect($user->hasDashboardAccess())->toBeTrue();
    });

    test('hasDashboardAccess returns false without dashboard permission', function () {
        $user = User::factory()->create();

        expect($user->hasDashboardAccess())->toBeFalse();
    });

    test('getDashboardRoute returns admin dashboard for admin', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');

        expect($user->getDashboardRoute())->toBe('admin.dashboard');
    });

    test('getDashboardRoute returns bac-secretariat dashboard', function () {
        $user = User::factory()->create();
        $user->assignRole('bac_secretariat');

        expect($user->getDashboardRoute())->toBe('bac-secretariat.dashboard');
    });

    test('getDashboardRoute returns bac-chairman dashboard', function () {
        $user = User::factory()->create();
        $user->assignRole('bac_chairman');

        expect($user->getDashboardRoute())->toBe('bac-chairman.dashboard');
    });

    test('getDashboardRoute returns hope dashboard', function () {
        $user = User::factory()->create();
        $user->assignRole('hope');

        expect($user->getDashboardRoute())->toBe('hope.dashboard');
    });

    test('getDashboardRoute returns default dashboard for no role', function () {
        $user = User::factory()->create();

        expect($user->getDashboardRoute())->toBe('dashboard');
    });
});

describe('User Model - Two Factor Authentication', function () {
    test('stores two_factor_secret encrypted', function () {
        $user = User::factory()->create([
            'two_factor_secret' => 'secret123',
        ]);

        // Check database has encrypted value
        $dbValue = DB::table('users')->where('id', $user->id)->value('two_factor_secret');
        expect($dbValue)->not->toBe('secret123');

        // Check model returns decrypted value
        expect($user->two_factor_secret)->toBe('secret123');
    });

    test('casts two_factor_confirmed_at to datetime', function () {
        $user = User::factory()->create([
            'two_factor_confirmed_at' => now(),
        ]);

        expect($user->two_factor_confirmed_at)->toBeInstanceOf(Carbon::class);
    });
});

describe('User Model - Data Integrity', function () {
    test('requires email', function () {
        expect(fn () => User::create([
            'name' => 'Test User',
            'password' => bcrypt('password'),
        ]))->toThrow(QueryException::class);
    });

    test('email must be unique', function () {
        User::factory()->create(['email' => 'test@example.com']);

        expect(fn () => User::factory()->create(['email' => 'test@example.com']))
            ->toThrow(QueryException::class);
    });

    test('password is hashed automatically', function () {
        $user = User::factory()->create([
            'password' => 'plaintext',
        ]);

        expect($user->password)->not->toBe('plaintext');
        expect(strlen($user->password))->toBeGreaterThan(50); // Bcrypt hashes are 60 chars
    });

    test('timestamps are managed automatically', function () {
        $user = User::factory()->create();

        expect($user->created_at)->toBeInstanceOf(Carbon::class);
        expect($user->updated_at)->toBeInstanceOf(Carbon::class);
    });

    test('nullable fields work correctly', function () {
        $user = User::factory()->create([
            'blockchain_address' => null,
            'locked_at' => null,
            'lock_expires_at' => null,
            'locked_reason' => null,
        ]);

        expect($user->blockchain_address)->toBeNull();
        expect($user->locked_at)->toBeNull();
        expect($user->lock_expires_at)->toBeNull();
        expect($user->locked_reason)->toBeNull();
    });
});

describe('User Model - Complex Scenarios', function () {
    beforeEach(function () {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'bac_secretariat']);

        Permission::firstOrCreate(['name' => 'create procurement']);
        Permission::firstOrCreate(['name' => 'approve procurement']);
        Permission::firstOrCreate(['name' => 'view documents']);
    });

    test('user can have multiple roles', function () {
        $user = User::factory()->create();
        $user->assignRole(['admin', 'bac_secretariat']);

        expect($user->hasRole('admin'))->toBeTrue();
        expect($user->hasRole('bac_secretariat'))->toBeTrue();
        expect($user->roles)->toHaveCount(2);
    });

    test('user inherits permissions from roles', function () {
        $role = Role::findByName('admin');
        $role->givePermissionTo(['create procurement', 'approve procurement']);

        $user = User::factory()->create();
        $user->assignRole('admin');

        expect($user->can('create procurement'))->toBeTrue();
        expect($user->can('approve procurement'))->toBeTrue();
    });

    test('direct permissions override role permissions', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('view documents');

        expect($user->can('view documents'))->toBeTrue();
        expect($user->hasDirectPermission('view documents'))->toBeTrue();
    });

    test('locked account maintains role and permissions', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $user->givePermissionTo('create procurement');

        $user->lockAccount('Testing', 30);

        $user->refresh();

        expect($user->account_locked)->toBeTrue();
        expect($user->hasRole('admin'))->toBeTrue();
        expect($user->can('create procurement'))->toBeTrue();
    });
});
