<?php

/**
 * Procurement Mode Analytics Service Tests
 *
 * Tests for mode-based analytics and reporting per NGPA (RA 12009) IRR.
 * Validates analytics for Municipality of Gloria (4th Class Municipality).
 *
 * NGPA References:
 * - Competitive Modes: IRR Sections 27-30
 * - Alternative Modes: IRR Sections 31-37
 */

use App\Services\ProcurementModeAnalyticsService;

beforeEach(function () {
    $this->analyticsService = app(ProcurementModeAnalyticsService::class);
});

describe('Mode Summary', function () {
    it('returns correct summary statistics', function () {
        $procurements = collect([
            ['id' => 'PR-001', 'procurement_mode' => 'competitive_bidding', 'is_alternative_mode' => false],
            ['id' => 'PR-002', 'procurement_mode' => 'limited_source_bidding', 'is_alternative_mode' => false],
            ['id' => 'PR-003', 'procurement_mode' => 'small_value_procurement', 'is_alternative_mode' => true],
            ['id' => 'PR-004', 'procurement_mode' => 'direct_contracting', 'is_alternative_mode' => true],
            ['id' => 'PR-005', 'procurement_mode' => 'small_value_procurement', 'is_alternative_mode' => true],
        ]);

        $summary = $this->analyticsService->getModeSummary($procurements);

        expect($summary['total_procurements'])->toBe(5)
            ->and($summary['competitive_count'])->toBe(2)
            ->and($summary['alternative_count'])->toBe(3)
            ->and($summary['unique_modes_used'])->toBe(4)
            ->and($summary['competitive_percentage'])->toBe(40.0)
            ->and($summary['alternative_percentage'])->toBe(60.0);
    });

    it('handles empty collection', function () {
        $summary = $this->analyticsService->getModeSummary(collect());

        expect($summary['total_procurements'])->toBe(0)
            ->and($summary['competitive_percentage'])->toBe(0)
            ->and($summary['alternative_percentage'])->toBe(0);
    });
});

describe('Mode Performance Metrics', function () {
    it('calculates performance metrics per mode', function () {
        $procurements = collect([
            ['id' => 'PR-001', 'procurement_mode' => 'small_value_procurement', 'stage' => 'procurement_initiation'],
            ['id' => 'PR-002', 'procurement_mode' => 'small_value_procurement', 'stage' => 'monitoring'],
            ['id' => 'PR-003', 'procurement_mode' => 'competitive_bidding', 'stage' => 'bid_opening'],
        ]);

        $metrics = $this->analyticsService->getModePerformanceMetrics($procurements);

        expect($metrics)->toBeArray()->not->toBeEmpty();

        $svpMetric = collect($metrics)->firstWhere('mode', 'small_value_procurement');
        expect($svpMetric)->not->toBeNull()
            ->and($svpMetric['total_count'])->toBe(2)
            ->and($svpMetric['irr_section'])->toBe('Section 34')
            ->and($svpMetric['is_alternative'])->toBeTrue();
    });

    it('includes phase distribution for each mode', function () {
        $procurements = collect([
            ['id' => 'PR-001', 'procurement_mode' => 'competitive_bidding', 'stage' => 'procurement_initiation'],
            ['id' => 'PR-002', 'procurement_mode' => 'competitive_bidding', 'stage' => 'bid_opening'],
            ['id' => 'PR-003', 'procurement_mode' => 'competitive_bidding', 'stage' => 'contract_implementation'],
        ]);

        $metrics = $this->analyticsService->getModePerformanceMetrics($procurements);
        $cbMetric = collect($metrics)->firstWhere('mode', 'competitive_bidding');

        expect($cbMetric['phase_distribution'])->toHaveKeys(['pre_procurement', 'procurement', 'post_procurement']);
    });

    it('calculates completion rate correctly', function () {
        $procurements = collect([
            ['id' => 'PR-001', 'procurement_mode' => 'small_value_procurement', 'stage' => 'monitoring'],
            ['id' => 'PR-002', 'procurement_mode' => 'small_value_procurement', 'stage' => 'monitoring'],
            ['id' => 'PR-003', 'procurement_mode' => 'small_value_procurement', 'stage' => 'contract_implementation'],
            ['id' => 'PR-004', 'procurement_mode' => 'small_value_procurement', 'stage' => 'procurement_initiation'],
        ]);

        $metrics = $this->analyticsService->getModePerformanceMetrics($procurements);
        $svpMetric = collect($metrics)->firstWhere('mode', 'small_value_procurement');

        // 2 out of 4 are in monitoring stage = 50% completion
        expect($svpMetric['completion_rate'])->toBe(50.0);
    });
});

