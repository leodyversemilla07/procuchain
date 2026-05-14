<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'send notifications']);

    $adminRole = Role::firstOrCreate(['name' => 'admin']);
    $adminRole->givePermissionTo('send notifications');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->regularUser = User::factory()->create();
});

describe('NotificationPolicy', function () {
    describe('view', function () {
        it('allows all authenticated users to view their notifications', function () {
            expect($this->admin->can('view-notifications'))->toBeTrue();
        });

        it('allows regular users without any permissions to view notifications', function () {
            expect($this->regularUser->can('view-notifications'))->toBeTrue();
        });
    });

    describe('manage', function () {
        it('allows users with send notifications permission', function () {
            expect($this->admin->can('manage-notifications'))->toBeTrue();
        });

        it('denies users without send notifications permission', function () {
            expect($this->regularUser->can('manage-notifications'))->toBeFalse();
        });

        it('allows users explicitly given send notifications permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('send notifications');

            expect($user->can('manage-notifications'))->toBeTrue();
        });
    });
});
