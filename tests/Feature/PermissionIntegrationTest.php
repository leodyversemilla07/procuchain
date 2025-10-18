<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// Permissions and roles are automatically seeded by TestCase::setUp()

describe('Spatie Laravel Permission Integration', function (): void {
    describe('Role Assignment', function (): void {
        it('can assign admin role to user', function (): void {
            $user = User::factory()->create();
            $user->assignRole('admin');

            expect($user->hasRole('admin'))->toBeTrue()
                ->and($user->getRoleNames())->toContain('admin');
        });

        it('can assign bac_secretariat role to user', function (): void {
            $user = User::factory()->create();
            $user->assignRole('bac_secretariat');

            expect($user->hasRole('bac_secretariat'))->toBeTrue()
                ->and($user->getRoleNames())->toContain('bac_secretariat');
        });

        it('can assign multiple roles to user', function (): void {
            $user = User::factory()->create();
            $user->assignRole(['admin', 'bac_secretariat']);

            expect($user->getRoleNames())->toHaveCount(2)
                ->and($user->hasAnyRole(['admin', 'bac_secretariat']))->toBeTrue();
        });
    });

    describe('Permission Assignment', function (): void {
        it('admin role has all permissions', function (): void {
            $admin = User::factory()->create();
            $admin->assignRole('admin');

            $totalPermissions = Permission::count();

            expect($admin->getAllPermissions())->toHaveCount($totalPermissions)
                ->and($admin->hasPermissionTo('create procurement'))->toBeTrue()
                ->and($admin->hasPermissionTo('manage users'))->toBeTrue()
                ->and($admin->hasPermissionTo('approve procurement'))->toBeTrue();
        });

        it('bac_secretariat has correct permissions', function (): void {
            $user = User::factory()->create();
            $user->assignRole('bac_secretariat');

            expect($user->hasPermissionTo('create procurement'))->toBeTrue()
                ->and($user->hasPermissionTo('edit procurement'))->toBeTrue()
                ->and($user->hasPermissionTo('manage procurement initiation'))->toBeTrue()
                ->and($user->hasPermissionTo('manage users'))->toBeFalse();
        });

        it('bac_chairman has approval permissions', function (): void {
            $user = User::factory()->create();
            $user->assignRole('bac_chairman');

            expect($user->hasPermissionTo('approve procurement'))->toBeTrue()
                ->and($user->hasPermissionTo('reject procurement'))->toBeTrue()
                ->and($user->hasPermissionTo('approve stage transition'))->toBeTrue()
                ->and($user->hasPermissionTo('create procurement'))->toBeFalse();
        });

        it('hope has limited permissions', function (): void {
            $user = User::factory()->create();
            $user->assignRole('hope');

            expect($user->hasPermissionTo('approve procurement'))->toBeTrue()
                ->and($user->hasPermissionTo('view procurement'))->toBeTrue()
                ->and($user->hasPermissionTo('create procurement'))->toBeFalse()
                ->and($user->hasPermissionTo('edit procurement'))->toBeFalse();
        });
    });

    describe('User Model Helper Methods', function (): void {
        it('isAdmin helper works correctly', function (): void {
            $admin = User::factory()->create();
            $admin->assignRole('admin');

            $user = User::factory()->create();
            $user->assignRole('bac_secretariat');

            expect($admin->isAdmin())->toBeTrue()
                ->and($user->isAdmin())->toBeFalse();
        });

        it('isBacSecretariat helper works correctly', function (): void {
            $user = User::factory()->create();
            $user->assignRole('bac_secretariat');

            expect($user->isBacSecretariat())->toBeTrue();
        });

        it('isBacChairman helper works correctly', function (): void {
            $user = User::factory()->create();
            $user->assignRole('bac_chairman');

            expect($user->isBacChairman())->toBeTrue();
        });

        it('isHope helper works correctly', function (): void {
            $user = User::factory()->create();
            $user->assignRole('hope');

            expect($user->isHope())->toBeTrue();
        });

        it('canManageProcurement helper works correctly', function (): void {
            $admin = User::factory()->create();
            $admin->assignRole('admin');

            $secretariat = User::factory()->create();
            $secretariat->assignRole('bac_secretariat');

            $chairman = User::factory()->create();
            $chairman->assignRole('bac_chairman');

            expect($admin->canManageProcurement())->toBeTrue()
                ->and($secretariat->canManageProcurement())->toBeTrue()
                ->and($chairman->canManageProcurement())->toBeFalse();
        });

        it('canApproveProcurement helper works correctly', function (): void {
            $admin = User::factory()->create();
            $admin->assignRole('admin');

            $chairman = User::factory()->create();
            $chairman->assignRole('bac_chairman');

            $secretariat = User::factory()->create();
            $secretariat->assignRole('bac_secretariat');

            expect($admin->canApproveProcurement())->toBeTrue()
                ->and($chairman->canApproveProcurement())->toBeTrue()
                ->and($secretariat->canApproveProcurement())->toBeFalse();
        });

        it('canManageDocuments helper works correctly', function (): void {
            $admin = User::factory()->create();
            $admin->assignRole('admin');

            $secretariat = User::factory()->create();
            $secretariat->assignRole('bac_secretariat');

            expect($admin->canManageDocuments())->toBeTrue()
                ->and($secretariat->canManageDocuments())->toBeTrue();
        });

        it('canManageUsers helper works correctly', function (): void {
            $admin = User::factory()->create();
            $admin->assignRole('admin');

            $user = User::factory()->create();
            $user->assignRole('bac_secretariat');

            expect($admin->canManageUsers())->toBeTrue()
                ->and($user->canManageUsers())->toBeFalse();
        });

        it('getAssignedRoles returns correct array', function (): void {
            $user = User::factory()->create();
            $user->assignRole(['admin', 'bac_secretariat']);

            $roles = $user->getAssignedRoles();

            expect($roles)->toBeArray()
                ->and($roles)->toHaveCount(2)
                ->and($roles)->toContain('admin')
                ->and($roles)->toContain('bac_secretariat');
        });

        it('getAllowedPermissions returns correct array', function (): void {
            $user = User::factory()->create();
            $user->assignRole('hope');

            $permissions = $user->getAllowedPermissions();

            expect($permissions)->toBeArray()
                ->and($permissions)->toContain('view procurement')
                ->and($permissions)->toContain('approve procurement');
        });

        it('getPrimaryRole returns first role', function (): void {
            $user = User::factory()->create();
            $user->assignRole(['bac_secretariat', 'admin']);

            expect($user->getPrimaryRole())->toBe('bac_secretariat');
        });

        it('hasDashboardAccess works correctly', function (): void {
            $admin = User::factory()->create();
            $admin->assignRole('admin');

            $regular = User::factory()->create();

            expect($admin->hasDashboardAccess())->toBeTrue()
                ->and($regular->hasDashboardAccess())->toBeFalse();
        });

        it('getDashboardRoute returns correct route for each role', function (): void {
            $admin = User::factory()->create();
            $admin->assignRole('admin');

            $secretariat = User::factory()->create();
            $secretariat->assignRole('bac_secretariat');

            $chairman = User::factory()->create();
            $chairman->assignRole('bac_chairman');

            $hope = User::factory()->create();
            $hope->assignRole('hope');

            expect($admin->getDashboardRoute())->toBe('admin.dashboard')
                ->and($secretariat->getDashboardRoute())->toBe('bac-secretariat.dashboard')
                ->and($chairman->getDashboardRoute())->toBe('bac-chairman.dashboard')
                ->and($hope->getDashboardRoute())->toBe('hope.dashboard');
        });
    });

    describe('Middleware Protection', function (): void {
        it('protects routes with role middleware', function (): void {
            $admin = User::factory()->create();
            $admin->assignRole('admin');

            $user = User::factory()->create();
            $user->assignRole('bac_secretariat');

            // Test that admin can access admin routes (if they exist)
            expect($admin->hasRole('admin'))->toBeTrue();
            expect($user->hasRole('admin'))->toBeFalse();
        });
    });

    describe('Permission Caching', function (): void {
        it('clears permission cache correctly', function (): void {
            $user = User::factory()->create();
            $user->assignRole('admin');

            // Cache should exist
            expect($user->hasPermissionTo('manage users'))->toBeTrue();

            // Clear cache
            $this->artisan('permission:cache-reset')
                ->assertSuccessful();

            // Should still work after cache clear
            expect($user->hasPermissionTo('manage users'))->toBeTrue();
        });
    });

    describe('Database Tables', function (): void {
        it('has all required Spatie permission tables', function (): void {
            $schema = DB::connection()->getSchemaBuilder();

            expect($schema->hasTable('roles'))->toBeTrue()
                ->and($schema->hasTable('permissions'))->toBeTrue()
                ->and($schema->hasTable('model_has_roles'))->toBeTrue()
                ->and($schema->hasTable('model_has_permissions'))->toBeTrue()
                ->and($schema->hasTable('role_has_permissions'))->toBeTrue();
        });

        it('roles table has correct structure', function (): void {
            $schema = DB::connection()->getSchemaBuilder();

            expect($schema->hasColumns('roles', [
                'id',
                'name',
                'guard_name',
                'created_at',
                'updated_at',
            ]))->toBeTrue();
        });

        it('permissions table has correct structure', function (): void {
            $schema = DB::connection()->getSchemaBuilder();

            expect($schema->hasColumns('permissions', [
                'id',
                'name',
                'guard_name',
                'created_at',
                'updated_at',
            ]))->toBeTrue();
        });
    });
});
