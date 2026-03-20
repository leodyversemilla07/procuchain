<?php

use App\DataTransferObjects\ProcurementData;
use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementCategoryEnums;
use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Models\User;
use App\Repositories\ProcurementRepository;
use App\Services\DocumentValidationService;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

beforeEach(function () {
    $this->bacSecretariat = User::factory()->create();
    $this->bacSecretariat->assignRole('bac_secretariat');
    $this->bacSecretariat->blockchain_address = 'test_address_123';
    $this->bacSecretariat->save();

    // Mock SVP procurement data (RFQ and Abstract stages are in SVP workflow)
    $this->svpProcurementData = new ProcurementData(
        prNumber: 'PR-2024-001',
        appReference: 'APP-2024-001',
        title: 'Test Procurement',
        description: 'Test Description',
        abcAmount: 100000.00,
        fundingSource: 'General Fund',
        category: ProcurementCategoryEnums::GOODS,
        procurementMode: ProcurementModeEnums::SMALL_VALUE_PROCUREMENT,
        office: 'Test Office',
        endUser: 'Test User',
        deliveryLocation: null,
        deliveryDate: null,
        deliveryTermDays: null,
        preparedBy: 'Test Preparer',
        bacResolutionNumber: null,
        bacResolutionDate: null,
        philgepsReference: null,
        philgepsPostingDate: null,
        approvedBy: null,
        approvalDate: null,
        status: 'in_progress',
        userId: (string) $this->bacSecretariat->id,
        createdAt: now()
    );

    // Helper to mock the repository with SVP procurement
    $this->mockSvpRepository = function () {
        $repository = mock(ProcurementRepository::class);
        $repository->shouldReceive('findByProcurement')->andReturn($this->svpProcurementData);
        $this->instance(ProcurementRepository::class, $repository);
    };
});

describe('Request for Quotation (RFQ) Stage', function () {
    it('shows RFQ stage page for authorized users', function () {
        actingAs($this->bacSecretariat);
        ($this->mockSvpRepository)();

        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::REQUEST_FOR_QUOTATION->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/stage-upload')
            ->has('procurement')
            ->has('documentGuide')
            ->has('workflowInfo')
        );
    });

    it('includes workflowInfo with mode details for RFQ page', function () {
        actingAs($this->bacSecretariat);
        ($this->mockSvpRepository)();

        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::REQUEST_FOR_QUOTATION->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('workflowInfo', fn ($workflow) => $workflow
                ->has('mode')
                ->has('workflow')
                ->has('workflow.stages')
                ->has('workflow.total_stages')
                ->has('workflow.current_index')
                ->has('workflow.progress_percentage')
            )
        );
    });

    it('provides document guide for RFQ stage', function () {
        actingAs($this->bacSecretariat);
        ($this->mockSvpRepository)();

        $validationService = mock(DocumentValidationService::class);
        $validationService->shouldReceive('getStageDocumentGuide')
            ->andReturn([
                'required' => [DocumentTypeEnums::REQUEST_FOR_QUOTATION],
                'optional' => [],
                'uploaded' => [],
            ]);
        $this->instance(DocumentValidationService::class, $validationService);

        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.document-guide', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::REQUEST_FOR_QUOTATION->value,
        ]));

        $response->assertSuccessful();
    });

    it('checks RFQ stage completion status', function () {
        actingAs($this->bacSecretariat);

        $validationService = mock(DocumentValidationService::class);
        $validationService->shouldReceive('validateStageCompletion')
            ->andReturn([
                'can_complete' => false,
                'completion_percentage' => 0,
                'missing_documents' => [DocumentTypeEnums::REQUEST_FOR_QUOTATION->value],
            ]);

        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.check-completion', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::REQUEST_FOR_QUOTATION->value,
        ]));

        $response->assertSuccessful();
    });
});

