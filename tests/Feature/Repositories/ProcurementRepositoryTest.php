<?php

use App\DataTransferObjects\ProcurementData;
use App\Enums\ProcurementCategoryEnums;
use App\Enums\ProcurementModeEnums;
use App\Models\Procurement;
use App\Repositories\ProcurementRepository;
use App\Services\Manager;

beforeEach(function () {
    $this->mockMultichain = Mockery::mock(Manager::class);
    $this->repository = new ProcurementRepository($this->mockMultichain);
});

it('creates procurement metadata on blockchain', function () {
    $procurement = new ProcurementData(
        prNumber: 'PR-2025-001-0001',
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
            'PR-2025-001-0001',
            Mockery::on(function ($data) {
                return isset($data['json'])
                    && $data['json']['pr_number'] === 'PR-2025-001-0001'
                    && $data['json']['title'] === 'Office Supplies Procurement'
                    && $data['json']['abc_amount'] === '150000'
                    && $data['json']['category'] === 'goods';
            })
        )
        ->andReturn('metadata-txid-123');

    $this->repository->create($procurement);
});

it('retrieves procurement from the normalized table', function () {
    Procurement::create([
        'pr_number' => 'test-id-123',
        'app_reference' => 'APP-2025',
        'title' => 'Office Supplies Procurement',
        'description' => 'Purchase of office supplies',
        'abc_amount' => 150000,
        'fund_source' => 'General Fund',
        'category' => 'goods',
        'procurement_mode' => 'small_value_procurement',
        'office' => 'General Services',
        'end_user' => 'All Departments',
        'prepared_by' => 'John Doe',
        'current_status' => 'draft',
        'user_id' => '1',
        'initiated_at' => now(),
    ]);

    $procurement = $this->repository->findByProcurement('test-id-123');

    expect($procurement)->toBeInstanceOf(ProcurementData::class)
        ->and($procurement->prNumber)->toBe('test-id-123')
        ->and($procurement->title)->toBe('Office Supplies Procurement')
        ->and($procurement->userId)->toBe('1');
});

it('returns null when procurement not found', function () {
    $procurement = $this->repository->findByProcurement('non-existent-id');

    expect($procurement)->toBeNull();
});

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
