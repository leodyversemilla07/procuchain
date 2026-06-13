<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'manage settings']);
    Permission::firstOrCreate(['name' => 'view settings']);
    Permission::firstOrCreate(['name' => 'create users']);

    $adminRole = Role::firstOrCreate(['name' => 'admin']);
    $adminRole->givePermissionTo(['manage settings', 'view settings', 'create users']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->regularUser = User::factory()->create();
});

describe('SettingsPolicy', function () {
    describe('view', function () {
        it('allows users with view settings permission', function () {
            expect($this->admin->can('view-settings'))->toBeTrue();
        });

        it('denies users without view settings permission', function () {
            expect($this->regularUser->can('view-settings'))->toBeFalse();
        });

        it('allows users explicitly given view settings permission', function () {
            $viewer = User::factory()->create();
            $viewer->givePermissionTo('view settings');

            expect($viewer->can('view-settings'))->toBeTrue();
        });
    });

    describe('manage', function () {
        it('allows users with manage settings permission', function () {
            expect($this->admin->can('manage-settings'))->toBeTrue();
        });

        it('denies users without manage settings permission', function () {
            expect($this->regularUser->can('manage-settings'))->toBeFalse();
        });

        it('denies users who can only view settings', function () {
            $viewer = User::factory()->create();
            $viewer->givePermissionTo('view settings');

            expect($viewer->can('manage-settings'))->toBeFalse();
        });
    });

    describe('manageWorkflowConfig', function () {
        it('allows users with manage settings permission', function () {
            expect($this->admin->can('manage-workflow-config'))->toBeTrue();
        });

        it('denies users without manage settings permission', function () {
            expect($this->regularUser->can('manage-workflow-config'))->toBeFalse();
        });
    });

    describe('manageStageDocumentConfig', function () {
        it('allows users with manage settings permission', function () {
            expect($this->admin->can('manage-stage-document-config'))->toBeTrue();
        });

        it('denies users without manage settings permission', function () {
            expect($this->regularUser->can('manage-stage-document-config'))->toBeFalse();
        });
    });

    describe('manageUserInvitations', function () {
        it('allows users with create users permission', function () {
            expect($this->admin->can('manage-user-invitations'))->toBeTrue();
        });

        it('denies users without create users permission', function () {
            expect($this->regularUser->can('manage-user-invitations'))->toBeFalse();
        });

        it('allows users explicitly given create users permission', function () {
            $inviter = User::factory()->create();
            $inviter->givePermissionTo('create users');

            expect($inviter->can('manage-user-invitations'))->toBeTrue();
        });

        it('denies users who can only manage settings but not create users', function () {
            $settingsBlockchainRpcClient = User::factory()->create();
            $settingsBlockchainRpcClient->givePermissionTo('manage settings');

            expect($settingsBlockchainRpcClient->can('manage-user-invitations'))->toBeFalse();
        });
    });
});
