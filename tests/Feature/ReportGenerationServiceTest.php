<?php

use App\Services\ProcurementDataService;
use App\Services\ReportGenerationService;
use App\Services\SemanticSearchService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->mockProcurementDataService = Mockery::mock(ProcurementDataService::class);
    $this->mockSemanticSearchService = Mockery::mock(SemanticSearchService::class);
    $this->app->instance(ProcurementDataService::class, $this->mockProcurementDataService);
});

test('report generation service generates report with month filter', function () {
    $mockResults = [
        'success' => true,
        'query' => '',
        'filters' => [
            'date_from' => '2025-01-01',
            'date_to' => '2025-01-31',
        ],
        'total' => 5,
        'results' => [
            [
                'id' => 'PR-001',
                'title' => 'Test Procurement',
                'current_status' => 'active',
                'stage' => 'bidding',
                'created_at' => '2025-01-15',
                'abc_amount' => 100000,
            ],
        ],
    ];

    $this->mockSemanticSearchService
        ->shouldReceive('search')
        ->once()
        ->andReturn($mockResults);

    $this->mockSemanticSearchService
        ->shouldReceive('calculateStatistics')
        ->once()
        ->andReturn([
            'total_count' => 1,
            'by_status' => ['active' => 1],
            'by_stage' => ['bidding' => 1],
            'by_mode' => [],
            'by_category' => [],
            'total_abc_amount' => 100000,
        ]);

    $service = new ReportGenerationService($this->mockSemanticSearchService);
    $report = $service->generateReport([
        'filter_type' => 'month',
        'month' => 1,
        'year' => 2025,
    ]);

    expect($report['success'])->toBeTrue()
        ->and($report)->toHaveKey('summary')
        ->and($report)->toHaveKey('time_series')
        ->and($report)->toHaveKey('data');
});

test('report generation service generates report with quarter filter', function () {
    $mockResults = [
        'success' => true,
        'query' => '',
        'filters' => [
            'date_from' => '2025-01-01',
            'date_to' => '2025-03-31',
        ],
        'total' => 3,
        'results' => [],
    ];

    $this->mockSemanticSearchService
        ->shouldReceive('search')
        ->once()
        ->andReturn($mockResults);

    $this->mockSemanticSearchService
        ->shouldReceive('calculateStatistics')
        ->once()
        ->andReturn([
            'total_count' => 0,
            'by_status' => [],
            'by_stage' => [],
            'by_mode' => [],
            'by_category' => [],
            'total_abc_amount' => 0,
        ]);

    $service = new ReportGenerationService($this->mockSemanticSearchService);
    $report = $service->generateReport([
        'filter_type' => 'quarter',
        'quarter' => 1,
        'year' => 2025,
    ]);

    expect($report['success'])->toBeTrue()
        ->and($report['parameters']['filter_type'])->toBe('quarter');
});

test('report generation service generates report with year filter', function () {
    $mockResults = [
        'success' => true,
        'query' => '',
        'filters' => [
            'date_from' => '2025-01-01',
            'date_to' => '2025-12-31',
        ],
        'total' => 10,
        'results' => [],
    ];

    $this->mockSemanticSearchService
        ->shouldReceive('search')
        ->once()
        ->andReturn($mockResults);

    $this->mockSemanticSearchService
        ->shouldReceive('calculateStatistics')
        ->once()
        ->andReturn([
            'total_count' => 0,
            'by_status' => [],
            'by_stage' => [],
            'by_mode' => [],
            'by_category' => [],
            'total_abc_amount' => 0,
        ]);

    $service = new ReportGenerationService($this->mockSemanticSearchService);
    $report = $service->generateReport([
        'filter_type' => 'year',
        'year' => 2025,
    ]);

    expect($report['success'])->toBeTrue()
        ->and($report['parameters']['filter_type'])->toBe('year');
});

test('report generation service exports to CSV', function () {
    $reportData = [
        'success' => true,
        'data' => [
            [
                'id' => 'PR-001',
                'title' => 'Test Procurement',
                'current_status' => 'active',
                'stage' => 'bidding',
                'mode' => 'public_bidding',
                'category' => 'goods',
                'abc_amount' => 100000,
                'created_at' => '2025-01-15',
            ],
        ],
    ];

    $service = new ReportGenerationService($this->mockSemanticSearchService);
    $csv = $service->exportReport($reportData, 'csv');

    expect($csv)->toBeString()
        ->and($csv)->toContain('ID')
        ->and($csv)->toContain('Title')
        ->and($csv)->toContain('PR-001')
        ->and($csv)->toContain('Test Procurement');
});

test('report generation service handles empty data for CSV export', function () {
    $reportData = [
        'success' => true,
        'data' => [],
    ];

    $service = new ReportGenerationService($this->mockSemanticSearchService);
    $csv = $service->exportReport($reportData, 'csv');

    expect($csv)->toBe('');
});

test('report generation service handles search failure', function () {
    $this->mockSemanticSearchService
        ->shouldReceive('search')
        ->once()
        ->andReturn([
            'success' => false,
            'error' => 'Test error',
        ]);

    $service = new ReportGenerationService($this->mockSemanticSearchService);
    $report = $service->generateReport([
        'filter_type' => 'month',
        'month' => 1,
        'year' => 2025,
    ]);

    expect($report['success'])->toBeFalse()
        ->and($report)->toHaveKey('error');
});

test('report generation service applies custom date range filter', function () {
    $mockResults = [
        'success' => true,
        'query' => '',
        'filters' => [
            'date_from' => '2025-01-01',
            'date_to' => '2025-06-30',
        ],
        'total' => 2,
        'results' => [],
    ];

    $this->mockSemanticSearchService
        ->shouldReceive('search')
        ->once()
        ->andReturn($mockResults);

    $this->mockSemanticSearchService
        ->shouldReceive('calculateStatistics')
        ->once()
        ->andReturn([
            'total_count' => 0,
            'by_status' => [],
            'by_stage' => [],
            'by_mode' => [],
            'by_category' => [],
            'total_abc_amount' => 0,
        ]);

    $service = new ReportGenerationService($this->mockSemanticSearchService);
    $report = $service->generateReport([
        'filter_type' => 'date_range',
        'date_from' => '2025-01-01',
        'date_to' => '2025-06-30',
    ]);

    expect($report['success'])->toBeTrue()
        ->and($report['filters'])->toHaveKey('date_from')
        ->and($report['filters'])->toHaveKey('date_to');
});
