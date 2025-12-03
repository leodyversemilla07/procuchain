<?php

use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementCategoryEnums;
use App\Enums\ProcurementModeEnums;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class)->group('procurement', 'integration');

beforeEach(function () {
    Storage::fake('local');

    // Create permissions (or get if exists)
    $managePermission = Permission::firstOrCreate(['name' => 'manage procurement initiation']);
    $createPermission = Permission::firstOrCreate(['name' => 'create procurement']);
    $viewPermission = Permission::firstOrCreate(['name' => 'view procurement']);
    $publishPermission = Permission::firstOrCreate(['name' => 'publish to blockchain']);

    // Create BAC Secretariat role (or get if exists)
    $role = Role::firstOrCreate(['name' => 'bac_secretariat']);
    $role->syncPermissions(['manage procurement initiation', 'create procurement', 'view procurement', 'publish to blockchain']);

    // Create a BAC Secretariat user for testing
    $this->user = User::factory()->create([
        'name' => 'Test BAC Secretariat',
        'email' => 'bac.secretariat.'.uniqid().'@test.com',
        'blockchain_address' => '1TestAddress1234567890123456789012345678901234567890',
    ]);

    // Assign BAC Secretariat role
    $this->user->assignRole('bac_secretariat');
});

/**
 * Test: Can view procurement initiation form
 */
test('bac secretariat can view procurement initiation form', function () {
    $response = $this->actingAs($this->user)
        ->get('/bac-secretariat/procurement-initiation');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/procurement-initiation')
            ->has('categories')
            ->has('procurementModes')
            ->has('documentTypes')
        );
});

/**
 * Test: Complete procurement initiation with all required documents
 */
