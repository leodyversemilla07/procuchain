<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/*
 * Progressive Document Upload - Browser Tests
 *
 * Note: These tests require full infrastructure setup:
 * - Database with procurement records
 * - Blockchain node connectivity
 * - Inertia page components
 *
 * Tests are skipped by default. To enable:
 * 1. Set up local blockchain environment
 * 2. Seed test procurement data
 * 3. Remove ->skip() from tests
 */

beforeEach(function () {
    Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);
    $this->user = User::factory()->create(['blockchain_address' => 'test_address_123']);
    $this->user->assignRole('bac_secretariat');
});

describe('Progressive Document Upload - Page Load', function () {
    it('loads pre-procurement conference upload page', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/pre-procurement/PR-TEST-001/pre_procurement_conference');

        $page->assertSee('Pre-Procurement Conference')
            ->assertSee('Document Upload')
            ->assertSee('Required Documents')
            ->assertNoJavaScriptErrors();
    })->skip('Requires blockchain setup and test procurement data');

    it('loads bidding documents upload page', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/pre-procurement/PR-TEST-001/bidding_documents');

        $page->assertSee('Bidding Documents')
            ->assertSee('Document Upload')
            ->assertSee('Required Documents')
            ->assertNoJavaScriptErrors();
    })->skip('Requires blockchain setup and test procurement data');
});

describe('Progressive Document Upload - File Selection', function () {
    it('allows selecting a PDF file', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/pre-procurement/PR-TEST-001/pre_procurement_conference');

        $page->assertSee('Upload Document')
            ->click('button[data-testid="select-file"]')
            ->assertSee('Choose File');
    })->skip('Requires blockchain setup and DOM testing');

    it('shows file validation error for non-PDF', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/pre-procurement/PR-TEST-001/pre_procurement_conference');

        // Attempt to upload non-PDF file
        $page->attach('document_file', __DIR__.'/../fixtures/test-document.docx')
            ->assertSee('Only PDF files are allowed');
    })->skip('Requires blockchain setup and test fixtures');
});

describe('Progressive Document Upload - Form Interaction', function () {
    it('shows document type selector', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/pre-procurement/PR-TEST-001/pre_procurement_conference');

        $page->assertSee('Document Type')
            ->assertSee('Description')
            ->assertSee('Upload');
    })->skip('Requires blockchain setup');

    it('displays upload progress indicator', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/pre-procurement/PR-TEST-001/pre_procurement_conference');

        $page->attach('document_file', __DIR__.'/../fixtures/test-document.pdf')
            ->select('document_type', 'pre_procurement_agenda')
            ->fill('description', 'Test agenda')
            ->click('button[type="submit"]')
            ->assertSee('Uploading...');
    })->skip('Requires blockchain setup and test fixtures');

    it('shows success message after upload', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/pre-procurement/PR-TEST-001/pre_procurement_conference');

        $page->attach('document_file', __DIR__.'/../fixtures/test-document.pdf')
            ->select('document_type', 'pre_procurement_agenda')
            ->fill('description', 'Test agenda')
            ->click('button[type="submit"]')
            ->waitForText('Document uploaded successfully');
    })->skip('Requires blockchain setup and test fixtures');
});

describe('Progressive Document Upload - Document List', function () {
    it('displays uploaded documents', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/pre-procurement/PR-TEST-001/pre_procurement_conference');

        $page->assertSee('Uploaded Documents')
            ->assertSee('Document Type')
            ->assertSee('Uploaded Date')
            ->assertSee('Status');
    })->skip('Requires blockchain setup with uploaded documents');

    it('allows downloading uploaded documents', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/pre-procurement/PR-TEST-001/pre_procurement_conference');

        $page->click('a[data-testid="download-document"]')
            ->assertDownloaded();
    })->skip('Requires blockchain setup with uploaded documents');
});

describe('Progressive Document Upload - Stage Completion', function () {
    it('shows completion progress', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/pre-procurement/PR-TEST-001/pre_procurement_conference');

        $page->assertSee('Completion Status')
            ->assertSee('%');
    })->skip('Requires blockchain setup');

    it('enables complete button when requirements met', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/pre-procurement/PR-TEST-001/pre_procurement_conference');

        $page->assertEnabled('button[data-testid="complete-stage"]');
    })->skip('Requires blockchain setup with all required documents uploaded');

    it('marks stage as complete', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/pre-procurement/PR-TEST-001/pre_procurement_conference');

        $page->click('button[data-testid="complete-stage"]')
            ->waitForText('Stage completed successfully');
    })->skip('Requires blockchain setup with all required documents uploaded');
});
