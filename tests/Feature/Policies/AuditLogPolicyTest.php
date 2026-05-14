<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'manage users']);

    $adminRole = Role::firstOrCreate(['name' => 'admin']);
    $adminRole->givePermissionTo('manage users');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->regularUser = User::factory()->create();
});

describe('AuditLogPolicy', function () {
    describe('viewAny', function () {
        it('allows users with manage users permission to view audit logs', function () {
            expect($this->admin->can('view-audit-log'))->toBeTrue();
        });

        it('denies users without manage users permission', function () {
            expect($this->regularUser->can('view-audit-log'))->toBeFalse();
        });

        it('allows users explicitly given manage users permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('manage users');

            expect($user->can('view-audit-log'))->toBeTrue();
        });
    });
});
