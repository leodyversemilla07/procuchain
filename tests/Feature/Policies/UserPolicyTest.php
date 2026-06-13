<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create permissions
    Permission::firstOrCreate(['name' => 'manage users']);
    Permission::firstOrCreate(['name' => 'create users']);
    Permission::firstOrCreate(['name' => 'edit users']);
    Permission::firstOrCreate(['name' => 'delete users']);
    Permission::firstOrCreate(['name' => 'assign roles']);

    // Create roles
    $adminRole = Role::firstOrCreate(['name' => 'admin']);
    $adminRole->givePermissionTo(['manage users', 'create users', 'edit users', 'delete users', 'assign roles']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->regularUser = User::factory()->create();
    $this->targetUser = User::factory()->create();
});

describe('UserPolicy', function () {
    describe('viewAny', function () {
        it('allows users with manage users permission', function () {
            expect($this->admin->can('viewAny', User::class))->toBeTrue();
        });

        it('denies users without manage users permission', function () {
            expect($this->regularUser->can('viewAny', User::class))->toBeFalse();
        });
    });

    describe('view', function () {
        it('allows users to view their own proFile', function () {
            expect($this->regularUser->can('view', $this->regularUser))->toBeTrue();
        });

        it('allows admins to view any user proFile', function () {
            expect($this->admin->can('view', $this->targetUser))->toBeTrue();
        });

        it('denies regular users from viewing other proBlockchainFiles without permission', function () {
            expect($this->regularUser->can('view', $this->targetUser))->toBeFalse();
        });
    });

    describe('create', function () {
        it('allows users with create users permission', function () {
            expect($this->admin->can('create', User::class))->toBeTrue();
        });

        it('denies users without create users permission', function () {
            expect($this->regularUser->can('create', User::class))->toBeFalse();
        });
    });

    describe('update', function () {
        it('allows users to update their own proFile', function () {
            expect($this->regularUser->can('update', $this->regularUser))->toBeTrue();
        });

        it('allows admins to update other users', function () {
            expect($this->admin->can('update', $this->targetUser))->toBeTrue();
        });

        it('denies regular users from updating other users', function () {
            expect($this->regularUser->can('update', $this->targetUser))->toBeFalse();
        });

        it('allows users with edit users permission to update others', function () {
            $editor = User::factory()->create();
            $editor->givePermissionTo('edit users');

            expect($editor->can('update', $this->targetUser))->toBeTrue();
        });
    });

    describe('delete', function () {
        it('allows admins to delete other users', function () {
            expect($this->admin->can('delete', $this->targetUser))->toBeTrue();
        });

        it('prevents users from deleting themselves', function () {
            expect($this->admin->can('delete', $this->admin))->toBeFalse();
        });

        it('denies users without delete users permission', function () {
            expect($this->regularUser->can('delete', $this->targetUser))->toBeFalse();
        });

        it('allows users with delete users permission to delete others but not themselves', function () {
            $deleter = User::factory()->create();
            $deleter->givePermissionTo('delete users');

            expect($deleter->can('delete', $this->targetUser))->toBeTrue();
            expect($deleter->can('delete', $deleter))->toBeFalse();
        });
    });

    describe('restore', function () {
        it('allows users with manage users permission', function () {
            expect($this->admin->can('restore', $this->targetUser))->toBeTrue();
        });

        it('denies users without manage users permission', function () {
            expect($this->regularUser->can('restore', $this->targetUser))->toBeFalse();
        });
    });

    describe('forceDelete', function () {
        it('allows admins to force delete other users', function () {
            expect($this->admin->can('forceDelete', $this->targetUser))->toBeTrue();
        });

        it('prevents users from force deleting themselves', function () {
            expect($this->admin->can('forceDelete', $this->admin))->toBeFalse();
        });

        it('denies users without delete users permission', function () {
            expect($this->regularUser->can('forceDelete', $this->targetUser))->toBeFalse();
        });
    });

    describe('resetPassword', function () {
        it('allows admins to reset other users passwords', function () {
            expect($this->admin->can('resetPassword', $this->targetUser))->toBeTrue();
        });

        it('prevents users from resetting their own password via admin panel', function () {
            expect($this->admin->can('resetPassword', $this->admin))->toBeFalse();
        });

        it('denies users without edit users permission', function () {
            expect($this->regularUser->can('resetPassword', $this->targetUser))->toBeFalse();
        });

        it('allows users with edit users permission to reset other users passwords', function () {
            $editor = User::factory()->create();
            $editor->givePermissionTo('edit users');

            expect($editor->can('resetPassword', $this->targetUser))->toBeTrue();
            expect($editor->can('resetPassword', $editor))->toBeFalse();
        });
    });

    describe('assignRoles', function () {
        it('allows admins to assign roles to other users', function () {
            expect($this->admin->can('assignRoles', $this->targetUser))->toBeTrue();
        });

        it('prevents users from changing their own role', function () {
            expect($this->admin->can('assignRoles', $this->admin))->toBeFalse();
        });

        it('denies users without assign roles permission', function () {
            expect($this->regularUser->can('assignRoles', $this->targetUser))->toBeFalse();
        });

        it('allows users with assign roles permission to assign roles to others', function () {
            $roleBlockchainRpcClient = User::factory()->create();
            $roleBlockchainRpcClient->givePermissionTo('assign roles');

            expect($roleBlockchainRpcClient->can('assignRoles', $this->targetUser))->toBeTrue();
            expect($roleBlockchainRpcClient->can('assignRoles', $roleBlockchainRpcClient))->toBeFalse();
        });
    });

    describe('unlockAccount', function () {
        it('allows users with manage users permission', function () {
            expect($this->admin->can('unlockAccount', $this->targetUser))->toBeTrue();
        });

        it('denies users without manage users permission', function () {
            expect($this->regularUser->can('unlockAccount', $this->targetUser))->toBeFalse();
        });

        it('allows admins to unlock their own account', function () {
            expect($this->admin->can('unlockAccount', $this->admin))->toBeTrue();
        });
    });
});
