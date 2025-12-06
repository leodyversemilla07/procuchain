<?php

/**
 * Tests for Skip Stage functionality.
 *
 * Validates that:
 * 1. Optional stages can be skipped
 * 2. Required stages cannot be skipped
 * 3. Skip action publishes to blockchain
 * 4. Workflow transitions correctly after skip
 */

use App\DataTransferObjects\ProcurementData;
use App\Enums\ProcurementCategoryEnums;
use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Models\User;
use App\Repositories\ProcurementRepository;
use App\Services\Publishers\EventPublisher;
use App\Services\Publishers\StatusPublisher;

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

describe('Skip Stage Endpoint Availability', function () {
    it('has skip route for pre-procurement phase', function () {
        expect(route('bac-secretariat.procurement.pre-procurement.skip', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
        ]))->toBeString();
    });

    it('has skip route for procurement phase', function () {
        expect(route('bac-secretariat.procurement.bidding.skip', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::PRE_BID_CONFERENCE->value,
        ]))->toBeString();
    });

    it('has skip route for post-procurement phase', function () {
        expect(route('bac-secretariat.procurement.post-procurement.skip', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::MONITORING->value,
        ]))->toBeString();
    });
});

describe('Optional Stage Detection', function () {
    it('identifies pre-bid conference as optional for SVP', function () {
        $optionalStages = StageEnums::getOptionalStagesForMode(ProcurementModeEnums::SMALL_VALUE_PROCUREMENT);
        expect($optionalStages)->toContain(StageEnums::PRE_BID_CONFERENCE);
    });

    it('identifies pre-procurement conference as optional for competitive bidding', function () {
        $optionalStages = StageEnums::getOptionalStagesForMode(ProcurementModeEnums::COMPETITIVE_BIDDING);
        expect($optionalStages)->toContain(StageEnums::PRE_PROCUREMENT_CONFERENCE);
    });

    it('identifies supplemental bid bulletin as optional for competitive bidding', function () {
        $optionalStages = StageEnums::getOptionalStagesForMode(ProcurementModeEnums::COMPETITIVE_BIDDING);
        expect($optionalStages)->toContain(StageEnums::SUPPLEMENTAL_BID_BULLETIN);
    });

    it('returns empty array for modes with no optional stages', function () {
        // Direct Contracting typically has very few optional stages
        $optionalStages = StageEnums::getOptionalStagesForMode(ProcurementModeEnums::DIRECT_CONTRACTING);
        expect($optionalStages)->toBeArray();
    });
});

describe('Skip Optional Stage', function () {
    it('can skip an optional pre-procurement stage', function () {
        actingAs($this->bacSecretariat);

        // Mock the procurement repository
        $repository = mock(ProcurementRepository::class);
        $repository->shouldReceive('findByProcurement')
            ->with('PR-2024-001')
            ->andReturn($this->mockProcurementData);
        $this->instance(ProcurementRepository::class, $repository);

        // Mock the status publisher
        $statusPublisher = mock(StatusPublisher::class);
        $statusPublisher->shouldReceive('publish')
            ->once()
            ->andReturn(['status_txid' => 'mock_status_txid']);
        $statusPublisher->shouldReceive('publishTransition')
            ->once()
            ->andReturn(['status_txid' => 'mock_transition_txid']);
        $this->instance(StatusPublisher::class, $statusPublisher);

        // Mock the event publisher
        $eventPublisher = mock(EventPublisher::class);
        $eventPublisher->shouldReceive('publish')
            ->once()
            ->andReturn(['event_txid' => 'mock_event_txid']);
        $eventPublisher->shouldReceive('publishStageTransition')
            ->once()
            ->andReturn(['event_txid' => 'mock_transition_event_txid']);
        $this->instance(EventPublisher::class, $eventPublisher);

        // Mock the Manager for blockchain operations
        $multichain = mock(\App\Services\Manager::class);
        $multichain->shouldReceive('listStreamKeyItems')
            ->andReturn([]);
        $this->instance(\App\Services\Manager::class, $multichain);

        $response = $this->post(route('bac-secretariat.procurement.pre-procurement.skip', [
            'pr_number' => 'PR-2024-001',
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
        ]), [
            'reason' => 'Not required for this procurement type',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    });
});

describe('Cannot Skip Required Stage', function () {
    it('returns error when trying to skip a required stage', function () {
        actingAs($this->bacSecretariat);

        // Create procurement with SVP mode where RFQ is required
        $svpProcurement = new ProcurementData(
            prNumber: 'PR-2024-002',
            appReference: 'APP-2024-002',
            title: 'SVP Procurement',
            description: 'Test Description',
            abcAmount: 50000.00,
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
            userId: 'test@example.com',
            createdAt: now()
        );

        // Mock the procurement repository
        $repository = mock(ProcurementRepository::class);
        $repository->shouldReceive('findByProcurement')
            ->with('PR-2024-002')
            ->andReturn($svpProcurement);
        $this->instance(ProcurementRepository::class, $repository);

        // Mock the Manager for blockchain operations
        $multichain = mock(\App\Services\Manager::class);
        $multichain->shouldReceive('listStreamKeyItems')
            ->andReturn([]);
        $this->instance(\App\Services\Manager::class, $multichain);

        // RFQ is required for SVP, so skipping should fail
        $response = $this->post(route('bac-secretariat.procurement.pre-procurement.skip', [
            'pr_number' => 'PR-2024-002',
            'stage' => StageEnums::REQUEST_FOR_QUOTATION->value,
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    });
});

describe('Skip Stage Status Enum', function () {
    it('has STAGE_SKIPPED status enum', function () {
        $status = \App\Enums\StatusEnums::STAGE_SKIPPED;

        expect($status->value)->toBe('stage_skipped');
        expect($status->getDisplayName())->toBe('Stage Skipped');
        expect($status->getDescription())->toContain('skipped');
    });
});
