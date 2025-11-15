<?php

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Models\User;
use App\Repositories\ProcurementRepository;
use App\Services\DocumentValidationService;
use Illuminate\Http\UploadedFile;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

beforeEach(function () {
    $this->bacSecretariat = User::factory()->create();
    $this->bacSecretariat->assignRole('bac_secretariat');
    $this->bacSecretariat->blockchain_address = 'test_address_validation';
    $this->bacSecretariat->save();
});

describe('Document Upload Validation Workflow', function () {
    it('prevents upload of invalid document type for stage', function () {
        actingAs($this->bacSecretariat);

        $validationService = mock(DocumentValidationService::class);
        $validationService->shouldReceive('validateUpload')
            ->andReturn([
                'errors' => ['This document type is not valid for this stage'],
                'warnings' => [],
            ]);
        $this->instance(DocumentValidationService::class, $validationService);

        $repository = mock(ProcurementRepository::class);
        $repository->shouldReceive('findByProcurement')->andReturn(
            new \App\DataTransferObjects\ProcurementData(
                prNumber: 'PR-2024-001',
                ppmpReference: 'PPMP-2024-001',
                title: 'Test Procurement',
                description: 'Test Description',
                abcAmount: 1000000.00,
                fundingSource: 'General Fund',
                category: \App\Enums\ProcurementCategoryEnums::GOODS,
                procurementMode: \App\Enums\ProcurementModeEnums::PUBLIC_BIDDING,
                office: 'Test Office',
                endUser: 'Test User',
                purpose: 'Test Purpose',
                deliveryLocation: 'Test Location',
                deliveryDate: now(),
                deliveryTermDays: 30,
                preparedBy: 'Test Preparer',
                bacResolutionNumber: null,
                bacResolutionDate: null,
                philgepsReference: null,
                philgepsPostingDate: null,
                approvedBy: null,
                approvalDate: null,
                status: 'in_progress',
                userId: 'test@example.com',
                createdAt: now()
            )
        );
        $this->instance(ProcurementRepository::class, $repository);

        $procurementDataService = mock(\App\Services\ProcurementDataService::class);
        $this->instance(\App\Services\ProcurementDataService::class, $procurementDataService);

        $file = UploadedFile::fake()->create('invalid_doc.pdf', 1000, 'application/pdf');

        $response = $this->post(route('bac-secretariat.procurement.pre-procurement.upload', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
        ]), [
            'pr_number' => 'PR-2024-001',
            'invalid_document_file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors();
    });

    it('warns when uploading document not typical for stage', function () {
        actingAs($this->bacSecretariat);

        $validationService = mock(DocumentValidationService::class);
        $validationService->shouldReceive('validateUpload')
            ->andReturn([
                'errors' => [],
                'warnings' => ['This document is not typically required for this stage'],
            ]);
        $this->instance(DocumentValidationService::class, $validationService);

        $file = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');

        $response = $this->post(route('bac-secretariat.procurement.pre-procurement.validate-upload', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
        ]), [
            'document_type' => DocumentTypeEnums::NOTICE_OF_AWARD->value, // Wrong stage
            'file' => $file,
        ]);

        $response->assertSuccessful();
        $response->assertJsonPath('warnings.0', 'This document is not typically required for this stage');
    });

    // Note: Stage completion tests require blockchain repository access
    // These are covered in ProcurementPhaseControllersTest.php integration tests

    it('returns document guide with required and optional documents', function () {
        actingAs($this->bacSecretariat);

        $validationService = mock(DocumentValidationService::class);
        $validationService->shouldReceive('getStageDocumentGuide')
            ->andReturn([
                'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
                'stage_display_name' => 'Pre-Procurement Conference',
                'phase' => 'pre_procurement',
                'description' => 'Documents for Pre-Procurement Conference',
                'required_documents' => [
                    [
                        'value' => DocumentTypeEnums::PRE_PROCUREMENT_MINUTES->value,
                        'display_name' => 'Meeting Minutes',
                        'description' => 'Official minutes of the pre-procurement conference',
                    ],
                ],
                'optional_documents' => [
                    [
                        'value' => DocumentTypeEnums::PRE_PROCUREMENT_AGENDA->value,
                        'display_name' => 'Meeting Agenda',
                        'description' => 'Agenda for the pre-procurement conference',
                    ],
                ],
                'counts' => [
                    'required_count' => 1,
                    'optional_count' => 1,
                    'total_count' => 2,
                ],
            ]);
        $this->instance(DocumentValidationService::class, $validationService);

        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.document-guide', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
        ]));

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'stage',
            'stage_display_name',
            'phase',
            'description',
            'required_documents' => [
                '*' => ['value', 'display_name', 'description'],
            ],
            'optional_documents' => [
                '*' => ['value', 'display_name', 'description'],
            ],
            'counts' => ['required_count', 'optional_count', 'total_count'],
        ]);
    });

    // Note: Completion tests with blockchain repository covered in integration tests
});