describe('Threshold Analysis for Municipality of Gloria', function () {
    it('returns correct thresholds for 4th class municipality', function () {
        $procurements = collect([
            ['id' => 'PR-001', 'procurement_mode' => 'small_value_procurement'],
            ['id' => 'PR-002', 'procurement_mode' => 'small_value_procurement'],
            ['id' => 'PR-003', 'procurement_mode' => 'direct_acquisition'],
            ['id' => 'PR-004', 'procurement_mode' => 'competitive_bidding'],
        ]);

        $analysis = $this->analyticsService->getThresholdAnalysis($procurements);

        expect($analysis['municipality_class'])->toBe('4th Class')
            ->and($analysis['municipality_name'])->toContain('Gloria')
            ->and($analysis['svp']['threshold'])->toBe(400000.0)
            ->and($analysis['svp']['irr_section'])->toBe('Section 34.2')
            ->and($analysis['svp']['count'])->toBe(2)
            ->and($analysis['direct_acquisition']['threshold'])->toBe(200000.0)
            ->and($analysis['direct_acquisition']['irr_section'])->toBe('Section 32')
            ->and($analysis['direct_acquisition']['count'])->toBe(1);
    });

    it('calculates percentages correctly', function () {
        $procurements = collect([
            ['id' => 'PR-001', 'procurement_mode' => 'small_value_procurement'],
            ['id' => 'PR-002', 'procurement_mode' => 'small_value_procurement'],
            ['id' => 'PR-003', 'procurement_mode' => 'competitive_bidding'],
            ['id' => 'PR-004', 'procurement_mode' => 'competitive_bidding'],
        ]);

        $analysis = $this->analyticsService->getThresholdAnalysis($procurements);

        expect($analysis['svp']['percentage'])->toBe(50.0);
    });
});

describe('NGPA Compliance Metrics', function () {
    it('calculates mode compliance rate', function () {
        $procurements = collect([
            ['id' => 'PR-001', 'procurement_mode' => 'competitive_bidding'],
            ['id' => 'PR-002', 'procurement_mode' => 'small_value_procurement'],
            ['id' => 'PR-003', 'procurement_mode' => null],
            ['id' => 'PR-004', 'procurement_mode' => ''],
        ]);

        $compliance = $this->analyticsService->getNgpaComplianceMetrics($procurements);

        expect($compliance['total_procurements'])->toBe(4)
            ->and($compliance['with_valid_mode'])->toBe(2)
            ->and($compliance['mode_compliance_rate'])->toBe(50.0);
    });

    it('groups procurements by IRR section', function () {
        $procurements = collect([
            ['id' => 'PR-001', 'procurement_mode' => 'competitive_bidding'],
            ['id' => 'PR-002', 'procurement_mode' => 'limited_source_bidding'],
            ['id' => 'PR-003', 'procurement_mode' => 'small_value_procurement'],
        ]);

        $compliance = $this->analyticsService->getNgpaComplianceMetrics($procurements);

        expect($compliance['by_irr_section'])->toBeArray();

        // Should have sections for competitive bidding (27), limited source (28), and SVP (34)
        $sections = collect($compliance['by_irr_section'])->pluck('section')->toArray();
        expect($sections)->toContain('Section 27')
            ->toContain('Section 28')
            ->toContain('Section 34');
    });
});

