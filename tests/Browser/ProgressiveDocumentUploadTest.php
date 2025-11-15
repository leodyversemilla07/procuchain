<?php

use App\Models\User;

uses()->group('browser', 'progressive-upload');

beforeEach(function () {
    $this->user = User::factory()->create([
        'blockchain_address' => 'test_address_123',
        'email' => 'bac@test.com',
    ]);

    // Assign bac_secretariat role using Spatie Permission
    $this->user->assignRole('bac_secretariat');
});

describe('Progressive Document Upload - User Experience', function () {
    it('allows uploading documents one at a time with real-time checklist updates', function () {
        $this->actingAs($this->user);

        $page = visit('/bac-secretariat/pre-procurement/PR-2025-001/procurement_initiation');

        $page->assertSee('Document Checklist')
            ->assertSee('Purchase Request')
            ->assertSee('PPMP')
            ->assertNoJavascriptErrors();

        // Click upload button for Purchase Request
        $page->click('button[data-document-type="purchase_request"]')
            ->assertSee('Upload');

        // Verify file input appears
        $page->waitFor('input[type="file"]', 5);

        // Simulate file selection and upload
        // Note: In real browser test, this would trigger the actual file upload
        $page->assertNoConsoleLogs();
    })->skip('Requires full browser environment and blockchain');

    it('displays upload progress and disables other uploads during upload', function () {
        $this->actingAs($this->user);

        $page = visit('/bac-secretariat/pre-procurement/PR-2025-001/procurement_initiation');

        // Start upload
        $page->click('button[data-document-type="purchase_request"]');

        // Verify loading state
        $page->waitFor('[data-uploading="true"]', 5);

        // Other upload buttons should be disabled
        $page->assertDisabled('button[data-document-type="ppmp"]');
    })->skip('Requires full browser environment and blockchain');

    it('shows success toast after successful upload', function () {
        $this->actingAs($this->user);

        $page = visit('/bac-secretariat/pre-procurement/PR-2025-001/procurement_initiation');

        // Perform upload (mocked)
        $page->click('button[data-document-type="purchase_request"]');

        // Wait for success toast
        $page->waitForText('Document uploaded', 10)
            ->assertSee('uploaded successfully');
    })->skip('Requires full browser environment and blockchain');

    it('updates checklist with checkmark after upload', function () {
        $this->actingAs($this->user);

        $page = visit('/bac-secretariat/pre-procurement/PR-2025-001/procurement_initiation');

        // Before upload - no checkmark
        $page->assertMissing('[data-document="purchase_request"][data-uploaded="true"]');

        // Perform upload
        $page->click('button[data-document-type="purchase_request"]');

        // After upload - checkmark appears
        $page->waitFor('[data-document="purchase_request"][data-uploaded="true"]', 10)
            ->assertSee('CheckCircle');
    })->skip('Requires full browser environment and blockchain');

    it('shows error toast for invalid file type', function () {
        $this->actingAs($this->user);

        $page = visit('/bac-secretariat/pre-procurement/PR-2025-001/procurement_initiation');

        // Attempt to upload non-PDF
        $page->click('button[data-document-type="purchase_request"]');

        // Simulate selecting a .docx file
        // In real test, this would trigger file validation

        $page->waitForText('Invalid file type', 5)
            ->assertSee('Only PDF files are allowed');
    })->skip('Requires full browser environment');

    it('shows error toast for file size exceeding limit', function () {
        $this->actingAs($this->user);

        $page = visit('/bac-secretariat/pre-procurement/PR-2025-001/procurement_initiation');

        $page->click('button[data-document-type="purchase_request"]');

        // Simulate selecting a file > 10MB
        $page->waitForText('File too large', 5)
            ->assertSee('Maximum file size is 10MB');
    })->skip('Requires full browser environment');
});

