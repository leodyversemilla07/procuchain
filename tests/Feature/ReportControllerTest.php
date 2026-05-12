<?php

use App\Models\User;
use App\Services\ReportGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create()->assignRole('admin');
    $this->actingAs($this->user);
});

test('reports index page can be rendered', function () {
    $response = $this->get('/admin/reports');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('reports/index'));
});

test('report can be generated with month filter', function () {
    $mockReportService = Mockery::mock(ReportGenerationService::class);
    $mockReportService->shouldReceive('generateReport')
        ->once()
        ->andReturn([
            'success' => true,
            'report_generated_at' => now()->toIso8601String(),
            'parameters' => [
                'filter_type' => 'month',
                'month' => 1,
                'year' => 2025,
            ],
            'summary' => [
                'total_count' => 5,
                'by_status' => ['active' => 5],
                'by_stage' => ['bidding' => 5],
                'by_mode' => [],
                'by_category' => [],
                'total_abc_amount' => 500000,
            ],
            'time_series' => [],
            'data' => [],
        ]);

    $this->app->instance(ReportGenerationService::class, $mockReportService);

    $response = $this->postJson('/admin/reports/generate', [
        'filter_type' => 'month',
        'month' => 1,
        'year' => 2025,
    ]);

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
    ]);
});

test('report can be generated with quarter filter', function () {
    $mockReportService = Mockery::mock(ReportGenerationService::class);
    $mockReportService->shouldReceive('generateReport')
        ->once()
        ->andReturn([
            'success' => true,
            'report_generated_at' => now()->toIso8601String(),
            'parameters' => [
                'filter_type' => 'quarter',
                'quarter' => 1,
                'year' => 2025,
            ],
            'summary' => [
                'total_count' => 10,
                'by_status' => ['active' => 10],
                'by_stage' => ['bidding' => 10],
                'by_mode' => [],
                'by_category' => [],
                'total_abc_amount' => 1000000,
            ],
            'time_series' => [],
            'data' => [],
        ]);

    $this->app->instance(ReportGenerationService::class, $mockReportService);

    $response = $this->postJson('/admin/reports/generate', [
        'filter_type' => 'quarter',
        'quarter' => 1,
        'year' => 2025,
    ]);

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
    ]);
});

test('report can be generated with year filter', function () {
    $mockReportService = Mockery::mock(ReportGenerationService::class);
    $mockReportService->shouldReceive('generateReport')
        ->once()
        ->andReturn([
            'success' => true,
            'report_generated_at' => now()->toIso8601String(),
            'parameters' => [
                'filter_type' => 'year',
                'year' => 2025,
            ],
            'summary' => [
                'total_count' => 50,
                'by_status' => ['active' => 50],
                'by_stage' => ['bidding' => 50],
                'by_mode' => [],
                'by_category' => [],
                'total_abc_amount' => 5000000,
            ],
            'time_series' => [],
            'data' => [],
        ]);

    $this->app->instance(ReportGenerationService::class, $mockReportService);

    $response = $this->postJson('/admin/reports/generate', [
        'filter_type' => 'year',
        'year' => 2025,
    ]);

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
    ]);
});

// TODO: Implement semantic search JSON API endpoint (POST /admin/search)
// The current search route is GET /admin/blockchain-explorer/search (Inertia page)
// These tests should be re-enabled once the API endpoint is built
test('semantic search can be performed', function () {
    $this->markTestSkipped('Semantic search API endpoint not yet implemented');
});

test('report generation requires authentication', function () {
    auth()->logout();

    $response = $this->postJson('/admin/reports/generate', [
        'filter_type' => 'month',
        'month' => 1,
        'year' => 2025,
    ]);

    $response->assertUnauthorized();
});

test('report generation requires admin role', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->postJson('/admin/reports/generate', [
        'filter_type' => 'month',
        'month' => 1,
        'year' => 2025,
    ]);

    $response->assertForbidden();
});

test('report generation validates month parameter', function () {
    $response = $this->postJson('/admin/reports/generate', [
        'filter_type' => 'month',
        'month' => 13,
        'year' => 2025,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['month']);
});

test('report generation validates quarter parameter', function () {
    $response = $this->postJson('/admin/reports/generate', [
        'filter_type' => 'quarter',
        'quarter' => 5,
        'year' => 2025,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['quarter']);
});

test('report generation validates date range', function () {
    $response = $this->postJson('/admin/reports/generate', [
        'filter_type' => 'date_range',
        'date_from' => '2025-06-01',
        'date_to' => '2025-01-01',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['date_to']);
});

test('report can be exported as CSV', function () {
    $mockReportService = Mockery::mock(ReportGenerationService::class);
    $mockReportService->shouldReceive('generateReport')
        ->once()
        ->andReturn([
            'success' => true,
            'data' => [
                ['id' => 'PR-001', 'title' => 'Test'],
            ],
        ]);

    $mockReportService->shouldReceive('exportReport')
        ->once()
        ->with(Mockery::any(), 'csv')
        ->andReturn("ID,Title\nPR-001,Test\n");

    $this->app->instance(ReportGenerationService::class, $mockReportService);

    $response = $this->postJson('/admin/reports/export', [
        'filter_type' => 'month',
        'month' => 1,
        'year' => 2025,
        'format' => 'csv',
    ]);

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

test('report can be exported as PDF', function () {
    $mockReportService = Mockery::mock(ReportGenerationService::class);
    $mockReportService->shouldReceive('generateReport')
        ->once()
        ->andReturn([
            'success' => true,
            'summary' => [
                'total_count' => 2,
                'total_abc_amount' => 2500000,
                'by_status' => ['active' => 2],
                'by_stage' => ['bid_opening' => 1, 'post_qualification' => 1],
                'by_mode' => ['public_bidding' => 2],
            ],
            'data' => [
                [
                    'id' => 'PR-2026-001',
                    'title' => 'Test Procurement One',
                    'current_status' => 'active',
                    'stage' => 'bid_opening',
                    'mode' => 'public_bidding',
                    'abc_amount' => 1000000,
                ],
                [
                    'id' => 'PR-2026-002',
                    'title' => 'Test Procurement Two',
                    'current_status' => 'active',
                    'stage' => 'post_qualification',
                    'mode' => 'public_bidding',
                    'abc_amount' => 1500000,
                ],
            ],
        ]);

    $this->app->instance(ReportGenerationService::class, $mockReportService);

    $response = $this->postJson('/admin/reports/export', [
        'filter_type' => 'month',
        'month' => 1,
        'year' => 2026,
        'format' => 'pdf',
    ]);

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('procurement-report-');
});

// TODO: Re-enable when semantic search API endpoint is implemented
test('semantic search requires query parameter', function () {
    $this->markTestSkipped('Semantic search API endpoint not yet implemented');
});
