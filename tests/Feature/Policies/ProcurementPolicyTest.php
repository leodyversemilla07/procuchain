<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Permissions required by ProcurementPolicy
    $permissions = [
        'view procurement',
        'create procurement',
        'edit procurement',
        'manage procurements',
        'approve procurement',
        'approve stage transition',
        'publish to blockchain',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    // Roles matching the seeder
    $bacSecretariatRole = Role::firstOrCreate(['name' => 'bac_secretariat']);
    $bacSecretariatRole->givePermissionTo([
        'view procurement', 'create procurement', 'edit procurement',
        'manage procurements', 'publish to blockchain',
    ]);

    $bacChairmanRole = Role::firstOrCreate(['name' => 'bac_chairman']);
    $bacChairmanRole->givePermissionTo([
        'view procurement', 'approve procurement', 'approve stage transition',
    ]);

    $hopeRole = Role::firstOrCreate(['name' => 'hope']);
    $hopeRole->givePermissionTo(['view procurement', 'approve procurement']);

    $adminRole = Role::firstOrCreate(['name' => 'admin']);
    $adminRole->givePermissionTo([
        'view procurement', 'edit procurement', 'manage procurements',
        'approve procurement', 'approve stage transition', 'publish to blockchain',
    ]);

    $this->secretariat = User::factory()->create()->assignRole('bac_secretariat');
    $this->chairman = User::factory()->create()->assignRole('bac_chairman');
    $this->hope = User::factory()->create()->assignRole('hope');
    $this->admin = User::factory()->create()->assignRole('admin');
    $this->guest = User::factory()->create(); // no role, no permissions
});

describe('ProcurementPolicy', function () {
    describe('view-procurement', function () {
        it('allows bac_secretariat to view', function () {
            expect($this->secretariat->can('view-procurement'))->toBeTrue();
        });

        it('allows bac_chairman to view', function () {
            expect($this->chairman->can('view-procurement'))->toBeTrue();
        });

        it('allows hope to view', function () {
            expect($this->hope->can('view-procurement'))->toBeTrue();
        });

        it('allows admin to view', function () {
            expect($this->admin->can('view-procurement'))->toBeTrue();
        });

        it('denies users without view procurement permission', function () {
            expect($this->guest->can('view-procurement'))->toBeFalse();
        });
    });

    describe('create-procurement', function () {
        it('allows bac_secretariat to create', function () {
            expect($this->secretariat->can('create-procurement'))->toBeTrue();
        });

        it('denies bac_chairman from creating', function () {
            expect($this->chairman->can('create-procurement'))->toBeFalse();
        });

        it('denies hope from creating', function () {
            expect($this->hope->can('create-procurement'))->toBeFalse();
        });

        it('denies users with no role from creating', function () {
            expect($this->guest->can('create-procurement'))->toBeFalse();
        });
    });

    describe('archive-procurement', function () {
        it('allows bac_secretariat to archive', function () {
            expect($this->secretariat->can('archive-procurement'))->toBeTrue();
        });

        it('allows admin to archive', function () {
            expect($this->admin->can('archive-procurement'))->toBeTrue();
        });

        it('denies bac_chairman from archiving', function () {
            expect($this->chairman->can('archive-procurement'))->toBeFalse();
        });

        it('denies hope from archiving', function () {
            expect($this->hope->can('archive-procurement'))->toBeFalse();
        });

        it('denies a user with no role from archiving', function () {
            expect($this->guest->can('archive-procurement'))->toBeFalse();
        });
    });

    describe('restore-procurement', function () {
        it('allows bac_secretariat to restore', function () {
            expect($this->secretariat->can('restore-procurement'))->toBeTrue();
        });

        it('allows admin to restore', function () {
            expect($this->admin->can('restore-procurement'))->toBeTrue();
        });

        it('denies bac_chairman from restoring', function () {
            expect($this->chairman->can('restore-procurement'))->toBeFalse();
        });

        it('denies hope from restoring', function () {
            expect($this->hope->can('restore-procurement'))->toBeFalse();
        });
    });

    describe('correct-procurement', function () {
        it('allows bac_secretariat to correct', function () {
            expect($this->secretariat->can('correct-procurement'))->toBeTrue();
        });

        it('allows admin to correct', function () {
            expect($this->admin->can('correct-procurement'))->toBeTrue();
        });

        it('denies bac_chairman from correcting (read-only oversight role)', function () {
            expect($this->chairman->can('correct-procurement'))->toBeFalse();
        });

        it('denies hope from correcting (read-only oversight role)', function () {
            expect($this->hope->can('correct-procurement'))->toBeFalse();
        });
    });

    describe('approve-procurement', function () {
        it('allows bac_chairman to approve', function () {
            expect($this->chairman->can('approve-procurement'))->toBeTrue();
        });

        it('allows hope to approve', function () {
            expect($this->hope->can('approve-procurement'))->toBeTrue();
        });

        it('allows admin to approve', function () {
            expect($this->admin->can('approve-procurement'))->toBeTrue();
        });

        it('denies bac_secretariat from approving (submitter cannot self-approve)', function () {
            expect($this->secretariat->can('approve-procurement'))->toBeFalse();
        });
    });

    describe('publish-procurement', function () {
        it('allows bac_secretariat to publish to blockchain', function () {
            expect($this->secretariat->can('publish-procurement'))->toBeTrue();
        });

        it('allows admin to publish', function () {
            expect($this->admin->can('publish-procurement'))->toBeTrue();
        });

        it('denies bac_chairman from publishing', function () {
            expect($this->chairman->can('publish-procurement'))->toBeFalse();
        });

        it('denies hope from publishing', function () {
            expect($this->hope->can('publish-procurement'))->toBeFalse();
        });
    });

    describe('archive route authorization (HTTP)', function () {
        it('returns 403 when bac_chairman tries to archive', function () {
            $this->actingAs($this->chairman)
                ->post('/procurement/PR-2026-001/archive')
                ->assertForbidden();
        });

        it('returns 403 when hope tries to archive', function () {
            $this->actingAs($this->hope)
                ->post('/procurement/PR-2026-001/archive')
                ->assertForbidden();
        });

        it('redirects unauthenticated users to login on the archive route', function () {
            $this->post('/procurement/PR-2026-001/archive')
                ->assertRedirect('/login');
        });
    });
});
