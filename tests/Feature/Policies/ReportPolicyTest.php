<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'view procurement']);
    Permission::firstOrCreate(['name' => 'manage procurements']);
    Permission::firstOrCreate(['name' => 'download documents']);

    $adminRole = Role::firstOrCreate(['name' => 'admin']);
    $adminRole->givePermissionTo(['view procurement', 'manage procurements', 'download documents']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->regularUser = User::factory()->create();
});

describe('ReportPolicy', function () {
    describe('view', function () {
        it('allows users with view procurement permission', function () {
            expect($this->admin->can('view-reports'))->toBeTrue();
        });

        it('denies users without view procurement permission', function () {
            expect($this->regularUser->can('view-reports'))->toBeFalse();
        });

        it('allows users explicitly given view procurement permission', function () {
            $viewer = User::factory()->create();
            $viewer->givePermissionTo('view procurement');

            expect($viewer->can('view-reports'))->toBeTrue();
        });
    });

    describe('generate', function () {
        it('allows users with manage procurements permission', function () {
            expect($this->admin->can('generate-reports'))->toBeTrue();
        });

        it('denies users without manage procurements permission', function () {
            expect($this->regularUser->can('generate-reports'))->toBeFalse();
        });

        it('denies users who can only view procurement', function () {
            $viewer = User::factory()->create();
            $viewer->givePermissionTo('view procurement');

            expect($viewer->can('generate-reports'))->toBeFalse();
        });
    });

    describe('export', function () {
        it('allows users with manage procurements permission', function () {
            expect($this->admin->can('export-reports'))->toBeTrue();
        });

        it('allows users with download documents permission', function () {
            $downloader = User::factory()->create();
            $downloader->givePermissionTo('download documents');

            expect($downloader->can('export-reports'))->toBeTrue();
        });

        it('denies users without either manage procurements or download documents', function () {
            expect($this->regularUser->can('export-reports'))->toBeFalse();
        });

        it('denies users who can only view procurement', function () {
            $viewer = User::factory()->create();
            $viewer->givePermissionTo('view procurement');

            expect($viewer->can('export-reports'))->toBeFalse();
        });
    });
});
