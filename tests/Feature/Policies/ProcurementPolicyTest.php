<?php

use App\Models\Procurement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create permissions
    Permission::firstOrCreate(['name' => 'create procurement']);
    Permission::firstOrCreate(['name' => 'edit procurement']);
    Permission::firstOrCreate(['name' => 'delete procurement']);
    Permission::firstOrCreate(['name' => 'publish to blockchain']);

    // Create roles
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'bac_secretariat']);
    Role::firstOrCreate(['name' => 'bac_chairman']);
    Role::firstOrCreate(['name' => 'hope']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->bacSecretary = User::factory()->create();
    $this->bacSecretary->assignRole('bac_secretariat');

    $this->bacChairman = User::factory()->create();
    $this->bacChairman->assignRole('bac_chairman');

    $this->hope = User::factory()->create();
    $this->hope->assignRole('hope');

    $this->regularUser = User::factory()->create();

    // Create a test procurement using the factory
    $this->procurement = Procurement::factory()->create();
});

describe('ProcurementPolicy', function () {
    describe('viewAny', function () {
        it('allows all authenticated users to view procurement list', function () {
            expect($this->admin->can('viewAny', Procurement::class))->toBeTrue();
            expect($this->bacSecretary->can('viewAny', Procurement::class))->toBeTrue();
            expect($this->bacChairman->can('viewAny', Procurement::class))->toBeTrue();
            expect($this->hope->can('viewAny', Procurement::class))->toBeTrue();
            expect($this->regularUser->can('viewAny', Procurement::class))->toBeTrue();
        });
    });

    describe('view', function () {
        it('allows all authenticated users to view individual procurements', function () {
            expect($this->admin->can('view', $this->procurement))->toBeTrue();
            expect($this->bacSecretary->can('view', $this->procurement))->toBeTrue();
            expect($this->bacChairman->can('view', $this->procurement))->toBeTrue();
            expect($this->hope->can('view', $this->procurement))->toBeTrue();
            expect($this->regularUser->can('view', $this->procurement))->toBeTrue();
        });
    });

    describe('create', function () {
        it('allows BAC Secretariat to create procurement', function () {
            expect($this->bacSecretary->can('create', Procurement::class))->toBeTrue();
        });

        it('allows users with create procurement permission', function () {
            $this->regularUser->givePermissionTo('create procurement');
            expect($this->regularUser->can('create', Procurement::class))->toBeTrue();
        });

        it('denies users without BAC Secretariat role or permission', function () {
            expect($this->regularUser->can('create', Procurement::class))->toBeFalse();
            expect($this->bacChairman->can('create', Procurement::class))->toBeFalse();
            expect($this->hope->can('create', Procurement::class))->toBeFalse();
        });
    });

    describe('update', function () {
        it('allows BAC Secretariat to update procurement', function () {
            expect($this->bacSecretary->can('update', $this->procurement))->toBeTrue();
        });

        it('allows users with edit procurement permission', function () {
            $this->regularUser->givePermissionTo('edit procurement');
            expect($this->regularUser->can('update', $this->procurement))->toBeTrue();
        });

        it('denies users without BAC Secretariat role or permission', function () {
            expect($this->regularUser->can('update', $this->procurement))->toBeFalse();
            expect($this->bacChairman->can('update', $this->procurement))->toBeFalse();
        });
    });

    describe('delete', function () {
        it('allows admins to delete procurement', function () {
            expect($this->admin->can('delete', $this->procurement))->toBeTrue();
        });

        it('allows users with delete procurement permission', function () {
            $this->regularUser->givePermissionTo('delete procurement');
            expect($this->regularUser->can('delete', $this->procurement))->toBeTrue();
        });

        it('denies non-admin users without permission', function () {
            expect($this->bacSecretary->can('delete', $this->procurement))->toBeFalse();
            expect($this->bacChairman->can('delete', $this->procurement))->toBeFalse();
            expect($this->regularUser->can('delete', $this->procurement))->toBeFalse();
        });
    });

    describe('restore', function () {
        it('allows admins to restore procurement', function () {
            expect($this->admin->can('restore', $this->procurement))->toBeTrue();
        });

        it('denies non-admin users', function () {
            expect($this->bacSecretary->can('restore', $this->procurement))->toBeFalse();
            expect($this->regularUser->can('restore', $this->procurement))->toBeFalse();
        });
    });

    describe('forceDelete', function () {
        it('allows admins to force delete procurement', function () {
            expect($this->admin->can('forceDelete', $this->procurement))->toBeTrue();
        });

        it('denies non-admin users', function () {
            expect($this->bacSecretary->can('forceDelete', $this->procurement))->toBeFalse();
            expect($this->regularUser->can('forceDelete', $this->procurement))->toBeFalse();
        });
    });

    describe('publish', function () {
        it('allows BAC Secretariat to publish to blockchain', function () {
            expect($this->bacSecretary->can('publish', $this->procurement))->toBeTrue();
        });

        it('allows users with publish to blockchain permission', function () {
            $this->regularUser->givePermissionTo('publish to blockchain');
            expect($this->regularUser->can('publish', $this->procurement))->toBeTrue();
        });

        it('denies users without BAC Secretariat role or permission', function () {
            expect($this->regularUser->can('publish', $this->procurement))->toBeFalse();
            expect($this->bacChairman->can('publish', $this->procurement))->toBeFalse();
        });
    });

    describe('manageStages', function () {
        it('allows BAC Secretariat to manage stages', function () {
            expect($this->bacSecretary->can('manageStages', $this->procurement))->toBeTrue();
        });

        it('denies users without BAC Secretariat role', function () {
            expect($this->regularUser->can('manageStages', $this->procurement))->toBeFalse();
            expect($this->bacChairman->can('manageStages', $this->procurement))->toBeFalse();
        });
    });

    describe('approve', function () {
        it('allows BAC Chairman to approve procurement', function () {
            expect($this->bacChairman->can('approve', $this->procurement))->toBeTrue();
        });

        it('allows HOPE to approve procurement', function () {
            expect($this->hope->can('approve', $this->procurement))->toBeTrue();
        });

        it('denies users without BAC Chairman or HOPE role', function () {
            expect($this->regularUser->can('approve', $this->procurement))->toBeFalse();
            expect($this->bacSecretary->can('approve', $this->procurement))->toBeFalse();
        });
    });

    describe('viewBlockchain', function () {
        it('allows all authenticated users to view blockchain data', function () {
            expect($this->admin->can('viewBlockchain', $this->procurement))->toBeTrue();
            expect($this->bacSecretary->can('viewBlockchain', $this->procurement))->toBeTrue();
            expect($this->bacChairman->can('viewBlockchain', $this->procurement))->toBeTrue();
            expect($this->hope->can('viewBlockchain', $this->procurement))->toBeTrue();
            expect($this->regularUser->can('viewBlockchain', $this->procurement))->toBeTrue();
        });
    });

    describe('retryBlockchainPublication', function () {
        it('allows BAC Secretariat to retry failed publications', function () {
            expect($this->bacSecretary->can('retryBlockchainPublication', $this->procurement))->toBeTrue();
        });

        it('allows users with publish to blockchain permission', function () {
            $this->regularUser->givePermissionTo('publish to blockchain');
            expect($this->regularUser->can('retryBlockchainPublication', $this->procurement))->toBeTrue();
        });

        it('denies users without BAC Secretariat role or permission', function () {
            expect($this->regularUser->can('retryBlockchainPublication', $this->procurement))->toBeFalse();
            expect($this->bacChairman->can('retryBlockchainPublication', $this->procurement))->toBeFalse();
        });
    });
});