describe('Abstract of Quotations Stage', function () {
    it('shows Abstract of Quotations stage page for authorized users', function () {
        actingAs($this->bacSecretariat);
        ($this->mockSvpRepository)();

        $response = $this->get(route('bac-secretariat.procurement.bidding.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::ABSTRACT_OF_QUOTATIONS->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/stage-upload')
            ->has('procurement')
            ->has('documentGuide')
            ->has('workflowInfo')
        );
    });

    it('includes workflowInfo with mode details for Abstract page', function () {
        actingAs($this->bacSecretariat);
        ($this->mockSvpRepository)();

        $response = $this->get(route('bac-secretariat.procurement.bidding.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::ABSTRACT_OF_QUOTATIONS->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('workflowInfo', fn ($workflow) => $workflow
                ->has('mode')
                ->has('workflow')
                ->has('workflow.stages')
                ->has('workflow.total_stages')
                ->has('workflow.current_index')
                ->has('workflow.progress_percentage')
            )
        );
    });

    it('provides document guide for Abstract stage', function () {
        actingAs($this->bacSecretariat);
        ($this->mockSvpRepository)();

        $validationService = mock(DocumentValidationService::class);
        $validationService->shouldReceive('getStageDocumentGuide')
            ->andReturn([
                'required' => [DocumentTypeEnums::ABSTRACT_OF_QUOTATIONS],
                'optional' => [],
                'uploaded' => [],
            ]);
        $this->instance(DocumentValidationService::class, $validationService);

        $response = $this->get(route('bac-secretariat.procurement.bidding.document-guide', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::ABSTRACT_OF_QUOTATIONS->value,
        ]));

        $response->assertSuccessful();
    });

    it('checks Abstract stage completion status', function () {
        actingAs($this->bacSecretariat);

        $validationService = mock(DocumentValidationService::class);
        $validationService->shouldReceive('validateStageCompletion')
            ->andReturn([
                'can_complete' => false,
                'completion_percentage' => 0,
                'missing_documents' => [DocumentTypeEnums::ABSTRACT_OF_QUOTATIONS->value],
            ]);

        $response = $this->get(route('bac-secretariat.procurement.bidding.check-completion', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::ABSTRACT_OF_QUOTATIONS->value,
        ]));

        $response->assertSuccessful();
    });
});

describe('Mode-Aware Stage Navigation', function () {
    it('validates RFQ stage exists in SVP mode workflow', function () {
        $stages = StageEnums::getStagesForMode(ProcurementModeEnums::SMALL_VALUE_PROCUREMENT);

        expect($stages)->toContain(StageEnums::REQUEST_FOR_QUOTATION);
        expect($stages)->toContain(StageEnums::ABSTRACT_OF_QUOTATIONS);
    });

    it('validates RFQ stage does not exist in Competitive Bidding workflow', function () {
        $stages = StageEnums::getStagesForMode(ProcurementModeEnums::COMPETITIVE_BIDDING);

        expect($stages)->not->toContain(StageEnums::REQUEST_FOR_QUOTATION);
        expect($stages)->not->toContain(StageEnums::ABSTRACT_OF_QUOTATIONS);
    });

    it('validates correct next stage for RFQ in SVP mode', function () {
        $nextStages = StageEnums::REQUEST_FOR_QUOTATION->getNextStagesForMode(
            ProcurementModeEnums::SMALL_VALUE_PROCUREMENT
        );

        expect($nextStages)->toContain(StageEnums::ABSTRACT_OF_QUOTATIONS);
    });

    it('validates correct next stage for Abstract in SVP mode', function () {
        $nextStages = StageEnums::ABSTRACT_OF_QUOTATIONS->getNextStagesForMode(
            ProcurementModeEnums::SMALL_VALUE_PROCUREMENT
        );

        expect($nextStages)->toContain(StageEnums::BAC_RESOLUTION);
    });

    it('validates Direct Contracting mode includes RFQ stage', function () {
        $stages = StageEnums::getStagesForMode(ProcurementModeEnums::DIRECT_CONTRACTING);

        expect($stages)->toContain(StageEnums::REQUEST_FOR_QUOTATION);
    });

    it('validates Repeat Order mode includes RFQ stage', function () {
        $stages = StageEnums::getStagesForMode(ProcurementModeEnums::REPEAT_ORDER);

        expect($stages)->toContain(StageEnums::REQUEST_FOR_QUOTATION);
    });

    it('validates Direct Sales mode includes RFQ stage', function () {
        $stages = StageEnums::getStagesForMode(ProcurementModeEnums::DIRECT_SALES);

        expect($stages)->toContain(StageEnums::REQUEST_FOR_QUOTATION);
    });

    it('validates Direct Procurement for STI includes RFQ stage', function () {
        $stages = StageEnums::getStagesForMode(ProcurementModeEnums::DIRECT_PROCUREMENT_FOR_STI);

        expect($stages)->toContain(StageEnums::REQUEST_FOR_QUOTATION);
    });

    it('validates only SVP mode has Abstract of Quotations stage', function () {
        // SVP has Abstract
        $svpStages = StageEnums::getStagesForMode(ProcurementModeEnums::SMALL_VALUE_PROCUREMENT);
        expect($svpStages)->toContain(StageEnums::ABSTRACT_OF_QUOTATIONS);

        // Direct Contracting does not have Abstract
        $dcStages = StageEnums::getStagesForMode(ProcurementModeEnums::DIRECT_CONTRACTING);
        expect($dcStages)->not->toContain(StageEnums::ABSTRACT_OF_QUOTATIONS);
    });
});

