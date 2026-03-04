<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $permissions = [
        'view documents',
        'download documents',
        'upload documents',
        'edit procurement',
        'approve procurement',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $bacSecretariatRole = Role::firstOrCreate(['name' => 'bac_secretariat']);
    $bacSecretariatRole->givePermissionTo([
        'view documents', 'download documents', 'upload documents', 'edit procurement',
    ]);

    $bacChairmanRole = Role::firstOrCreate(['name' => 'bac_chairman']);
    $bacChairmanRole->givePermissionTo([
        'view documents', 'download documents', 'approve procurement',
    ]);

    $hopeRole = Role::firstOrCreate(['name' => 'hope']);
    $hopeRole->givePermissionTo([
        'view documents', 'download documents', 'approve procurement',
    ]);

    $adminRole = Role::firstOrCreate(['name' => 'admin']);
    $adminRole->givePermissionTo([
        'view documents', 'download documents', 'upload documents',
        'edit procurement', 'approve procurement',
    ]);

    $this->secretariat = User::factory()->create()->assignRole('bac_secretariat');
    $this->chairman = User::factory()->create()->assignRole('bac_chairman');
    $this->hope = User::factory()->create()->assignRole('hope');
    $this->admin = User::factory()->create()->assignRole('admin');
    $this->guest = User::factory()->create(); // no role, no permissions
});

describe('DocumentPolicy', function () {
    describe('view-document', function () {
        it('allows bac_secretariat to view documents', function () {
            expect($this->secretariat->can('view-document'))->toBeTrue();
        });

        it('allows bac_chairman to view documents', function () {
            expect($this->chairman->can('view-document'))->toBeTrue();
        });

        it('allows hope to view documents', function () {
            expect($this->hope->can('view-document'))->toBeTrue();
        });

        it('allows admin to view documents', function () {
            expect($this->admin->can('view-document'))->toBeTrue();
        });

        it('denies users with no role from viewing documents', function () {
            expect($this->guest->can('view-document'))->toBeFalse();
        });
    });

    describe('download-document', function () {
        it('allows bac_secretariat to download', function () {
            expect($this->secretariat->can('download-document'))->toBeTrue();
        });

        it('allows bac_chairman to download', function () {
            expect($this->chairman->can('download-document'))->toBeTrue();
        });

        it('allows hope to download', function () {
            expect($this->hope->can('download-document'))->toBeTrue();
        });

        it('allows admin to download', function () {
            expect($this->admin->can('download-document'))->toBeTrue();
        });

        it('denies users with no role from downloading', function () {
            expect($this->guest->can('download-document'))->toBeFalse();
        });
    });

    describe('upload-document', function () {
        it('allows bac_secretariat to upload', function () {
            expect($this->secretariat->can('upload-document'))->toBeTrue();
        });

        it('allows admin to upload', function () {
            expect($this->admin->can('upload-document'))->toBeTrue();
        });

        it('denies bac_chairman from uploading (read-only oversight role)', function () {
            expect($this->chairman->can('upload-document'))->toBeFalse();
        });

        it('denies hope from uploading (read-only oversight role)', function () {
            expect($this->hope->can('upload-document'))->toBeFalse();
        });
    });

    describe('correct-document', function () {
        it('allows bac_secretariat to correct documents', function () {
            expect($this->secretariat->can('correct-document'))->toBeTrue();
        });

        it('allows bac_chairman to correct documents (oversight approval role)', function () {
            expect($this->chairman->can('correct-document'))->toBeTrue();
        });

        it('allows hope to correct documents (oversight approval role)', function () {
            expect($this->hope->can('correct-document'))->toBeTrue();
        });

        it('allows admin to correct documents', function () {
            expect($this->admin->can('correct-document'))->toBeTrue();
        });

        it('denies users with no role from correcting documents', function () {
            expect($this->guest->can('correct-document'))->toBeFalse();
        });
    });

    describe('download route authorization (HTTP)', function () {
        it('redirects unauthenticated users to login when accessing a file', function () {
            $this->get('/files/some-file-key.pdf')
                ->assertRedirect('/login');
        });

        it('returns 403 when a user without download permission tries to download', function () {
            $this->actingAs($this->guest)
                ->get('/files/some-file-key.pdf')
                ->assertForbidden();
        });
    });
});
