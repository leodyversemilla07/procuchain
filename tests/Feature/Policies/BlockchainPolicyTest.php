<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'view blockchain transactions']);
    Permission::firstOrCreate(['name' => 'publish to blockchain']);

    $adminRole = Role::firstOrCreate(['name' => 'admin']);
    $adminRole->givePermissionTo(['view blockchain transactions', 'publish to blockchain']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->regularUser = User::factory()->create();
});

describe('BlockchainPolicy', function () {
    describe('viewExplorer', function () {
        it('allows users with view blockchain transactions permission', function () {
            expect($this->admin->can('view-blockchain-explorer'))->toBeTrue();
        });

        it('denies users without view blockchain transactions permission', function () {
            expect($this->regularUser->can('view-blockchain-explorer'))->toBeFalse();
        });
    });

    describe('viewTransactions', function () {
        it('allows users with view blockchain transactions permission', function () {
            expect($this->admin->can('view-blockchain-transactions'))->toBeTrue();
        });

        it('denies users without view blockchain transactions permission', function () {
            expect($this->regularUser->can('view-blockchain-transactions'))->toBeFalse();
        });
    });

    describe('viewNetwork', function () {
        it('allows users with view blockchain transactions permission', function () {
            expect($this->admin->can('view-blockchain-network'))->toBeTrue();
        });

        it('denies users without view blockchain transactions permission', function () {
            expect($this->regularUser->can('view-blockchain-network'))->toBeFalse();
        });
    });

    describe('resetCircuitBreaker', function () {
        it('allows users with publish to blockchain permission', function () {
            expect($this->admin->can('reset-blockchain-circuit-breaker'))->toBeTrue();
        });

        it('denies users without publish to blockchain permission', function () {
            expect($this->regularUser->can('reset-blockchain-circuit-breaker'))->toBeFalse();
        });

        it('denies users who can view but not publish to blockchain', function () {
            $viewer = User::factory()->create();
            $viewer->givePermissionTo('view blockchain transactions');

            expect($viewer->can('reset-blockchain-circuit-breaker'))->toBeFalse();
        });
    });

    describe('viewSharedLedger', function () {
        it('allows users with view blockchain transactions permission', function () {
            expect($this->admin->can('view-shared-ledger'))->toBeTrue();
        });

        it('denies users without view blockchain transactions permission', function () {
            expect($this->regularUser->can('view-shared-ledger'))->toBeFalse();
        });
    });
});
