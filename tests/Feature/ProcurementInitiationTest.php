<?php

use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementCategory;
use App\Enums\ProcurementMode;
use App\Jobs\BlockchainWriteJob;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\BlockchainRpcClient;
use App\Services\Publishers\EventPublisher;
use App\Services\Publishers\StatusPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class)->group('procurement', 'integration');

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();

    // Mock MultiChain BlockchainRpcClient for CI environment (no blockchain node available)
    $mockMultichain = Mockery::mock(BlockchainRpcClient::class);
    $mockMultichain->shouldReceive('publish')->andReturn('mock_txid_'.uniqid());
    $mockMultichain->shouldReceive('liststreamkeyitems')->andReturn([]);
    $mockMultichain->shouldReceive('getinfo')->andReturn(['version' => '2.3.3']);
    $this->app->instance(BlockchainRpcClient::class, $mockMultichain);

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
    // Create fake PDF BlockchainFiles
    $blockchainFiles = [
        UploadedFile::fake()->create('Purchase_Request.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('Certificate_of_Funds.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('PPMP_Entry.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('Technical_Specifications.pdf', 1000, 'application/pdf'),
    ];

    $response = $this->actingAs($this->user)
        ->withoutMiddleware('throttle:blockchain_writes')->startSession()->post('/bac-secretariat/initiate-procurement', [
            // Basic Information
            'pr_number' => 'PR-2025-'.str_pad((string) rand(100, 999), 3, '0', STR_PAD_LEFT).'-0001',
            'app_reference' => 'APP-2025-001',
            'title' => 'Office Supplies Procurement',
            'description' => 'Purchase of office supplies for municipal office for FY 2025',

            // Financial Information
            'abc_amount' => '150000.00',
            'funding_source' => 'General Fund',

            // Classification
            'category' => ProcurementCategory::GOODS->value,
            'procurement_mode' => ProcurementMode::SMALL_VALUE_PROCUREMENT->value,

            // Office Information
            'office' => 'General Services Office',
            'end_user' => 'All Departments',

            // Prepared By
            'prepared_by' => 'Test BAC Secretariat',

            // Documents
            'Files' => $blockchainFiles,
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

    $response->assertStatus(202)
        ->assertJsonStructure(['job_id', 'status', 'pr_number']);

    Queue::assertPushed(BlockchainWriteJob::class);
});

/**
 * Test: Consulting services requires TOR instead of tech specs
 */
test('consulting services requires terms of reference not technical specifications', function () {
    $blockchainFiles = [
        UploadedFile::fake()->create('Purchase_Request.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('Certificate_of_Funds.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('PPMP_Entry.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('Terms_of_Reference.pdf', 1000, 'application/pdf'),
    ];

    $response = $this->actingAs($this->user)
        ->withoutMiddleware('throttle:blockchain_writes')->startSession()->post('/bac-secretariat/initiate-procurement', [
            'pr_number' => 'PR-2025-'.str_pad((string) rand(100, 999), 3, '0', STR_PAD_LEFT).'-0001',
            'app_reference' => 'APP-2025-002',
            'title' => 'IT Consulting Services',
            'description' => 'Professional IT consulting services for system upgrade',
            'abc_amount' => '150000.00', // Within 4th class municipality SVP threshold (₱400,000)
            'funding_source' => 'Special Fund',
            'category' => ProcurementCategory::CONSULTING_SERVICES->value,
            'procurement_mode' => ProcurementMode::SMALL_VALUE_PROCUREMENT->value,
            'office' => 'IT Department',
            'end_user' => 'IT Department',
            'prepared_by' => 'Test BAC Secretariat',
            'Files' => $blockchainFiles,
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

    $response->assertStatus(202)
        ->assertJsonStructure(['job_id', 'status', 'pr_number']);

    Queue::assertPushed(BlockchainWriteJob::class);
});

/**
 * Test: Validation - Missing PR number fails
 */
test('validation fails without pr number', function () {
    $response = $this->actingAs($this->user)
        ->withoutMiddleware('throttle:blockchain_writes')->startSession()->post('/bac-secretariat/initiate-procurement', [
            'pr_number' => '', // Missing PR number
            'app_reference' => 'APP-2025-003',
            'title' => 'Test Procurement',
            'description' => 'Test description',
            'abc_amount' => '150000.00',
            'funding_source' => 'General Fund',
            'category' => ProcurementCategory::GOODS->value,
            'procurement_mode' => ProcurementMode::SMALL_VALUE_PROCUREMENT->value,
            'office' => 'General Services Office',
            'prepared_by' => 'Test BAC Secretariat',
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
            'pr_number' => '2025-001', // Invalid format (should be PR-YYYY-###-####)
            'app_reference' => 'APP-2025-004',
            'title' => 'Test Procurement',
            'description' => 'Test description',
            'abc_amount' => '150000.00',
            'funding_source' => 'General Fund',
            'category' => ProcurementCategory::GOODS->value,
            'procurement_mode' => ProcurementMode::SMALL_VALUE_PROCUREMENT->value,
            'office' => 'General Services Office',
            'prepared_by' => 'Test BAC Secretariat',
        ]);

    $response->assertRedirect()
        ->assertSessionHasErrors(['pr_number']);
});

/**
 * Test: Validation - Missing mandatory documents fails
 */
test('validation fails when mandatory documents are missing', function () {
    $blockchainFiles = [
        // Only uploading 2 documents when 4 are required for Goods
        UploadedFile::fake()->create('Purchase_Request.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('PPMP_Entry.pdf', 1000, 'application/pdf'),
    ];

    $response = $this->actingAs($this->user)
        ->withoutMiddleware('throttle:blockchain_writes')->startSession()->post('/bac-secretariat/initiate-procurement', [
            'pr_number' => 'PR-2025-'.str_pad((string) rand(100, 999), 3, '0', STR_PAD_LEFT).'-0001',
            'app_reference' => 'APP-2025-005',
            'title' => 'Test Procurement',
            'description' => 'Test description',
            'abc_amount' => '150000.00',
            'funding_source' => 'General Fund',
            'category' => ProcurementCategory::GOODS->value,
            'procurement_mode' => ProcurementMode::SMALL_VALUE_PROCUREMENT->value,
            'office' => 'General Services Office',
            'prepared_by' => 'Test BAC Secretariat',
            'Files' => $blockchainFiles,
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
    $blockchainFiles = [
        UploadedFile::fake()->create('Purchase_Request.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('Certificate_of_Funds.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('PPMP_Entry.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('Technical_Specifications.pdf', 1000, 'application/pdf'),
    ];

    $response = $this->actingAs($this->user)
        ->withoutMiddleware('throttle:blockchain_writes')->startSession()->post('/bac-secretariat/initiate-procurement', [
            'pr_number' => 'PR-2025-'.str_pad((string) rand(100, 999), 3, '0', STR_PAD_LEFT).'-0001',
            'app_reference' => 'APP-2025-006',
            'title' => 'Large Procurement',
            'description' => 'Large procurement exceeding SVP threshold',
            'abc_amount' => '500000.00', // ₱500K exceeds SVP threshold (₱400K for 4th class municipality)
            'funding_source' => 'General Fund',
            'category' => ProcurementCategory::GOODS->value,
            'procurement_mode' => ProcurementMode::SMALL_VALUE_PROCUREMENT->value, // Wrong mode
            'office' => 'General Services Office',
            'prepared_by' => 'Test BAC Secretariat',
            'Files' => $blockchainFiles,
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
        ->toContain('RA 12009');
});

/**
 * Test: Validation - Non-PDF BlockchainFiles rejected
 */
test('validation fails for non-pdf BlockchainFiles', function () {
    $blockchainFiles = [
        UploadedFile::fake()->create('Purchase_Request.docx', 1000, 'application/msword'), // Not PDF
        UploadedFile::fake()->create('Certificate_of_Funds.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('PPMP_Entry.pdf', 1000, 'application/pdf'),
        UploadedFile::fake()->create('Technical_Specifications.pdf', 1000, 'application/pdf'),
    ];

    $response = $this->actingAs($this->user)
        ->withoutMiddleware('throttle:blockchain_writes')->startSession()->post('/bac-secretariat/initiate-procurement', [
            'pr_number' => 'PR-2025-'.str_pad((string) rand(100, 999), 3, '0', STR_PAD_LEFT).'-0001',
            'app_reference' => 'APP-2025-007',
            'title' => 'Test Procurement',
            'description' => 'Test description',
            'abc_amount' => '150000.00',
            'funding_source' => 'General Fund',
            'category' => ProcurementCategory::GOODS->value,
            'procurement_mode' => ProcurementMode::SMALL_VALUE_PROCUREMENT->value,
            'office' => 'General Services Office',
            'prepared_by' => 'Test BAC Secretariat',
            'Files' => $blockchainFiles,
            'document_types' => [
                DocumentTypeEnums::PURCHASE_REQUEST->value,
                DocumentTypeEnums::CERTIFICATE_OF_FUNDS->value,
                DocumentTypeEnums::PPMP_ENTRY->value,
                DocumentTypeEnums::TECHNICAL_SPECIFICATIONS->value,
            ],
            'document_descriptions' => ['PR', 'Certificate', 'PPMP', 'Specs'],
        ]);

    $response->assertRedirect()
        ->assertSessionHasErrors(['Files.0']);
});

/**
 * Test: Can add optional documents
 */
test('can add optional supporting documents', function () {
    $blockchainFiles = [
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
            'pr_number' => 'PR-2025-'.str_pad((string) rand(100, 999), 3, '0', STR_PAD_LEFT).'-0001',
            'app_reference' => 'APP-2025-008',
            'title' => 'Office Supplies with Supporting Docs',
            'description' => 'Purchase with market research and price survey',
            'abc_amount' => '150000.00',
            'funding_source' => 'General Fund',
            'category' => ProcurementCategory::GOODS->value,
            'procurement_mode' => ProcurementMode::SMALL_VALUE_PROCUREMENT->value,
            'office' => 'General Services Office',
            'end_user' => 'All Departments',
            'prepared_by' => 'Test BAC Secretariat',
            'Files' => $blockchainFiles,
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

    $response->assertStatus(202)
        ->assertJsonStructure(['job_id', 'status', 'pr_number']);

    Queue::assertPushed(BlockchainWriteJob::class);
});

/**
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
    // Create required DB records (removed old repository mocks)
    $procurement = Procurement::create([
        'pr_number' => 'PR-2025-100-0001',
        'app_reference' => 'APP-2025-TEST',
        'title' => 'Test Procurement',
        'description' => 'Test procurement for stage completion',
        'abc_amount' => 150000.00,
        'fund_source' => 'General Fund',
        'category' => 'goods',
        'procurement_mode' => 'small_value_procurement',
        'office' => 'Test Office',
        'end_user' => 'Test Department',
        'prepared_by' => 'Test User',
        'status' => 'procurement_initiation',
        'user_id' => $this->user->id,
        'created_at' => now(),
    ]);

    ProcurementDocument::create([
        'procurement_id' => $procurement->id,
        'stage' => 'procurement_initiation',
        'document_type' => 'procurement_initiation_document',
        'filename' => 'test.pdf',
        'file_key' => 'test-file-key',
        'mime_type' => 'application/pdf',
        'hash' => hash('sha256', 'test'),
        'uploaded_by' => $this->user->name ?? 'test',
        'status' => 'uploaded',
        'uploaded_at' => now(),
        'user_address' => $this->user->blockchain_address ?? 'test_address',
    ]);

    // Mock the required services
    $statusPublisher = Mockery::mock(StatusPublisher::class);
    $statusPublisher->shouldReceive('publish')->andReturn([
        'success' => true,
        'status_txid' => 'test_txid_123',
        'stage' => 'procurement_initiation',
        'current_status' => 'procurement_submitted',
        'previous_status' => null,
    ]);
    $this->app->instance(StatusPublisher::class, $statusPublisher);

    $eventPublisher = Mockery::mock(EventPublisher::class);
    $eventPublisher->shouldReceive('publish')->andReturn([
        'success' => true,
        'event_txid' => 'test_event_txid_123',
        'event_type' => 'stage_completed',
        'category' => 'stage_transition',
    ]);
    $this->app->instance(EventPublisher::class, $eventPublisher);

    $prNumber = 'PR-2025-100-0001';

    $response = $this->actingAs($this->user)
        ->withoutMiddleware('throttle:blockchain_writes')->startSession()->post("/bac-secretariat/procurement-initiation/{$prNumber}/complete");

    $response->assertStatus(202)
        ->assertJsonStructure(['job_id', 'status', 'next_stage', 'next_stage_name']);

    Queue::assertPushed(BlockchainWriteJob::class);
});

/**
 * Test: Cannot mark stage as complete without all required documents
 */
test('cannot mark procurement initiation stage as complete without required documents', function () {
    $prNumber = 'PR-2025-200-0001';

    $response = $this->actingAs($this->user)
        ->withoutMiddleware('throttle:blockchain_writes')->startSession()->post("/bac-secretariat/procurement-initiation/{$prNumber}/complete");

    $response->assertStatus(422)
        ->assertJson(['error' => 'Cannot mark stage as complete. Missing required documents: Procurement Initiation Document (PDF)']);
});
