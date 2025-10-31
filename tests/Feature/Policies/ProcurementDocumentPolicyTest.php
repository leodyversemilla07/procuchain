<?php

use App\Models\ProcurementDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create permissions
    Permission::firstOrCreate(['name' => 'upload documents']);
    Permission::firstOrCreate(['name' => 'delete documents']);
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

    $this->regularUser = User::factory()->create();

    // Create test documents using the factory
    $this->confirmedDocument = ProcurementDocument::factory()->create([
        'blockchain_status' => 'confirmed',
    ]);

    $this->failedDocument = ProcurementDocument::factory()->create([
        'blockchain_status' => 'failed',
    ]);
});

describe('ProcurementDocumentPolicy', function () {
    describe('viewAny', function () {
        it('allows all authenticated users to view documents', function () {
            expect($this->admin->can('viewAny', ProcurementDocument::class))->toBeTrue();
            expect($this->bacSecretary->can('viewAny', ProcurementDocument::class))->toBeTrue();
            expect($this->bacChairman->can('viewAny', ProcurementDocument::class))->toBeTrue();
            expect($this->regularUser->can('viewAny', ProcurementDocument::class))->toBeTrue();
        });
    });

    describe('view', function () {
        it('allows all authenticated users to view individual documents', function () {
            expect($this->admin->can('view', $this->confirmedDocument))->toBeTrue();
            expect($this->bacSecretary->can('view', $this->confirmedDocument))->toBeTrue();
            expect($this->bacChairman->can('view', $this->confirmedDocument))->toBeTrue();
            expect($this->regularUser->can('view', $this->confirmedDocument))->toBeTrue();
        });
    });

    describe('create', function () {
        it('allows BAC Secretariat to upload documents', function () {
            expect($this->bacSecretary->can('create', ProcurementDocument::class))->toBeTrue();
        });

        it('allows users with upload documents permission', function () {
            $this->regularUser->givePermissionTo('upload documents');
            expect($this->regularUser->can('create', ProcurementDocument::class))->toBeTrue();
        });

        it('denies users without BAC Secretariat role or permission', function () {
            expect($this->regularUser->can('create', ProcurementDocument::class))->toBeFalse();
            expect($this->bacChairman->can('create', ProcurementDocument::class))->toBeFalse();
        });
    });

    describe('update', function () {
        it('denies all users from updating documents directly', function () {
            // Documents cannot be updated directly - only corrected via blockchain
            expect($this->admin->can('update', $this->confirmedDocument))->toBeFalse();
            expect($this->bacSecretary->can('update', $this->confirmedDocument))->toBeFalse();
            expect($this->bacChairman->can('update', $this->confirmedDocument))->toBeFalse();
            expect($this->regularUser->can('update', $this->confirmedDocument))->toBeFalse();
        });
    });

    describe('delete', function () {
        it('allows admins to delete documents', function () {
            expect($this->admin->can('delete', $this->confirmedDocument))->toBeTrue();
        });

        it('allows users with delete documents permission', function () {
            $this->regularUser->givePermissionTo('delete documents');
            expect($this->regularUser->can('delete', $this->confirmedDocument))->toBeTrue();
        });

        it('denies non-admin users without permission', function () {
            expect($this->bacSecretary->can('delete', $this->confirmedDocument))->toBeFalse();
            expect($this->bacChairman->can('delete', $this->confirmedDocument))->toBeFalse();
            expect($this->regularUser->can('delete', $this->confirmedDocument))->toBeFalse();
        });
    });

    describe('restore', function () {
        it('allows admins to restore documents', function () {
            expect($this->admin->can('restore', $this->confirmedDocument))->toBeTrue();
        });

        it('denies non-admin users', function () {
            expect($this->bacSecretary->can('restore', $this->confirmedDocument))->toBeFalse();
            expect($this->regularUser->can('restore', $this->confirmedDocument))->toBeFalse();
        });
    });

    describe('forceDelete', function () {
        it('allows admins to force delete documents', function () {
            expect($this->admin->can('forceDelete', $this->confirmedDocument))->toBeTrue();
        });

        it('denies non-admin users', function () {
            expect($this->bacSecretary->can('forceDelete', $this->confirmedDocument))->toBeFalse();
            expect($this->regularUser->can('forceDelete', $this->confirmedDocument))->toBeFalse();
        });
    });

    describe('download', function () {
        it('allows all authenticated users to download documents', function () {
            expect($this->admin->can('download', $this->confirmedDocument))->toBeTrue();
            expect($this->bacSecretary->can('download', $this->confirmedDocument))->toBeTrue();
            expect($this->bacChairman->can('download', $this->confirmedDocument))->toBeTrue();
            expect($this->regularUser->can('download', $this->confirmedDocument))->toBeTrue();
        });
    });

    describe('correct', function () {
        it('allows admins to correct documents', function () {
            expect($this->admin->can('correct', $this->confirmedDocument))->toBeTrue();
        });

        it('allows BAC Chairman to correct documents', function () {
            expect($this->bacChairman->can('correct', $this->confirmedDocument))->toBeTrue();
        });

        it('allows BAC Secretariat to correct documents', function () {
            expect($this->bacSecretary->can('correct', $this->confirmedDocument))->toBeTrue();
        });

        it('denies regular users without required roles', function () {
            expect($this->regularUser->can('correct', $this->confirmedDocument))->toBeFalse();
        });
    });

    describe('viewCorrectionHistory', function () {
        it('allows all authenticated users to view correction history', function () {
            expect($this->admin->can('viewCorrectionHistory', $this->confirmedDocument))->toBeTrue();
            expect($this->bacSecretary->can('viewCorrectionHistory', $this->confirmedDocument))->toBeTrue();
            expect($this->bacChairman->can('viewCorrectionHistory', $this->confirmedDocument))->toBeTrue();
            expect($this->regularUser->can('viewCorrectionHistory', $this->confirmedDocument))->toBeTrue();
        });
    });

    describe('publish', function () {
        it('allows BAC Secretariat to publish documents', function () {
            expect($this->bacSecretary->can('publish', $this->confirmedDocument))->toBeTrue();
        });

        it('allows users with publish to blockchain permission', function () {
            $this->regularUser->givePermissionTo('publish to blockchain');
            expect($this->regularUser->can('publish', $this->confirmedDocument))->toBeTrue();
        });

        it('denies users without BAC Secretariat role or permission', function () {
            expect($this->regularUser->can('publish', $this->confirmedDocument))->toBeFalse();
            expect($this->bacChairman->can('publish', $this->confirmedDocument))->toBeFalse();
        });
    });

    describe('retryPublication', function () {
        it('allows BAC Secretariat to retry failed publications', function () {
            expect($this->bacSecretary->can('retryPublication', $this->failedDocument))->toBeTrue();
        });

        it('allows users with publish to blockchain permission to retry failed publications', function () {
            $this->regularUser->givePermissionTo('publish to blockchain');
            expect($this->regularUser->can('retryPublication', $this->failedDocument))->toBeTrue();
        });

        it('denies retry if document status is not failed', function () {
            expect($this->bacSecretary->can('retryPublication', $this->confirmedDocument))->toBeFalse();
            expect($this->admin->can('retryPublication', $this->confirmedDocument))->toBeFalse();
        });

        it('denies users without BAC Secretariat role or permission even for failed documents', function () {
            expect($this->regularUser->can('retryPublication', $this->failedDocument))->toBeFalse();
            expect($this->bacChairman->can('retryPublication', $this->failedDocument))->toBeFalse();
        });
    });
});
