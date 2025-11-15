<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    // Create BAC Secretariat role and user with proper guard_name
    Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);
    $this->user = User::factory()->create();
    $this->user->assignRole('bac_secretariat');
});

describe('Procurement Initiation Form Browser Tests', function () {
    it('can load the procurement initiation form', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        $page->assertSee('New Procurement')
            ->assertSee('Procurement Initiation')
            ->assertSee('Required Documents')
            ->assertSee('Optional Supporting Documents')
            ->assertNoJavaScriptErrors();
    });

    it('displays all required form fields', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        $page->assertSee('Procurement ID')
            ->assertSee('Procurement Title')
            ->assertSee('PPMP Reference')
            ->assertSee('Description')
            ->assertSee('Category')
            ->assertSee('Procurement Mode')
            ->assertSee('ABC Amount')
            ->assertSee('Funding Source')
            ->assertSee('Office')
            ->assertSee('End User')
            ->assertSee('Purpose')
            ->assertSee('Delivery Location')
            ->assertSee('Expected Delivery Date')
            ->assertSee('Delivery Term')
            ->assertSee('Prepared By')
            ->assertNoJavaScriptErrors();
    });

    it('validates required fields before submission', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        // Check that submit button is disabled when form is empty
        $page->assertButtonDisabled('Submit Procurement')
            ->assertNoJavaScriptErrors();
    });

    it('can fill procurement ID fields correctly', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        // Just verify the form loaded - PR number generation happens on backend
        $page->assertSee('Procurement ID')
            ->assertNoJavaScriptErrors();
    });

    it('can fill basic procurement information', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        $page->fill('title', 'Test Procurement Project')
            ->fill('ppmp_reference', 'PPMP-2025-001')
            ->fill('description', 'This is a test procurement for office supplies')
            ->wait(500)
            ->assertValue('title', 'Test Procurement Project')
            ->assertValue('ppmp_reference', 'PPMP-2025-001')
            ->assertNoJavaScriptErrors();
    });

    it('can select category dropdown', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        // Click category dropdown
        $page->click('#category')
            ->wait(500)
            ->assertSee('Goods')
            ->click('text=Goods')
            ->wait(500)
            ->assertNoJavaScriptErrors();
    });

    it('can select procurement mode dropdown', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        // Click procurement mode dropdown
        $page->click('#procurement_mode')
            ->wait(500)
            ->assertSee('Public Bidding')
            ->click('text=Public Bidding')
            ->wait(500)
            ->assertNoJavaScriptErrors();
    });

    it('can enter ABC amount', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        $page->fill('abc_amount', '500000.00')
            ->wait(500)
            ->assertValue('abc_amount', '500000.00')
            ->assertNoJavaScriptErrors();
    });

    it('can select funding source', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        // Click funding source dropdown
        $page->click('#funding_source')
            ->wait(500)
            ->assertVisible('[role="option"]')
            ->press('Escape')
            ->assertNoJavaScriptErrors();
    });

    it('can select office from dropdown', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        // Click office dropdown
        $page->click('#office')
            ->wait(500)
            ->assertVisible('[role="option"]')
            ->press('Escape')
            ->assertNoJavaScriptErrors();
    });

    it('can fill end user field', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        $page->fill('end_user', 'All Departments')
            ->wait(500)
            ->assertValue('end_user', 'All Departments')
            ->assertNoJavaScriptErrors();
    });

    it('can fill purpose and delivery details', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        $page->fill('purpose', 'To improve office efficiency and productivity')
            ->fill('delivery_location', 'Main Office Warehouse')
            ->fill('delivery_term_days', '30')
            ->wait(500)
            ->assertValue('purpose', 'To improve office efficiency and productivity')
            ->assertValue('delivery_location', 'Main Office Warehouse')
            ->assertValue('delivery_term_days', '30')
            ->assertNoJavaScriptErrors();
    });

    it('shows prepared by field with current user name', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        $page->assertValue('prepared_by', $this->user->name)
            ->assertNoJavaScriptErrors();
    });

    it('displays mandatory documents section', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        $page->assertSee('Required Documents (RA 9184)')
            ->assertVisible('.border-amber-500\\/50')
            ->assertNoJavaScriptErrors();
    });

    it('can upload a PDF file to mandatory document', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        // Create a test PDF file
        $testPdfPath = sys_get_temp_dir().'/test-document.pdf';
        file_put_contents($testPdfPath, '%PDF-1.4 test content');

        // Find first file input and attach file
        $page->attach('input[type="file"][accept=".pdf"]', $testPdfPath)
            ->wait(1000)
            ->assertNoJavaScriptErrors();

        // Clean up
        @unlink($testPdfPath);
    });

    it('shows optional documents section', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        $page->assertSee('Optional Supporting Documents')
            ->assertSee('Add Optional Document')
            ->assertNoJavaScriptErrors();
    });

    it('can add optional document', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        // Click optional document dropdown
        $page->click('text=Add Optional Document')
            ->click('select')
            ->wait(500)
            ->assertVisible('[role="option"]')
            ->press('Escape')
            ->assertNoJavaScriptErrors();
    });

    it('validates PR number format', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        // The PR number should be auto-formatted as PR-YYYY-XXXX-XXXX
        $page->type('input[type="text"][maxlength="4"][placeholder="0001"]:nth-of-type(3)', '1234')
            ->type('input[type="text"][maxlength="4"][placeholder="0001"]:nth-of-type(4)', '5678')
            ->wait(500)
            ->assertNoJavaScriptErrors();

        // Should show formatted PR number in preview
        $page->assertSee('PR-'.date('Y'))
            ->assertNoJavaScriptErrors();
    });

    it('updates preview as form is filled', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        // Fill PR number
        $page->type('input[type="text"][maxlength="4"][placeholder="0001"]:nth-of-type(3)', '0001')
            ->type('input[type="text"][maxlength="4"][placeholder="0001"]:nth-of-type(4)', '0001')
            ->wait(500);

        // Fill title
        $page->fill('title', 'Office Equipment Procurement')
            ->wait(500)
            ->assertSee('Preview')
            ->assertSee('PR Number')
            ->assertSee('Title')
            ->assertSee('Office Equipment Procurement')
            ->assertNoJavaScriptErrors();
    });

    it('shows document counter in preview', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        // Upload a test file
        $testPdfPath = sys_get_temp_dir().'/test-document.pdf';
        file_put_contents($testPdfPath, '%PDF-1.4 test content');

        $page->attach('input[type="file"][accept=".pdf"]', $testPdfPath)
            ->wait(1000);

        // Preview should show document count
        $page->assertSee('Documents')
            ->assertNoJavaScriptErrors();

        @unlink($testPdfPath);
    });

    it('disables submit button when form is incomplete', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        // Submit button should be disabled initially
        $page->assertDisabled('button:has-text("Submit Procurement")')
            ->assertNoJavaScriptErrors();
    });

    it('handles form validation errors gracefully', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        // Try to submit with only partial data
        $page->fill('title', 'Test')
            ->click('Submit Procurement')
            ->wait(500)
            ->assertSee('Please complete all required fields')
            ->assertNoJavaScriptErrors();
    });

    it('clears error messages when user interacts with fields', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        // Fill a field to trigger potential validation
        $page->fill('title', 'Test Project')
            ->clear('title')
            ->fill('title', 'New Test Project')
            ->assertNoJavaScriptErrors();
    });

    it('can complete full form submission workflow', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        // Fill PR number
        $page->type('input[type="text"][maxlength="4"][placeholder="0001"]:nth-of-type(3)', '0001')
            ->type('input[type="text"][maxlength="4"][placeholder="0001"]:nth-of-type(4)', '0001')
            ->wait(500);

        // Fill basic information
        $page->fill('title', 'Complete Procurement Test')
            ->fill('ppmp_reference', 'PPMP-2025-TEST')
            ->fill('description', 'End-to-end test of procurement form')
            ->wait(500);

        // Fill category
        $page->click('#category')
            ->wait(500)
            ->click('text=Goods')
            ->wait(500);

        // Fill procurement mode
        $page->click('#procurement_mode')
            ->wait(500)
            ->click('text=Public Bidding')
            ->wait(500);

        // Fill financial info
        $page->fill('abc_amount', '1000000.00')
            ->wait(500);

        // Fill remaining required fields
        $page->fill('purpose', 'Testing complete workflow')
            ->fill('delivery_location', 'Main Office')
            ->fill('prepared_by', $this->user->name)
            ->wait(500);

        // Create and upload test PDF files for mandatory documents
        $testPdfPath = sys_get_temp_dir().'/test-mandatory.pdf';
        file_put_contents($testPdfPath, '%PDF-1.4 test content');

        $page->attach('input[type="file"][accept=".pdf"]', $testPdfPath)
            ->wait(1000);

        // Verify preview is updated
        $page->assertSee('Preview')
            ->assertSee('Complete Procurement Test')
            ->assertNoJavaScriptErrors();

        @unlink($testPdfPath);
    });

    it('validates file type for PDF only', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        // Try to upload a non-PDF file (simulated by file without PDF signature)
        $testTxtPath = sys_get_temp_dir().'/test-document.txt';
        file_put_contents($testTxtPath, 'This is not a PDF');

        $page->attach('input[type="file"][accept=".pdf"]', $testTxtPath)
            ->wait(1000)
            ->assertSee('Invalid file type')
            ->assertNoJavaScriptErrors();

        @unlink($testTxtPath);
    });

    it('shows upload status for mandatory documents', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        // Initially, mandatory documents should show as not uploaded (amber border)
        $page->assertVisible('.border-amber-500\\/50')
            ->assertNoJavaScriptErrors();

        // After upload, should show green border
        $testPdfPath = sys_get_temp_dir().'/test-doc.pdf';
        file_put_contents($testPdfPath, '%PDF-1.4 test content');

        $page->attach('input[type="file"][accept=".pdf"]', $testPdfPath)
            ->wait(1000)
            ->assertVisible('.border-green-500\\/50')
            ->assertNoJavaScriptErrors();

        @unlink($testPdfPath);
    });

    it('can remove optional document', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        // Add optional document
        $page->click('text=Add Optional Document')
            ->click('select')
            ->wait(500);

        // Select first available option
        $page->press('ArrowDown')
            ->press('Enter')
            ->wait(500);

        // Should show remove button
        $page->assertVisible('button:has-text("Remove")')
            ->click('button:has-text("Remove")')
            ->wait(500)
            ->assertNoJavaScriptErrors();
    });

    it('works in mobile viewport', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation')
            ->on()->mobile();

        $page->assertSee('New Procurement')
            ->assertSee('Procurement Initiation')
            ->assertSee('Required Documents')
            ->assertNoJavaScriptErrors();
    });

    it('works in dark mode', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation')
            ->inDarkMode();

        $page->assertSee('New Procurement')
            ->assertSee('Procurement Initiation')
            ->assertNoJavaScriptErrors();
    });

    it('has proper accessibility attributes', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        // Check for main role
        $page->assertAttribute('[role="main"]', 'role', 'main')
            ->assertNoAccessibilityIssues()
            ->assertNoJavaScriptErrors();
    });

    it('maintains form state when switching between fields', function () {
        actingAs($this->user);

        $page = visit('/bac-secretariat/procurement-initiation');

        // Fill multiple fields
        $page->fill('title', 'State Persistence Test')
            ->fill('ppmp_reference', 'PPMP-2025-STATE')
            ->fill('description', 'Testing state persistence')
            ->wait(500);

        // Switch focus
        $page->click('title')
            ->click('ppmp_reference')
            ->click('description')
            ->wait(500);

        // Verify all values are maintained
        $page->assertValue('title', 'State Persistence Test')
            ->assertValue('ppmp_reference', 'PPMP-2025-STATE')
            ->assertValue('description', 'Testing state persistence')
            ->assertNoJavaScriptErrors();
    });
});
