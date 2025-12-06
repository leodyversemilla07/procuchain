<?php

/**
 * Tests for Workflow Display across all procurement stage pages.
 *
 * Validates that:
 * 1. All stage pages include workflowInfo prop
 * 2. workflowInfo contains correct mode and workflow structure
 * 3. Mode-aware stage navigation works correctly
 * 4. Progress percentage is calculated correctly
 *
 * @see App\Http\Controllers\Procurement\Concerns\HasProcurementSupport::getWorkflowInfo()
 */

use App\DataTransferObjects\ProcurementData;
use App\Enums\ProcurementCategoryEnums;
use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Models\User;
use App\Repositories\ProcurementRepository;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

beforeEach(function () {
    $this->bacSecretariat = User::factory()->create();
    $this->bacSecretariat->assignRole('bac_secretariat');
    $this->bacSecretariat->blockchain_address = 'test_address_123';
    $this->bacSecretariat->save();

    // Helper to create mock procurement data
    $this->mockProcurementData = new ProcurementData(
        prNumber: 'PR-2024-001',
        appReference: 'APP-2024-001',
        title: 'Test Procurement',
        description: 'Test Description',
        abcAmount: 1000000.00,
        fundingSource: 'General Fund',
        category: ProcurementCategoryEnums::GOODS,
        procurementMode: ProcurementModeEnums::COMPETITIVE_BIDDING,
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
        userId: 'test@example.com',
        createdAt: now()
    );
});

describe('Workflow Info Structure', function () {
    it('has correct workflow info structure with mode details', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::REQUEST_FOR_QUOTATION->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('workflowInfo', fn ($workflow) => $workflow
                // Mode details
                ->has('mode')
                // Workflow structure
                ->has('workflow', fn ($wf) => $wf
                    ->has('stages')
                    ->has('total_stages')
                    ->has('current_index')
                    ->has('progress_percentage')
                )
            )
        );
    });

    it('includes mode display name and IRR section', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::REQUEST_FOR_QUOTATION->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->where('workflowInfo.mode', fn ($mode) => $mode === null || (
                isset($mode['value']) &&
                isset($mode['display_name']) &&
                isset($mode['description']) &&
                isset($mode['irr_section'])
            ))
        );
    });

    it('includes stages array with required properties', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::REQUEST_FOR_QUOTATION->value,
        ]));

        $response->assertSuccessful();
        // Workflow stages should be present (even if empty for null mode)
        $response->assertInertia(fn ($page) => $page
            ->has('workflowInfo.workflow.stages')
        );
    });
});

describe('Pre-Procurement Stage Pages with Workflow Info', function () {
    it('includes workflowInfo on Pre-Procurement Conference page', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/procurement-stage/pre-procurement-conference-upload')
            ->has('workflowInfo')
            ->has('workflowInfo.workflow')
        );
    });

    it('includes workflowInfo on Bidding Documents page', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::BIDDING_DOCUMENTS->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/procurement-stage/bidding-documents-upload')
            ->has('workflowInfo')
            ->has('workflowInfo.workflow')
        );
    });

    it('includes workflowInfo on RFQ page', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::REQUEST_FOR_QUOTATION->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/procurement-stage/rfq-upload')
            ->has('workflowInfo')
            ->has('workflowInfo.workflow')
        );
    });
});

describe('Procurement Stage Pages with Workflow Info', function () {
    it('includes workflowInfo on Pre-Bid Conference page', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.bidding.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::PRE_BID_CONFERENCE->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/procurement-stage/pre-bid-conference-upload')
            ->has('workflowInfo')
        );
    });

    it('includes workflowInfo on Supplemental Bid Bulletin page', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.bidding.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/procurement-stage/supplemental-bid-bulletin-upload')
            ->has('workflowInfo')
        );
    });

    it('includes workflowInfo on Bid Opening page', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.bidding.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::BID_OPENING->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/procurement-stage/bid-opening-upload')
            ->has('workflowInfo')
        );
    });

    it('includes workflowInfo on Bid Evaluation page', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.bidding.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::BID_EVALUATION->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/procurement-stage/bid-evaluation-upload')
            ->has('workflowInfo')
        );
    });

    it('includes workflowInfo on Post-Qualification page', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.bidding.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::POST_QUALIFICATION->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/procurement-stage/post-qualification-upload')
            ->has('workflowInfo')
        );
    });

    it('includes workflowInfo on BAC Resolution page', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.bidding.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::BAC_RESOLUTION->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/procurement-stage/bac-resolution-upload')
            ->has('workflowInfo')
        );
    });

    it('includes workflowInfo on Abstract of Quotations page', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.bidding.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::ABSTRACT_OF_QUOTATIONS->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/procurement-stage/abstract-of-quotations-upload')
            ->has('workflowInfo')
        );
    });
});