describe('Progressive Upload Workflow', function () {
    it('uploads single document progressively', function () {
        actingAs($this->bacSecretariat);

        $repository = mock(ProcurementRepository::class);
        $repository->shouldReceive('findByProcurement')->andReturn(
            new \App\DataTransferObjects\ProcurementData(
                prNumber: 'PR-2024-001',
                ppmpReference: 'PPMP-2024-001',
                title: 'Test Procurement',
                description: 'Test Description',
                abcAmount: 1000000.00,
                fundingSource: 'General Fund',
                category: \App\Enums\ProcurementCategoryEnums::GOODS,
                procurementMode: \App\Enums\ProcurementModeEnums::PUBLIC_BIDDING,
                office: 'Test Office',
                endUser: 'Test User',
                purpose: 'Test Purpose',
                deliveryLocation: 'Test Location',
                deliveryDate: now(),
                deliveryTermDays: 30,
                preparedBy: 'Test Preparer',
                bacResolutionNumber: null,
                bacResolutionDate: null,
                philgepsReference: null,
                philgepsPostingDate: null,
                approvedBy: null,
                approvalDate: null,
                status: 'in_progress',
                userId: 'test@example.com',
                createdAt: now()
            )
        );
        $this->instance(ProcurementRepository::class, $repository);

        $orchestrator = mock(\App\Services\Publishers\ProcurementOrchestrator::class);
        $orchestrator->shouldReceive('publishDocumentWorkflow')->once()->andReturn([
            'success' => true,
            'document_txid' => 'doc123',
            'status_txid' => 'status123',
            'event_txid' => 'event123',
        ]);
        $this->instance(\App\Services\Publishers\ProcurementOrchestrator::class, $orchestrator);

        $procurementDataService = mock(\App\Services\ProcurementDataService::class);
        $this->instance(\App\Services\ProcurementDataService::class, $procurementDataService);

        $validationService = mock(DocumentValidationService::class);
        $validationService->shouldReceive('validateUpload')
            ->andReturn(['errors' => [], 'warnings' => []]);
        $validationService->shouldReceive('validateStageCompletion')
            ->andReturn(['can_complete' => false, 'completion_percentage' => 33]);
        $this->instance(DocumentValidationService::class, $validationService);

        $file = UploadedFile::fake()->create('minutes.pdf', 1000, 'application/pdf');

        $response = $this->post(route('bac-secretariat.procurement.pre-procurement.upload-document', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
        ]), [
            'document_file' => $file,
            'document_type' => DocumentTypeEnums::PRE_PROCUREMENT_MINUTES->value,
            'description' => 'Meeting minutes for pre-procurement conference',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    });

    it('tracks upload progress via completion percentage', function () {
        actingAs($this->bacSecretariat);

        $validationService = mock(DocumentValidationService::class);

        // First check: 0% complete (no documents)
        $validationService->shouldReceive('validateStageCompletion')
            ->once()
            ->andReturn([
                'can_complete' => false,
                'completion_percentage' => 0,
                'missing_documents' => [
                    DocumentTypeEnums::PRE_PROCUREMENT_MINUTES,
                    DocumentTypeEnums::PRE_PROCUREMENT_ATTENDANCE,
                ],
            ]);

        $this->instance(DocumentValidationService::class, $validationService);

        $response1 = $this->get(route('bac-secretariat.procurement.pre-procurement.check-completion', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
        ]));

        $response1->assertSuccessful();
        $response1->assertJsonPath('completion_percentage', 0);
        $response1->assertJsonPath('can_complete', false);
        expect($response1->json('missing_documents'))->toHaveCount(2);
    });

    it('shows increased completion after document upload', function () {
        actingAs($this->bacSecretariat);

        $validationService = mock(DocumentValidationService::class);

        // Check after one document uploaded: 50% complete
        $validationService->shouldReceive('validateStageCompletion')
            ->once()
            ->andReturn([
                'can_complete' => false,
                'completion_percentage' => 50,
                'missing_documents' => [
                    DocumentTypeEnums::PRE_PROCUREMENT_ATTENDANCE,
                ],
            ]);

        $this->instance(DocumentValidationService::class, $validationService);

        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.check-completion', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
        ]));

        $response->assertSuccessful();
        $response->assertJsonPath('completion_percentage', 50);
        $response->assertJsonPath('can_complete', false);
        expect($response->json('missing_documents'))->toHaveCount(1);
    });

    it('shows stage ready for completion when all required documents uploaded', function () {
        actingAs($this->bacSecretariat);

        $validationService = mock(DocumentValidationService::class);

        // All documents uploaded: 100% complete
        $validationService->shouldReceive('validateStageCompletion')
            ->once()
            ->andReturn([
                'can_complete' => true,
                'completion_percentage' => 100,
                'missing_documents' => [],
            ]);

        $this->instance(DocumentValidationService::class, $validationService);

        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.check-completion', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
        ]));

        $response->assertSuccessful();
        $response->assertJsonPath('completion_percentage', 100);
        $response->assertJsonPath('can_complete', true);
        $response->assertJsonPath('missing_documents', []);
    });

    it('validates document type before progressive upload', function () {
        $user = User::factory()->create();
        $user->assignRole('bac_secretariat');
        $this->actingAs($user);

        // Mock validation service to reject invalid document type
        $validationService = mock(\App\Services\DocumentValidationService::class);
        $validationService->shouldReceive('validateUpload')
            ->once()
            ->with(
                StageEnums::PRE_PROCUREMENT_CONFERENCE,
                DocumentTypeEnums::NOTICE_OF_AWARD, // Wrong stage!
                []
            )
            ->andReturn([
                'errors' => ['Document type NOTICE_OF_AWARD is not valid for stage PRE_PROCUREMENT_CONFERENCE'],
                'warnings' => [],
            ]);
        $this->instance(\App\Services\DocumentValidationService::class, $validationService);

        $repository = mock(ProcurementRepository::class);
        $repository->shouldReceive('findByProcurement')
            ->with('PR-2024-001')
            ->andReturn(
                new \App\DataTransferObjects\ProcurementData(
                    prNumber: 'PR-2024-001',
                    ppmpReference: 'PPMP-2024-001',
                    title: 'Test Procurement',
                    description: 'Test Description',
                    abcAmount: 1000000.00,
                    fundingSource: 'General Fund',
                    category: \App\Enums\ProcurementCategoryEnums::GOODS,
                    procurementMode: \App\Enums\ProcurementModeEnums::PUBLIC_BIDDING,
                    office: 'Test Office',
                    endUser: 'Test User',
                    purpose: 'Test Purpose',
                    deliveryLocation: 'Test Location',
                    deliveryDate: now(),
                    deliveryTermDays: 30,
                    preparedBy: 'Test Preparer',
                    bacResolutionNumber: null,
                    bacResolutionDate: null,
                    philgepsReference: null,
                    philgepsPostingDate: null,
                    approvedBy: null,
                    approvalDate: null,
                    status: 'in_progress',
                    userId: 'test@example.com',
                    createdAt: now()
                )
            );
        $this->instance(ProcurementRepository::class, $repository);

        $procurementDataService = mock(\App\Services\ProcurementDataService::class);
        $this->instance(\App\Services\ProcurementDataService::class, $procurementDataService);

        $file = UploadedFile::fake()->create('noa.pdf', 1000, 'application/pdf');

        $response = $this->withoutMiddleware('throttle:blockchain_writes')
            ->post(route('bac-secretariat.procurement.pre-procurement.upload-document', [
                'pr_number' => 'PR-2024-001',
                'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
            ]), [
                'document_file' => $file,
                'document_type' => DocumentTypeEnums::NOTICE_OF_AWARD->value, // Wrong stage!
                'description' => 'Invalid document for this stage',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('message');
    });
});