describe('Progressive Document Upload - Multiple Stages', function () {
    it('allows navigating between stages and uploading documents', function () {
        $this->actingAs($this->user);

        // Start at Procurement Initiation
        $page = visit('/bac-secretariat/pre-procurement/PR-2025-001/procurement_initiation');
        $page->assertSee('Procurement Initiation');

        // Upload document
        $page->click('button[data-document-type="purchase_request"]');
        $page->waitForText('uploaded successfully', 10);

        // Navigate to Pre-Procurement Conference
        $page->visit('/bac-secretariat/pre-procurement/PR-2025-001/pre_procurement_conference');
        $page->assertSee('Pre-Procurement Conference');

        // Upload document in new stage
        $page->click('button[data-document-type="pre_procurement_agenda"]');
        $page->waitForText('uploaded successfully', 10);
    })->skip('Requires full browser environment and blockchain');
});

describe('Progressive Document Upload - Completion Status', function () {
    it('shows completion percentage as documents are uploaded', function () {
        $this->actingAs($this->user);

        $page = visit('/bac-secretariat/pre-procurement/PR-2025-001/procurement_initiation');

        // Initially 0%
        $page->assertSee('0%');

        // Upload first document
        $page->click('button[data-document-type="purchase_request"]');
        $page->waitForText('uploaded successfully', 10);

        // Percentage increases
        $page->assertNoSee('0%')
            ->assertSee('%'); // Some percentage > 0
    })->skip('Requires full browser environment and blockchain');

    it('displays "Mark Stage Complete" button when all required documents uploaded', function () {
        $this->actingAs($this->user);

        $page = visit('/bac-secretariat/pre-procurement/PR-2025-001/procurement_initiation');

        // Initially button is disabled or not visible
        $page->assertMissing('button:has-text("Mark Stage Complete")');

        // Upload all required documents (simulated)
        // After uploading all required docs...

        // Button becomes enabled
        $page->waitFor('button:has-text("Mark Stage Complete")', 10)
            ->assertEnabled('button:has-text("Mark Stage Complete")');
    })->skip('Requires full browser environment and blockchain');
});

describe('Progressive Document Upload - Replace Functionality', function () {
    it('allows replacing an already uploaded document', function () {
        $this->actingAs($this->user);

        $page = visit('/bac-secretariat/pre-procurement/PR-2025-001/procurement_initiation');

        // Document already uploaded
        $page->assertSee('Replace')
            ->click('button[data-document="purchase_request"][data-action="replace"]');

        // File input appears
        $page->waitFor('input[type="file"]', 5);

        // Upload new file
        // After successful upload
        $page->waitForText('Document uploaded', 10)
            ->assertSee('replaced successfully');
    })->skip('Requires full browser environment and blockchain');
});

describe('Progressive Document Upload - Mobile Responsiveness', function () {
    it('works on mobile devices', function () {
        $this->actingAs($this->user);

        $page = visit('/bac-secretariat/pre-procurement/PR-2025-001/procurement_initiation')
            ->resize(375, 667); // iPhone SE

        $page->assertSee('Document Checklist')
            ->assertNoJavascriptErrors();

        // Upload button is clickable on mobile
        $page->click('button[data-document-type="purchase_request"]')
            ->assertSee('Upload');
    })->skip('Requires full browser environment');
});

describe('Progressive Document Upload - Accessibility', function () {
    it('has proper ARIA labels for screen readers', function () {
        $this->actingAs($this->user);

        $page = visit('/bac-secretariat/pre-procurement/PR-2025-001/procurement_initiation');

        // Check ARIA attributes
        $page->assertPresent('[aria-label="Upload Purchase Request"]')
            ->assertPresent('[role="button"]');
    })->skip('Requires full browser environment');

    it('supports keyboard navigation', function () {
        $this->actingAs($this->user);

        $page = visit('/bac-secretariat/pre-procurement/PR-2025-001/procurement_initiation');

        // Tab to first upload button
        $page->keys('{Tab}')
            ->keys('{Tab}')
            ->keys('{Tab}')
            ->keys('{Enter}');

        // File input should appear
        $page->waitFor('input[type="file"]', 5);
    })->skip('Requires full browser environment');
});