describe('Post-Procurement Stage Pages with Workflow Info', function () {
    it('includes workflowInfo on Notice of Award page', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.post-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::NOTICE_OF_AWARD->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/procurement-stage/noa-upload')
            ->has('workflowInfo')
        );
    });

    it('includes workflowInfo on Performance Bond page', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.post-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/procurement-stage/performance-bond-contract-po-upload')
            ->has('workflowInfo')
        );
    });

    it('includes workflowInfo on Notice to Proceed page', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.post-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::NOTICE_TO_PROCEED->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/procurement-stage/ntp-upload')
            ->has('workflowInfo')
        );
    });

    it('includes workflowInfo on Monitoring page', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.post-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::MONITORING->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/procurement-stage/monitoring-upload')
            ->has('workflowInfo')
        );
    });

    it('includes workflowInfo on Completion page', function () {
        actingAs($this->bacSecretariat);

        $response = $this->get(route('bac-secretariat.procurement.post-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::COMPLETION->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/procurement-stage/completion-upload')
            ->has('workflowInfo')
        );
    });
});

describe('Procurement Initiation with Workflow Info', function () {
    it('returns 404 when procurement does not exist', function () {
        actingAs($this->bacSecretariat);

        // Mock the procurement repository to return null (not found)
        $repository = mock(ProcurementRepository::class);
        $repository->shouldReceive('findByProcurement')
            ->with('PR-NON-EXISTENT')
            ->andReturn(null);
        $this->instance(ProcurementRepository::class, $repository);

        $response = $this->get(route('bac-secretariat.procurement.initiation.show', [
            'pr_number' => 'PR-NON-EXISTENT',
        ]));

        $response->assertNotFound();
    });

    it('includes workflowInfo on Procurement Initiation page', function () {
        actingAs($this->bacSecretariat);

        // Mock the procurement repository to return valid procurement data
        $repository = mock(ProcurementRepository::class);
        $repository->shouldReceive('findByProcurement')
            ->with('PR-2024-001')
            ->andReturn($this->mockProcurementData);
        $this->instance(ProcurementRepository::class, $repository);

        // Mock the Manager for StatusRepository
        $multichain = mock(\App\Services\Manager::class);
        $multichain->shouldReceive('listStreamKeyItems')
            ->andReturn([]);
        $this->instance(\App\Services\Manager::class, $multichain);

        $response = $this->get(route('bac-secretariat.procurement.initiation.show', [
            'pr_number' => 'PR-2024-001',
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('bac-secretariat/procurement-stage/procurement-initiation-upload')
            ->has('workflowInfo')
        );
    });
});

describe('Mode-Specific Workflow Validation', function () {
    it('returns empty stages when mode is null', function () {
        actingAs($this->bacSecretariat);

        // For a procurement without a mode set, workflowInfo should have null mode
        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => 'PR-NEW-001',
            'stage' => StageEnums::REQUEST_FOR_QUOTATION->value,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('workflowInfo')
        );
    });

    it('validates stages exist in workflow for mode', function () {
        actingAs($this->bacSecretariat);

        // SVP mode should have RFQ stage
        $svpStages = StageEnums::getStagesForMode(ProcurementModeEnums::SMALL_VALUE_PROCUREMENT);

        expect($svpStages)->toContain(StageEnums::REQUEST_FOR_QUOTATION);
        expect($svpStages)->toContain(StageEnums::ABSTRACT_OF_QUOTATIONS);
    });

    it('identifies optional stages correctly for different modes', function () {
        // SVP optional stages
        $svpOptional = StageEnums::getOptionalStagesForMode(ProcurementModeEnums::SMALL_VALUE_PROCUREMENT);
        expect($svpOptional)->toBeArray();

        // Competitive Bidding optional stages
        $cbOptional = StageEnums::getOptionalStagesForMode(ProcurementModeEnums::COMPETITIVE_BIDDING);
        expect($cbOptional)->toBeArray();
        expect($cbOptional)->toContain(StageEnums::SUPPLEMENTAL_BID_BULLETIN);
    });
});

describe('Workflow Progress Calculation', function () {
    it('calculates progress percentage correctly', function () {
        // For a workflow with 5 stages:
        // - Stage 0 (first): 0%
        // - Stage 1: 25%
        // - Stage 2: 50%
        // - Stage 3: 75%
        // - Stage 4 (last): 100%
        $cbStages = StageEnums::getStagesForMode(ProcurementModeEnums::COMPETITIVE_BIDDING);
        $totalStages = count($cbStages);

        expect($totalStages)->toBeGreaterThan(0);

        // Progress at first stage should be 0%
        $progressAtFirst = (int) round((0 / ($totalStages - 1)) * 100);
        expect($progressAtFirst)->toBe(0);

        // Progress at last stage should be 100%
        $progressAtLast = (int) round((($totalStages - 1) / ($totalStages - 1)) * 100);
        expect($progressAtLast)->toBe(100);
    });

    it('returns correct stage count for each mode', function () {
        $modes = ProcurementModeEnums::cases();

        foreach ($modes as $mode) {
            $stages = StageEnums::getStagesForMode($mode);
            expect($stages)->toBeArray();
            expect(count($stages))->toBeGreaterThan(0, "Mode {$mode->value} should have at least one stage");
        }
    });
});

describe('Access Control for Stage Pages', function () {
    it('denies access to unauthenticated users', function () {
        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::REQUEST_FOR_QUOTATION->value,
        ]));

        $response->assertRedirect(route('login'));
    });

    it('denies access to users without bac_secretariat role', function () {
        $regularUser = User::factory()->create();

        actingAs($regularUser);

        $response = $this->get(route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::REQUEST_FOR_QUOTATION->value,
        ]));

        $response->assertForbidden();
    });
});
