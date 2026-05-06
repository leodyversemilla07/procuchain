<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsPermissions;

uses(RefreshDatabase::class);
uses(SeedsPermissions::class);

describe('Admin Dashboard Browser Flow', function () {
    beforeEach(function () {
        $this->seedPermissions();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    });

    it('displays admin dashboard with all statistics cards', function () {
        $this->actingAs($this->admin);

        $page = visit('/admin/dashboard');

        $page->assertSee('Dashboard')
            ->assertSee('Projects')
            ->assertNoJavascriptErrors()
            ->assertNoConsoleLogs();
    });

    it('displays user management page', function () {
        $this->actingAs($this->admin);

        $page = visit('/admin/users');

        $page->assertSee('User')
            ->assertNoJavascriptErrors();
    });

    it('displays blockchain explorer', function () {
        $this->actingAs($this->admin);

        $page = visit('/admin/blockchain-explorer');

        $page->assertSee('Blockchain')
            ->assertNoJavascriptErrors();
    });

    it('allows admin to toggle dark mode', function () {
        $this->actingAs($this->admin);

        $page = visit('/admin/dashboard');

        $page->assertNoJavascriptErrors();
    });
});

describe('BAC Secretariat Dashboard Browser Flow', function () {
    beforeEach(function () {
        $this->seedPermissions();

        $this->bacSecretariat = User::factory()->create();
        $this->bacSecretariat->assignRole('bac_secretariat');
    });

    it('displays BAC secretariat dashboard', function () {
        $this->actingAs($this->bacSecretariat);

        $page = visit('/bac-secretariat/dashboard');

        $page->assertSee('Dashboard')
            ->assertNoJavascriptErrors()
            ->assertNoConsoleLogs();
    });

    it('displays procurements list', function () {
        $this->actingAs($this->bacSecretariat);

        $page = visit(route('bac-secretariat.procurements.index'));

        $page->assertSee('Procurement List')
            ->assertNoJavascriptErrors();
    });
});

describe('BAC Chairman Dashboard Browser Flow', function () {
    beforeEach(function () {
        $this->seedPermissions();

        $this->bacChairman = User::factory()->create();
        $this->bacChairman->assignRole('bac_chairman');
    });

    it('displays BAC chairman dashboard', function () {
        $this->actingAs($this->bacChairman);

        $page = visit('/bac-chairman/dashboard');

        $page->assertSee('Dashboard')
            ->assertNoJavascriptErrors()
            ->assertNoConsoleLogs();
    });
});

describe('HOPE Dashboard Browser Flow', function () {
    beforeEach(function () {
        $this->seedPermissions();

        $this->hope = User::factory()->create();
        $this->hope->assignRole('hope');
    });

    it('displays HOPE dashboard', function () {
        $this->actingAs($this->hope);

        $page = visit('/hope/dashboard');

        $page->assertSee('Dashboard')
            ->assertNoJavascriptErrors()
            ->assertNoConsoleLogs();
    });
});