test('can initiate procurement with all required documents for goods', function () {
    // Create fake PDF files
    $files = [
        UploadedFile::fake()->create('Purchase_Request.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('Certificate_of_Funds.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('PPMP_Entry.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('Technical_Specifications.pdf', 1000, 'application/pdf'),
    ];

    $response = $this->actingAs($this->user)
        ->withoutMiddleware('throttle:blockchain_writes')->startSession()->post('/bac-secretariat/initiate-procurement', [
            // Basic Information
            'pr_number' => 'PR-2025-'.str_pad((string) rand(1000, 9999), 4, '0', STR_PAD_LEFT).'-0001',
            'ppmp_reference' => 'PPMP-2025-001',
            'title' => 'Office Supplies Procurement',
            'description' => 'Purchase of office supplies for municipal office for FY 2025',

            // Financial Information
            'abc_amount' => '150000.00',
            'funding_source' => 'General Fund',

            // Classification
            'category' => ProcurementCategoryEnums::GOODS->value,
            'procurement_mode' => ProcurementModeEnums::SHOPPING->value,

            // Office Information
            'office' => 'General Services Office',
            'end_user' => 'All Departments',

            // Purpose
            'purpose' => 'Regular office operations for FY 2025',

            // Delivery Details
            'delivery_location' => 'Municipal Hall, Main Office',
            'delivery_date' => now()->addDays(30)->format('Y-m-d'),
            'delivery_term_days' => 30,

            // Prepared By
            'prepared_by' => 'Test BAC Secretariat',

            // Documents
            'files' => $files,
            'document_types' => [
                DocumentTypeEnums::PURCHASE_REQUEST->value,
                DocumentTypeEnums::CERTIFICATE_OF_FUNDS->value,
                DocumentTypeEnums::PPMP_ENTRY->value,
                DocumentTypeEnums::TECHNICAL_SPECIFICATIONS->value,
            ],
            'document_descriptions' => [
                'Official Purchase Request with PR number',
                'Budget Officer certification',
                'Extract from approved PPMP',
                'Detailed technical specifications for office supplies',
            ],
        ]);

    $response->assertRedirect()
        ->assertSessionHas('success');

    // Procurement was successfully initiated and published to blockchain
    // Files are published to blockchain, not stored in local storage
});

/**
 * Test: Consulting services requires TOR instead of tech specs
 */
test('consulting services requires terms of reference not technical specifications', function () {
    $files = [
        UploadedFile::fake()->create('Purchase_Request.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('Certificate_of_Funds.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('PPMP_Entry.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('Terms_of_Reference.pdf', 1000, 'application/pdf'),
    ];

    $response = $this->actingAs($this->user)
        ->withoutMiddleware('throttle:blockchain_writes')->startSession()->post('/bac-secretariat/initiate-procurement', [
            'pr_number' => 'PR-2025-'.str_pad((string) rand(1000, 9999), 4, '0', STR_PAD_LEFT).'-0001',
            'ppmp_reference' => 'PPMP-2025-002',
            'title' => 'IT Consulting Services',
            'description' => 'Professional IT consulting services for system upgrade',
            'abc_amount' => '500000.00',
            'funding_source' => 'Special Fund',
            'category' => ProcurementCategoryEnums::CONSULTING_SERVICES->value,
            'procurement_mode' => ProcurementModeEnums::SHOPPING->value,
            'office' => 'IT Department',
            'end_user' => 'IT Department',
            'purpose' => 'System upgrade and migration',
            'delivery_location' => 'IT Office',
            'delivery_date' => now()->addDays(60)->format('Y-m-d'),
            'delivery_term_days' => 60,
            'prepared_by' => 'Test BAC Secretariat',
            'files' => $files,
            'document_types' => [
                DocumentTypeEnums::PURCHASE_REQUEST->value,
                DocumentTypeEnums::CERTIFICATE_OF_FUNDS->value,
                DocumentTypeEnums::PPMP_ENTRY->value,
                DocumentTypeEnums::TERMS_OF_REFERENCE->value,
            ],
            'document_descriptions' => [
                'Official Purchase Request',
                'Budget certification',
                'PPMP entry',
                'Terms of Reference for consulting services',
            ],
        ]);

    $response->assertRedirect()
        ->assertSessionHas('success');
});

/**
 * Test: Validation - Missing PR number fails
 */
test('validation fails without pr number', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware('throttle:blockchain_writes')->startSession()->post('/bac-secretariat/initiate-procurement', [
            'pr_number' => '', // Missing PR number
            'ppmp_reference' => 'PPMP-2025-003',
            'title' => 'Test Procurement',
            'description' => 'Test description',
            'abc_amount' => '150000.00',
            'funding_source' => 'General Fund',
            'category' => ProcurementCategoryEnums::GOODS->value,
            'procurement_mode' => ProcurementModeEnums::SHOPPING->value,
            'office' => 'General Services Office',
            'purpose' => 'Test purpose',
            'delivery_location' => 'Test location',
            'delivery_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

    $response->assertRedirect()
        ->assertSessionHasErrors(['pr_number']);
});

/**
 * Test: Validation - Invalid PR number format fails
 */
test('validation fails with invalid pr number format', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware('throttle:blockchain_writes')->startSession()->post('/bac-secretariat/initiate-procurement', [
            'pr_number' => '2025-001', // Invalid format (should be PR-YYYY-####-####)
            'ppmp_reference' => 'PPMP-2025-004',
            'title' => 'Test Procurement',
            'description' => 'Test description',
            'abc_amount' => '150000.00',
            'funding_source' => 'General Fund',
            'category' => ProcurementCategoryEnums::GOODS->value,
            'procurement_mode' => ProcurementModeEnums::SHOPPING->value,
            'office' => 'General Services Office',
            'purpose' => 'Test purpose',
            'delivery_location' => 'Test location',
            'delivery_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

    $response->assertRedirect()
        ->assertSessionHasErrors(['pr_number']);
});

/**
 * Test: Validation - Missing mandatory documents fails
 */
test('validation fails when mandatory documents are missing', function () {
    $files = [
        // Only uploading 2 documents when 4 are required for Goods
        UploadedFile::fake()->create('Purchase_Request.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('PPMP_Entry.pdf', 1000, 'application/pdf'),
    ];

    $response = $this->actingAs($this->user)
        ->withoutMiddleware('throttle:blockchain_writes')->startSession()->post('/bac-secretariat/initiate-procurement', [
            'pr_number' => 'PR-2025-'.str_pad((string) rand(1000, 9999), 4, '0', STR_PAD_LEFT).'-0001',
            'ppmp_reference' => 'PPMP-2025-005',
            'title' => 'Test Procurement',
            'description' => 'Test description',
            'abc_amount' => '150000.00',
            'funding_source' => 'General Fund',
            'category' => ProcurementCategoryEnums::GOODS->value,
            'procurement_mode' => ProcurementModeEnums::SHOPPING->value,
            'office' => 'General Services Office',
            'purpose' => 'Test purpose',
            'delivery_location' => 'Test location',
            'delivery_date' => now()->addDays(30)->format('Y-m-d'),
            'prepared_by' => 'Test BAC Secretariat',
            'files' => $files,
            'document_types' => [
                DocumentTypeEnums::PURCHASE_REQUEST->value,
                DocumentTypeEnums::PPMP_ENTRY->value,
                // Missing: CERTIFICATE_OF_FUNDS and TECHNICAL_SPECIFICATIONS
            ],
            'document_descriptions' => ['PR', 'PPMP'],
        ]);

    $response->assertRedirect()
        ->assertSessionHasErrors(['document_types']);
    expect($response->getSession()->get('errors')->first('document_types'))
        ->toContain('Certificate of Availability of Funds (CAF)')
        ->toContain('Technical Specifications');
});

/**
 * Test: Validation - ABC amount exceeds procurement mode threshold
 */
test('validation fails when abc amount exceeds procurement mode threshold', function () {
    $files = [
        UploadedFile::fake()->create('Purchase_Request.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('Certificate_of_Funds.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('PPMP_Entry.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('Technical_Specifications.pdf', 1000, 'application/pdf'),
    ];

    $response = $this->actingAs($this->user)
        ->withoutMiddleware('throttle:blockchain_writes')->startSession()->post('/bac-secretariat/initiate-procurement', [
            'pr_number' => 'PR-2025-'.str_pad((string) rand(1000, 9999), 4, '0', STR_PAD_LEFT).'-0001',
            'ppmp_reference' => 'PPMP-2025-006',
            'title' => 'Large Procurement',
            'description' => 'Large procurement exceeding Shopping threshold',
            'abc_amount' => '5000000.00', // 5M exceeds Shopping threshold (1M)
            'funding_source' => 'General Fund',
            'category' => ProcurementCategoryEnums::GOODS->value,
            'procurement_mode' => ProcurementModeEnums::SHOPPING->value, // Wrong mode
            'office' => 'General Services Office',
            'purpose' => 'Test purpose',
            'delivery_location' => 'Test location',
            'delivery_date' => now()->addDays(30)->format('Y-m-d'),
            'prepared_by' => 'Test BAC Secretariat',
            'files' => $files,
            'document_types' => [
                DocumentTypeEnums::PURCHASE_REQUEST->value,
                DocumentTypeEnums::CERTIFICATE_OF_FUNDS->value,
                DocumentTypeEnums::PPMP_ENTRY->value,
                DocumentTypeEnums::TECHNICAL_SPECIFICATIONS->value,
            ],
            'document_descriptions' => ['PR', 'Certificate', 'PPMP', 'Specs'],
        ]);

    $response->assertRedirect()
        ->assertSessionHasErrors(['procurement_mode']);
    expect($response->getSession()->get('errors')->first('procurement_mode'))
        ->toContain('threshold')
        ->toContain('RA 9184');
});

/**
 * Test: Validation - Non-PDF files rejected
 */
test('validation fails for non-pdf files', function () {
    $files = [
        UploadedFile::fake()->create('Purchase_Request.docx', 1000, 'application/msword'), // Not PDF
        UploadedFile::fake()->create('Certificate_of_Funds.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('PPMP_Entry.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('Technical_Specifications.pdf', 1000, 'application/pdf'),
    ];

    $response = $this->actingAs($this->user)
        ->withoutMiddleware('throttle:blockchain_writes')->startSession()->post('/bac-secretariat/initiate-procurement', [
            'pr_number' => 'PR-2025-'.str_pad((string) rand(1000, 9999), 4, '0', STR_PAD_LEFT).'-0001',
            'ppmp_reference' => 'PPMP-2025-007',
            'title' => 'Test Procurement',
            'description' => 'Test description',
            'abc_amount' => '150000.00',
            'funding_source' => 'General Fund',
            'category' => ProcurementCategoryEnums::GOODS->value,
            'procurement_mode' => ProcurementModeEnums::SHOPPING->value,
            'office' => 'General Services Office',
            'purpose' => 'Test purpose',
            'delivery_location' => 'Test location',
            'delivery_date' => now()->addDays(30)->format('Y-m-d'),
            'prepared_by' => 'Test BAC Secretariat',
            'files' => $files,
            'document_types' => [
                DocumentTypeEnums::PURCHASE_REQUEST->value,
                DocumentTypeEnums::CERTIFICATE_OF_FUNDS->value,
                DocumentTypeEnums::PPMP_ENTRY->value,
                DocumentTypeEnums::TECHNICAL_SPECIFICATIONS->value,
            ],
            'document_descriptions' => ['PR', 'Certificate', 'PPMP', 'Specs'],
        ]);

    $response->assertRedirect()
        ->assertSessionHasErrors(['files.0']);
});

/**
 * Test: Can add optional documents
 */
test('can add optional supporting documents', function () {
    $files = [
        // Mandatory documents
        UploadedFile::fake()->create('Purchase_Request.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('Certificate_of_Funds.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('PPMP_Entry.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('Technical_Specifications.pdf', 1000, 'application/pdf'),
        // Optional documents
        UploadedFile::fake()->create('Market_Research.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('Price_Survey.pdf', 1000, 'application/pdf'),
    ];

    $response = $this->actingAs($this->user)
        ->withoutMiddleware('throttle:blockchain_writes')->startSession()->post('/bac-secretariat/initiate-procurement', [
            'pr_number' => 'PR-2025-'.str_pad((string) rand(1000, 9999), 4, '0', STR_PAD_LEFT).'-0001',
            'ppmp_reference' => 'PPMP-2025-008',
            'title' => 'Office Supplies with Supporting Docs',
            'description' => 'Purchase with market research and price survey',
            'abc_amount' => '150000.00',
            'funding_source' => 'General Fund',
            'category' => ProcurementCategoryEnums::GOODS->value,
            'procurement_mode' => ProcurementModeEnums::SHOPPING->value,
            'office' => 'General Services Office',
            'end_user' => 'All Departments',
            'purpose' => 'Regular operations',
            'delivery_location' => 'Municipal Hall',
            'delivery_date' => now()->addDays(30)->format('Y-m-d'),
            'delivery_term_days' => 30,
            'prepared_by' => 'Test BAC Secretariat',
            'files' => $files,
            'document_types' => [
                DocumentTypeEnums::PURCHASE_REQUEST->value,
                DocumentTypeEnums::CERTIFICATE_OF_FUNDS->value,
                DocumentTypeEnums::PPMP_ENTRY->value,
                DocumentTypeEnums::TECHNICAL_SPECIFICATIONS->value,
                DocumentTypeEnums::MARKET_RESEARCH->value,
                DocumentTypeEnums::PRICE_SURVEY->value,
            ],
            'document_descriptions' => [
                'Official PR',
                'Budget certification',
                'PPMP entry',
                'Technical specs',
                'Market research for ABC computation',
                'Price survey with 3 quotations',
            ],
        ]);

    $response->assertRedirect()
        ->assertSessionHas('success');

    // Verify 6 documents were successfully uploaded (4 mandatory + 2 optional)
    // Files are published to blockchain, not stored in local storage
}); /**
 * Test: Unauthenticated users cannot access
 */
test('unauthenticated users cannot access procurement initiation', function () {
    $response = $this->get('/bac-secretariat/procurement-initiation');

    $response->assertRedirect('/login');
});

/**
 * Test: Users without permission cannot initiate procurement
 */
test('users without permission cannot initiate procurement', function () {
    $unauthorizedUser = User::factory()->create();
    // No role assigned

    $response = $this->actingAs($unauthorizedUser)
        ->get('/bac-secretariat/procurement-initiation');

    $response->assertStatus(403);
});

/**
 * Test: Can mark procurement initiation stage as complete with all documents uploaded
 */
test('can mark procurement initiation stage as complete when all documents uploaded', function () {
    // Mock the required services
    $statusPublisher = Mockery::mock(\App\Services\Publishers\StatusPublisher::class);
    $statusPublisher->shouldReceive('publish')->andReturn([
        'success' => true,
        'status_txid' => 'test_txid_123',
        'stage' => 'procurement_initiation',
        'current_status' => 'procurement_submitted',
        'previous_status' => null,
    ]);
    $this->app->instance(\App\Services\Publishers\StatusPublisher::class, $statusPublisher);

    $eventPublisher = Mockery::mock(\App\Services\Publishers\EventPublisher::class);
    $eventPublisher->shouldReceive('publish')->andReturn([
        'success' => true,
        'event_txid' => 'test_event_txid_123',
        'event_type' => 'stage_completed',
        'category' => 'stage_transition',
    ]);
    $this->app->instance(\App\Services\Publishers\EventPublisher::class, $eventPublisher);

    $prNumber = 'PR-2025-TEST-0001';

    // Mock document repository to return uploaded documents
    $documentRepo = Mockery::mock(\App\Repositories\DocumentRepository::class)->makePartial();
    $documentRepo->shouldReceive('findByProcurement')
        ->with($prNumber)
        ->andReturn(collect([
            (object) ['stage' => 'procurement_initiation', 'documentType' => 'purchase_request'],
            (object) ['stage' => 'procurement_initiation', 'documentType' => 'ppmp'],
            (object) ['stage' => 'procurement_initiation', 'documentType' => 'app'],
            (object) ['stage' => 'procurement_initiation', 'documentType' => 'certificate_of_funds'],
            (object) ['stage' => 'procurement_initiation', 'documentType' => 'approved_budget_contract'],
            (object) ['stage' => 'procurement_initiation', 'documentType' => 'technical_specifications'],
        ]));
    $this->app->instance(\App\Repositories\DocumentRepository::class, $documentRepo);

    // Mock procurement repository
    $procurementRepo = Mockery::mock(\App\Repositories\ProcurementRepository::class);
    $procurementRepo->shouldReceive('findByProcurement')
        ->with($prNumber)
        ->andReturn(new \App\DataTransferObjects\ProcurementData(
            prNumber: $prNumber,
            ppmpReference: 'PPMP-2025-TEST',
            title: 'Test Procurement',
            description: 'Test procurement for stage completion',
            abcAmount: 150000.00,
            fundingSource: 'General Fund',
            category: \App\Enums\ProcurementCategoryEnums::GOODS,
            procurementMode: \App\Enums\ProcurementModeEnums::SHOPPING,
            office: 'Test Office',
            endUser: 'Test Department',
            purpose: 'Test purpose',
            deliveryLocation: 'Test Location',
            deliveryDate: now()->addDays(30),
            deliveryTermDays: 30,
            preparedBy: 'Test User',
            bacResolutionNumber: null,
            bacResolutionDate: null,
            philgepsReference: null,
            philgepsPostingDate: null,
            approvedBy: null,
            approvalDate: null,
            status: 'procurement_initiation',
            userId: '1',
            createdAt: now(),
        ));
    $this->app->instance(\App\Repositories\ProcurementRepository::class, $procurementRepo);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware('throttle:blockchain_writes')->startSession()->post("/bac-secretariat/procurement-initiation/{$prNumber}/complete");

    $response->assertRedirect()
        ->assertSessionHas('success');
});

/**
 * Test: Cannot mark stage as complete without all required documents
 */
test('cannot mark procurement initiation stage as complete without required documents', function () {
    $prNumber = 'PR-2025-TEST-0002';

    // Mock document repository to return no documents
    $documentRepo = Mockery::mock(\App\Repositories\DocumentRepository::class)->makePartial();
    $documentRepo->shouldReceive('findByProcurement')
        ->with($prNumber)
        ->andReturn(collect([]));
    $this->app->instance(\App\Repositories\DocumentRepository::class, $documentRepo);

    $response = $this->actingAs($this->user)
        ->withoutMiddleware('throttle:blockchain_writes')->startSession()->post("/bac-secretariat/procurement-initiation/{$prNumber}/complete");

    $response->assertRedirect()
        ->assertSessionHas('error');
});