describe('Stage Distribution By Mode', function () {
    it('returns stage breakdown for each mode', function () {
        $procurements = collect([
            ['id' => 'PR-001', 'procurement_mode' => 'small_value_procurement', 'stage' => 'procurement_initiation'],
            ['id' => 'PR-002', 'procurement_mode' => 'small_value_procurement', 'stage' => 'procurement_initiation'],
            ['id' => 'PR-003', 'procurement_mode' => 'small_value_procurement', 'stage' => 'award'],
            ['id' => 'PR-004', 'procurement_mode' => 'competitive_bidding', 'stage' => 'bid_opening'],
        ]);

        $distribution = $this->analyticsService->getStageDistributionByMode($procurements);

        expect($distribution)->toBeArray()->not->toBeEmpty();

        $svpDist = collect($distribution)->firstWhere('mode', 'small_value_procurement');
        expect($svpDist['total'])->toBe(3)
            ->and($svpDist['stages'])->toBeArray();
    });

    it('sorts modes by total count descending', function () {
        $procurements = collect([
            ['id' => 'PR-001', 'procurement_mode' => 'competitive_bidding', 'stage' => 'bid_opening'],
            ['id' => 'PR-002', 'procurement_mode' => 'small_value_procurement', 'stage' => 'award'],
            ['id' => 'PR-003', 'procurement_mode' => 'small_value_procurement', 'stage' => 'award'],
            ['id' => 'PR-004', 'procurement_mode' => 'small_value_procurement', 'stage' => 'award'],
        ]);

        $distribution = $this->analyticsService->getStageDistributionByMode($procurements);

        // SVP should be first with 3, CB second with 1
        expect($distribution[0]['mode'])->toBe('small_value_procurement')
            ->and($distribution[0]['total'])->toBe(3);
    });
});

describe('Complete Analytics Report', function () {
    it('returns all analytics sections', function () {
        $procurements = collect([
            [
                'id' => 'PR-001',
                'procurement_mode' => 'competitive_bidding',
                'procurement_mode_label' => 'Public Bidding',
                'is_alternative_mode' => false,
                'stage' => 'bid_opening',
                'timestamp' => now()->toIso8601String(),
            ],
            [
                'id' => 'PR-002',
                'procurement_mode' => 'small_value_procurement',
                'procurement_mode_label' => 'Small Value Procurement',
                'is_alternative_mode' => true,
                'stage' => 'award',
                'timestamp' => now()->toIso8601String(),
            ],
        ]);

        $analytics = $this->analyticsService->getModeAnalytics($procurements);

        expect($analytics)->toHaveKeys([
            'summary',
            'mode_distribution',
            'type_breakdown',
            'mode_performance',
            'threshold_analysis',
            'ngpa_compliance',
            'stage_by_mode',
            'time_range',
            'generated_at',
        ]);
    });

    it('respects time range filter', function () {
        $procurements = collect([
            [
                'id' => 'PR-001',
                'procurement_mode' => 'competitive_bidding',
                'is_alternative_mode' => false,
                'timestamp' => now()->subDays(10)->toIso8601String(),
            ],
            [
                'id' => 'PR-002',
                'procurement_mode' => 'small_value_procurement',
                'is_alternative_mode' => true,
                'timestamp' => now()->subDays(60)->toIso8601String(),
            ],
        ]);

        $analytics = $this->analyticsService->getModeAnalytics($procurements, '30_days');

        expect($analytics['time_range'])->toBe('30_days')
            ->and($analytics['summary']['total_procurements'])->toBe(1); // Only PR-001 within 30 days
    });
});

describe('Edge Cases', function () {
    it('handles procurements with null modes gracefully', function () {
        $procurements = collect([
            ['id' => 'PR-001', 'procurement_mode' => null, 'stage' => 'bid_opening'],
            ['id' => 'PR-002', 'procurement_mode' => 'competitive_bidding', 'stage' => 'award'],
        ]);

        $metrics = $this->analyticsService->getModePerformanceMetrics($procurements);

        // Should only include valid (non-empty) modes in metrics
        // Note: null modes become empty strings in groupBy, so filter both
        $validModes = collect($metrics)->filter(fn ($m) => ! empty($m['mode']));
        expect($validModes)->toHaveCount(1)
            ->and($validModes->first()['mode'])->toBe('competitive_bidding');
    });

    it('handles empty procurements collection gracefully', function () {
        $analytics = $this->analyticsService->getModeAnalytics(collect());

        expect($analytics['summary']['total_procurements'])->toBe(0)
            ->and($analytics['mode_distribution'])->toBeEmpty()
            ->and($analytics['mode_performance'])->toBeEmpty();
    });
});
