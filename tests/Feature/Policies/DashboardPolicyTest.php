<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'view admin dashboard']);
    Permission::firstOrCreate(['name' => 'view bac-secretariat dashboard']);
    Permission::firstOrCreate(['name' => 'view bac-chairman dashboard']);
    Permission::firstOrCreate(['name' => 'view hope dashboard']);

    $adminRole = Role::firstOrCreate(['name' => 'admin']);
    $adminRole->givePermissionTo('view admin dashboard');

    $bacSecRole = Role::firstOrCreate(['name' => 'bac_secretariat']);
    $bacSecRole->givePermissionTo('view bac-secretariat dashboard');

    $bacChairRole = Role::firstOrCreate(['name' => 'bac_chairman']);
    $bacChairRole->givePermissionTo('view bac-chairman dashboard');

    $hopeRole = Role::firstOrCreate(['name' => 'hope']);
    $hopeRole->givePermissionTo('view hope dashboard');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->bacSecretariat = User::factory()->create();
    $this->bacSecretariat->assignRole('bac_secretariat');

    $this->bacChairman = User::factory()->create();
    $this->bacChairman->assignRole('bac_chairman');

    $this->hope = User::factory()->create();
    $this->hope->assignRole('hope');

    $this->regularUser = User::factory()->create();
});

describe('DashboardPolicy', function () {
    describe('viewAdmin', function () {
        it('allows users with view admin dashboard permission', function () {
            expect($this->admin->can('view-admin-dashboard'))->toBeTrue();
        });

        it('denies users without view admin dashboard permission', function () {
            expect($this->regularUser->can('view-admin-dashboard'))->toBeFalse();
        });

        it('denies bac secretariat from admin dashboard', function () {
            expect($this->bacSecretariat->can('view-admin-dashboard'))->toBeFalse();
        });
    });

    describe('viewBacSecretariat', function () {
        it('allows users with view bac-secretariat dashboard permission', function () {
            expect($this->bacSecretariat->can('view-bac-secretariat-dashboard'))->toBeTrue();
        });

        it('denies users without view bac-secretariat dashboard permission', function () {
            expect($this->regularUser->can('view-bac-secretariat-dashboard'))->toBeFalse();
        });

        it('allows admin to access bac secretariat dashboard via Gate::before bypass', function () {
            // Admin bypasses all gates via Gate::before — this is intentional
            expect($this->admin->can('view-bac-secretariat-dashboard'))->toBeTrue();
        });
    });

    describe('viewBacChairman', function () {
        it('allows users with view bac-chairman dashboard permission', function () {
            expect($this->bacChairman->can('view-bac-chairman-dashboard'))->toBeTrue();
        });

        it('denies users without view bac-chairman dashboard permission', function () {
            expect($this->regularUser->can('view-bac-chairman-dashboard'))->toBeFalse();
        });
    });

    describe('viewHope', function () {
        it('allows users with view hope dashboard permission', function () {
            expect($this->hope->can('view-hope-dashboard'))->toBeTrue();
        });

        it('denies users without view hope dashboard permission', function () {
            expect($this->regularUser->can('view-hope-dashboard'))->toBeFalse();
        });
    });
});
