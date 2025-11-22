<?php

use App\DataTransferObjects\ProcurementData;
use App\Enums\ProcurementCategory;
use App\Enums\ProcurementMode;
use App\Repositories\ProcurementRepository;
use App\Services\Manager;

beforeEach(function () {
    $this->mockMultichain = Mockery::mock(Manager::class);
    $this->repository = new ProcurementRepository($this->mockMultichain);
});

it('creates procurement metadata on blockchain', function () {
    $procurement = new ProcurementData(
        pr_number: 'test-id-123',
        prNumber: 'PR-2025-001',
        ppmpReference: 'PPMP-2025',
        title: 'Office Supplies Procurement',
        description: 'Purchase of office supplies for municipal office',
        abcAmount: 150000.00,
        fundingSource: 'General Fund',
        category: ProcurementCategory::Goods,
        procurementMode: ProcurementMode::Shopping,
        department: 'General Services',
        requestingOffice: 'Municipal Office',
        endUser: 'All Departments',
        purpose: 'Regular office operations',
        deliveryLocation: 'Municipal Hall',
        deliveryDate: now()->addDays(30),
        deliveryTermDays: 30,
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
            'test-id-123',
            Mockery::on(function ($data) {
                return isset($data['json'])
                    && $data['json']['pr_number'] === 'test-id-123'
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
                    'pr_number' => 'PR-2025-001',
                    'ppmp_reference' => 'PPMP-2025',
                    'title' => 'Office Supplies Procurement',
                    'description' => 'Purchase of office supplies',
                    'abc_amount' => '150000',
                    'funding_source' => 'General Fund',
                    'category' => 'goods',
                    'procurement_mode' => 'shopping',
                    'department' => 'General Services',
                    'requesting_office' => 'Municipal Office',
                    'end_user' => 'All Departments',
                    'purpose' => 'Regular operations',
                    'delivery_location' => 'Municipal Hall',
                    'delivery_date' => now()->addDays(30)->toIso8601String(),
                    'delivery_term_days' => 30,
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
        ->shouldReceive('listStreamKeyItems')
        ->once()
        ->with('procurement.metadata', 'test-id-123')
        ->andReturn($blockchainData);

    $procurement = $this->repository->find('test-id-123');

    expect($procurement)->toBeInstanceOf(ProcurementData::class)
        ->and($procurement->pr_number)->toBe('test-id-123')
        ->and($procurement->title)->toBe('Office Supplies Procurement')
        ->and($procurement->category)->toBe(ProcurementCategory::Goods)
        ->and($procurement->procurementMode)->toBe(ProcurementMode::Shopping);
});

it('returns null when procurement not found', function () {
    $this->mockMultichain
        ->shouldReceive('listStreamKeyItems')
        ->once()
        ->with('procurement.metadata', 'non-existent-id')
        ->andReturn([]);

    $procurement = $this->repository->find('non-existent-id');

    expect($procurement)->toBeNull();
});

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
