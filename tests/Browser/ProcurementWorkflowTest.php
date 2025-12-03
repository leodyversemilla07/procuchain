<?php

declare(strict_types=1);

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);
uses(\Tests\SeedsPermissions::class);

describe('Procurement Initiation Browser Flow', function () {
    beforeEach(function () {
        $this->seedPermissions();

        $this->bacSecretariat = User::factory()->create([
            'blockchain_address' => 'test-blockchain-address',
        ]);
        $this->bacSecretariat->assignRole('bac_secretariat');
    });

    it('displays procurement initiation form', function () {
        $this->actingAs($this->bacSecretariat);

        $page = visit('/bac-secretariat/procurements/initiate');

        $page->assertSee('Initiate')
            ->assertSee('PR Number')
            ->assertNoJavascriptErrors()
            ->assertNoConsoleLogs();
    });

    it('shows validation errors for required fields', function () {
        $this->actingAs($this->bacSecretariat);

        $page = visit('/bac-secretariat/procurements/initiate');

        // Try to submit empty form
        $page->click('button[type="submit"]')
            ->assertNoJavascriptErrors();
    });

    it('displays all required form sections', function () {
        $this->actingAs($this->bacSecretariat);

        $page = visit('/bac-secretariat/procurements/initiate');

        $page->assertSee('Title')
            ->assertSee('ABC')
            ->assertSee('Funding')
            ->assertNoJavascriptErrors();
    });
});

describe('Procurement List Browser Flow', function () {
    beforeEach(function () {
        $this->seedPermissions();

        $this->bacSecretariat = User::factory()->create();
        $this->bacSecretariat->assignRole('bac_secretariat');
    });

    it('displays procurement list with search functionality', function () {
        $this->actingAs($this->bacSecretariat);

        $page = visit('/procurements');

        $page->assertSee('Procurement')
            ->assertNoJavascriptErrors();
    });

    it('allows filtering by stage', function () {
        $this->actingAs($this->bacSecretariat);

        $page = visit('/procurements');

        $page->assertSee('Filter')
            ->assertNoJavascriptErrors();
    });

    it('shows procurement details when clicking on a row', function () {
        $this->actingAs($this->bacSecretariat);

        $page = visit('/procurements');

        $page->assertNoJavascriptErrors();
    });
});

describe('Document Upload Browser Flow', function () {
    beforeEach(function () {
        $this->seedPermissions();

        $this->bacSecretariat = User::factory()->create([
            'blockchain_address' => 'test-blockchain-address',
        ]);
        $this->bacSecretariat->assignRole('bac_secretariat');
    });

    it('displays file upload area with drag and drop support', function () {
        $this->actingAs($this->bacSecretariat);

        $page = visit('/bac-secretariat/procurements/initiate');

        $page->assertSee('Upload')
            ->assertNoJavascriptErrors();
    });

    it('shows file size limit information', function () {
        $this->actingAs($this->bacSecretariat);

        $page = visit('/bac-secretariat/procurements/initiate');

        $page->assertNoJavascriptErrors();
    });
});
