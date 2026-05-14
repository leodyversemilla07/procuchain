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

describe('LoginLogPolicy', function () {
    describe('viewAny', function () {
        it('allows users with manage users permission to view login logs', function () {
            expect($this->admin->can('view-login-logs'))->toBeTrue();
        });

        it('denies users without manage users permission', function () {
            expect($this->regularUser->can('view-login-logs'))->toBeFalse();
        });
    });

    describe('manageBlockedIps', function () {
        it('allows users with manage users permission to manage blocked IPs', function () {
            expect($this->admin->can('manage-blocked-ips'))->toBeTrue();
        });

        it('denies users without manage users permission', function () {
            expect($this->regularUser->can('manage-blocked-ips'))->toBeFalse();
        });
    });
});
