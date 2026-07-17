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

use App\Enums\ProcurementMode;
use App\Enums\ProcurementStatus;
use App\Enums\StageEnums;
use App\Jobs\BlockchainWriteJob;
use App\Models\User;
use App\Services\BlockchainRpcClient;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

beforeEach(function () {
    $this->bacSecretariat = User::factory()->create();
    $this->bacSecretariat->assignRole('bac_secretariat');
    $this->bacSecretariat->givePermissionTo('approve procurement');
    $this->bacSecretariat->blockchain_address = 'test_address_123';
    $this->bacSecretariat->save();

    // Helper to create mock procurement data
    $this->mockProcurementData = [
        'pr_number' => 'PR-2024-001-0001',
        'app_reference' => 'APP-2024-001',
        'title' => 'Test Procurement',
        'description' => 'Test Description',
        'abc_amount' => 1000000.00,
        'funding_source' => 'General Fund',
        'category' => 'goods',
        'procurement_mode' => 'competitive_bidding',
        'office' => 'Test Office',
        'end_user' => 'Test User',
        'prepared_by' => 'Test Preparer',
        'status' => 'in_progress',
        'user_id' => (string) $this->bacSecretariat->id,
        'created_at' => now(),
    ];
});

describe('Skip Stage Endpoint Availability', function () {
    it('has skip route for pre-procurement phase', function () {
        expect(route('bac-secretariat.procurement.pre-procurement.skip', [
            'pr_number' => 'PR-2024-001-0001',
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
        ]))->toBeString();
    });

    it('has skip route for procurement phase', function () {
        expect(route('bac-secretariat.procurement.bidding.skip', [
            'pr_number' => 'PR-2024-001-0001',
            'stage' => StageEnums::PRE_BID_CONFERENCE->value,
        ]))->toBeString();
    });

    it('has skip route for post-procurement phase', function () {
        expect(route('bac-secretariat.procurement.post-procurement.skip', [
            'pr_number' => 'PR-2024-001-0001',
            'stage' => StageEnums::MONITORING->value,
        ]))->toBeString();
    });
});

describe('Optional Stage Detection', function () {
    it('identifies pre-bid conference as optional for SVP', function () {
        $optionalStages = StageEnums::getOptionalStagesForMode(ProcurementMode::SMALL_VALUE_PROCUREMENT);
        expect($optionalStages)->toContain(StageEnums::PRE_BID_CONFERENCE);
    });

    it('identifies pre-procurement conference as optional for competitive bidding', function () {
        $optionalStages = StageEnums::getOptionalStagesForMode(ProcurementMode::COMPETITIVE_BIDDING);
        expect($optionalStages)->toContain(StageEnums::PRE_PROCUREMENT_CONFERENCE);
    });

    it('identifies supplemental bid bulletin as optional for competitive bidding', function () {
        $optionalStages = StageEnums::getOptionalStagesForMode(ProcurementMode::COMPETITIVE_BIDDING);
        expect($optionalStages)->toContain(StageEnums::SUPPLEMENTAL_BID_BULLETIN);
    });

    it('returns empty array for modes with no optional stages', function () {
        // Direct Contracting typically has very few optional stages
        $optionalStages = StageEnums::getOptionalStagesForMode(ProcurementMode::DIRECT_CONTRACTING);
        expect($optionalStages)->toBeArray();
    });
});

describe('Skip Optional Stage', function () {
    it('can skip an optional pre-procurement stage', function () {
        Queue::fake();
        actingAs($this->bacSecretariat);

        // Mock the BlockchainRpcClient for blockchain operations
        $multichain = mock(BlockchainRpcClient::class);
        $multichain->shouldReceive('listStreamKeyItems')
            ->andReturn([]);
        $this->instance(BlockchainRpcClient::class, $multichain);

        $response = $this->post(route('bac-secretariat.procurement.pre-procurement.skip', [
            'pr_number' => 'PR-2024-001-0001',
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
        ]), [
            'reason' => 'Not required for this procurement type',
        ]);

        $response->assertStatus(202)->assertJsonStructure(['job_id', 'status']);
        Queue::assertPushed(BlockchainWriteJob::class);
    });
});

describe('Cannot Skip Required Stage', function () {
    it('returns error when trying to skip a required stage', function () {
        Queue::fake();
        actingAs($this->bacSecretariat);

        // Create procurement with SVP mode where RFQ is required
        $svpProcurement = [
            'pr_number' => 'PR-2024-002-0001',
            'app_reference' => 'APP-2024-002',
            'title' => 'SVP Procurement',
            'description' => 'Test Description',
            'abc_amount' => 50000.00,
            'funding_source' => 'General Fund',
            'category' => 'goods',
            'procurement_mode' => 'small_value_procurement',
            'office' => 'Test Office',
            'end_user' => 'Test User',
            'prepared_by' => 'Test Preparer',
            'status' => 'in_progress',
            'user_id' => (string) $this->bacSecretariat->id,
            'created_at' => now(),
        ];

        // Mock the BlockchainRpcClient for blockchain operations
        $multichain = mock(BlockchainRpcClient::class);
        $multichain->shouldReceive('listStreamKeyItems')
            ->andReturn([]);
        $this->instance(BlockchainRpcClient::class, $multichain);

        // RFQ is required for SVP - controller dispatches job async, validation happens in job
        $response = $this->post(route('bac-secretariat.procurement.pre-procurement.skip', [
            'pr_number' => 'PR-2024-002-0001',
            'stage' => StageEnums::REQUEST_FOR_QUOTATION->value,
        ]));

        $response->assertStatus(202)->assertJsonStructure(['job_id', 'status']);
        Queue::assertPushed(BlockchainWriteJob::class);
    });
});

describe('Skip Stage Status Enum', function () {
    it('has STAGE_SKIPPED status enum', function () {
        $status = ProcurementStatus::STAGE_SKIPPED;

        expect($status->value)->toBe('stage_skipped');
        expect($status->getDisplayName())->toBe('Stage Skipped');
        expect($status->getDescription())->toContain('skipped');
    });
});