describe('Stage Display Names and Storage Paths', function () {
    it('has correct display name for RFQ stage', function () {
        expect(StageEnums::REQUEST_FOR_QUOTATION->getDisplayName())
            ->toBe('Request for Quotation');
    });

    it('has correct display name for Abstract stage', function () {
        expect(StageEnums::ABSTRACT_OF_QUOTATIONS->getDisplayName())
            ->toBe('Abstract of Quotations');
    });

    it('has correct storage path for RFQ stage', function () {
        expect(StageEnums::REQUEST_FOR_QUOTATION->getStoragePathSegment())
            ->toBe('RequestForQuotation');
    });

    it('has correct storage path for Abstract stage', function () {
        expect(StageEnums::ABSTRACT_OF_QUOTATIONS->getStoragePathSegment())
            ->toBe('AbstractOfQuotations');
    });

    it('has correct description for RFQ stage', function () {
        expect(StageEnums::REQUEST_FOR_QUOTATION->getDescription())
            ->toContain('RFQ');
    });

    it('has correct description for Abstract stage', function () {
        expect(StageEnums::ABSTRACT_OF_QUOTATIONS->getDescription())
            ->toContain('quotations');
    });
});

describe('Authorization for New Stages', function () {
    it('denies RFQ access to non-bac-secretariat users', function () {
        $regularUser = User::factory()->create();

        actingAs($regularUser);

        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::REQUEST_FOR_QUOTATION->value,
        ]));

        $response->assertForbidden();
    });

    it('denies Abstract access to non-bac-secretariat users', function () {
        $regularUser = User::factory()->create();

        actingAs($regularUser);

        $response = $this->get(route('bac-secretariat.procurement.bidding.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::ABSTRACT_OF_QUOTATIONS->value,
        ]));

        $response->assertForbidden();
    });

    it('denies RFQ access to unauthenticated users', function () {
        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::REQUEST_FOR_QUOTATION->value,
        ]));

        $response->assertRedirect(route('login'));
    });

    it('denies Abstract access to unauthenticated users', function () {
        $response = $this->get(route('bac-secretariat.procurement.bidding.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::ABSTRACT_OF_QUOTATIONS->value,
        ]));

        $response->assertRedirect(route('login'));
    });
});
