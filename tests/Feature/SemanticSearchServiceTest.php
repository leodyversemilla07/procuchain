<?php

use App\Services\ProcurementDataService;
use App\Services\SemanticSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->mockProcurementDataService = Mockery::mock(ProcurementDataService::class);
    $this->app->instance(ProcurementDataService::class, $this->mockProcurementDataService);
});

test('semantic search service searches without filters', function () {
    $mockData = [
        [
            'id' => 'PR-001',
            'title' => 'Test Procurement',
            'current_status' => 'active',
            'stage' => 'bidding',
            'created_at' => '2025-01-15',
        ],
    ];

    $this->mockProcurementDataService
        ->shouldReceive('fetchAndProcessProcurements')
        ->once()
        ->andReturn($mockData);

    $service = new SemanticSearchService($this->mockProcurementDataService);
    $results = $service->search('test');

    expect($results)->toHaveKey('success')
        ->and($results['success'])->toBeTrue()
        ->and($results)->toHaveKey('results')
        ->and($results['total'])->toBe(1);
});

test('semantic search service filters by status', function () {
    $mockData = [
        [
            'id' => 'PR-001',
            'title' => 'Active Procurement',
            'current_status' => 'active',
            'stage' => 'bidding',
            'created_at' => '2025-01-15',
        ],
        [
            'id' => 'PR-002',
            'title' => 'Completed Procurement',
            'current_status' => 'completed',
            'stage' => 'post_procurement',
            'created_at' => '2025-01-16',
        ],
    ];

    $this->mockProcurementDataService
        ->shouldReceive('fetchAndProcessProcurements')
        ->once()
        ->andReturn($mockData);

    $service = new SemanticSearchService($this->mockProcurementDataService);
    $results = $service->search('', ['status' => 'active']);

    expect($results['success'])->toBeTrue()
        ->and($results['total'])->toBe(1)
        ->and($results['results'][0]['id'])->toBe('PR-001');
});

test('semantic search service filters by date range', function () {
    $mockData = [
        [
            'id' => 'PR-001',
            'title' => 'January Procurement',
            'current_status' => 'active',
            'stage' => 'bidding',
            'created_at' => '2025-01-15',
        ],
        [
            'id' => 'PR-002',
            'title' => 'February Procurement',
            'current_status' => 'active',
            'stage' => 'bidding',
            'created_at' => '2025-02-15',
        ],
    ];

    $this->mockProcurementDataService
        ->shouldReceive('fetchAndProcessProcurements')
        ->once()
        ->andReturn($mockData);

    $service = new SemanticSearchService($this->mockProcurementDataService);
    $results = $service->search('', [
        'date_from' => '2025-01-01',
        'date_to' => '2025-01-31',
    ]);

    expect($results['success'])->toBeTrue()
        ->and($results['total'])->toBe(1)
        ->and($results['results'][0]['id'])->toBe('PR-001');
});

test('semantic search service calculates statistics correctly', function () {
    $mockData = [
        [
            'id' => 'PR-001',
            'title' => 'Procurement 1',
            'current_status' => 'active',
            'stage' => 'bidding',
            'mode' => 'public_bidding',
            'category' => 'goods',
            'abc_amount' => 100000,
            'created_at' => '2025-01-15',
        ],
        [
            'id' => 'PR-002',
            'title' => 'Procurement 2',
            'current_status' => 'active',
            'stage' => 'bidding',
            'mode' => 'public_bidding',
            'category' => 'services',
            'abc_amount' => 200000,
            'created_at' => '2025-01-16',
        ],
    ];

    $service = new SemanticSearchService($this->mockProcurementDataService);
    $statistics = $service->calculateStatistics($mockData);

    expect($statistics)->toHaveKey('total_count')
        ->and($statistics['total_count'])->toBe(2)
        ->and($statistics)->toHaveKey('by_status')
        ->and($statistics['by_status']['active'])->toBe(2)
        ->and($statistics)->toHaveKey('total_abc_amount')
        ->and($statistics['total_abc_amount'])->toBe(300000.0);
});

test('semantic search service handles empty results', function () {
    $this->mockProcurementDataService
        ->shouldReceive('fetchAndProcessProcurements')
        ->once()
        ->andReturn([]);

    $service = new SemanticSearchService($this->mockProcurementDataService);
    $results = $service->search('nonexistent');

    expect($results['success'])->toBeTrue()
        ->and($results['total'])->toBe(0)
        ->and($results['results'])->toBeEmpty();
});

test('semantic search service handles exceptions', function () {
    $this->mockProcurementDataService
        ->shouldReceive('fetchAndProcessProcurements')
        ->once()
        ->andThrow(new Exception('Test exception'));

    $service = new SemanticSearchService($this->mockProcurementDataService);
    $results = $service->search('test');

    expect($results['success'])->toBeFalse()
        ->and($results)->toHaveKey('error')
        ->and($results['total'])->toBe(0);
});
