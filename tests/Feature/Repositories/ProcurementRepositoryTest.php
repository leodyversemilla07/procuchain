<?php

use App\DataTransferObjects\ProcurementData;
use App\Enums\ProcurementCategoryEnums;
use App\Enums\ProcurementModeEnums;
use App\Repositories\ProcurementRepository;
use App\Services\Manager;

beforeEach(function () {
    $this->mockMultichain = Mockery::mock(Manager::class);
    $this->repository = new ProcurementRepository($this->mockMultichain);
});

it('creates procurement metadata on blockchain', function () {
    $procurement = new ProcurementData(
        prNumber: 'PR-2025-001',
        appReference: 'APP-2025',
        title: 'Office Supplies Procurement',
        description: 'Purchase of office supplies for municipal office',
        abcAmount: 150000.00,
        fundingSource: 'General Fund',
        category: ProcurementCategoryEnums::GOODS,
        procurementMode: ProcurementModeEnums::SMALL_VALUE_PROCUREMENT,
        office: 'General Services',
        endUser: 'All Departments',
        // Delivery details are populated at Contract Implementation stage per NGPA IRR
        deliveryLocation: null,
        deliveryDate: null,
        deliveryTermDays: null,
        preparedBy: 'John Doe',
        approvedBy: null,
        approvalDate: null,
        bacResolutionNumber: null,
        bacResolutionDate: null,
        philgepsReference: null,
        philgepsPostingDate: null,
        status: 'draft',
        userId: '1',
        createdAt: now(),
    );

    $this->mockMultichain
        ->shouldReceive('publish')
        ->once()
        ->with(
            'procurement.metadata',
            'PR-2025-001',
            Mockery::on(function ($data) {
                return isset($data['json'])
                    && $data['json']['pr_number'] === 'PR-2025-001'
                    && $data['json']['title'] === 'Office Supplies Procurement'
                    && $data['json']['abc_amount'] === '150000'
                    && $data['json']['category'] === 'goods';
            })
        );

    $this->repository->create($procurement);
});

it('retrieves procurement from blockchain', function () {
    $blockchainData = [
        [
            'data' => [
                'json' => [
                    'pr_number' => 'test-id-123',
                    'app_reference' => 'APP-2025',
                    'title' => 'Office Supplies Procurement',
                    'description' => 'Purchase of office supplies',
                    'abc_amount' => '150000',
                    'funding_source' => 'General Fund',
                    'category' => 'goods',
                    'procurement_mode' => 'small_value_procurement',
                    'office' => 'General Services',
                    'end_user' => 'All Departments',
                    // Delivery details are populated at Contract Implementation stage
                    'delivery_location' => null,
                    'delivery_date' => null,
                    'delivery_term_days' => null,
                    'prepared_by' => 'John Doe',
                    'approved_by' => null,
                    'approval_date' => null,
                    'bac_resolution_number' => null,
                    'bac_resolution_date' => null,
                    'philgeps_reference' => null,
                    'philgeps_posting_date' => null,
                    'status' => 'draft',
                    'user_id' => '1',
                    'created_at' => now()->toIso8601String(),
                ],
            ],
            'blocktime' => time(),
            'txid' => 'abc123',
        ],
    ];

    $this->mockMultichain
        ->shouldReceive('liststreamkeyitems')
        ->once()
        ->with('procurement.metadata', 'test-id-123')
        ->andReturn($blockchainData);

    $procurement = $this->repository->findByProcurement('test-id-123');

    expect($procurement)->toBeInstanceOf(ProcurementData::class)
        ->and($procurement->prNumber)->toBe('test-id-123')
        ->and($procurement->title)->toBe('Office Supplies Procurement');
});

it('returns null when procurement not found', function () {
    $this->mockMultichain
        ->shouldReceive('liststreamkeyitems')
        ->once()
        ->with('procurement.metadata', 'non-existent-id')
        ->andReturn([]);

    $procurement = $this->repository->findByProcurement('non-existent-id');

    expect($procurement)->toBeNull();
});

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
