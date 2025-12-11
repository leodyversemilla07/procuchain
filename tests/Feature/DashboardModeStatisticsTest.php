<?php

/**
 * Dashboard Mode Statistics Tests
 *
 * Tests for mode-based statistics in the dashboard.
 * Ensures NGPA compliance by validating procurement mode tracking
 * and competitive vs alternative mode breakdown.
 *
 * NGPA Reference:
 * - IRR Sections 27-30: Competitive modes
 * - IRR Sections 31-37: Alternative modes
 *
 * Municipality of Gloria Context:
 * - 4th Class Municipality, Oriental Mindoro
 * - SVP threshold: ₱400,000
 * - Direct Acquisition threshold: ₱200,000
 */

use App\Enums\ProcurementModeEnums;
use App\Services\DashboardService;

beforeEach(function () {
    $this->dashboardService = app(DashboardService::class);
});

describe('Mode Distribution', function () {
    it('returns empty distribution when no procurements exist', function () {
        $emptyCollection = collect([]);

        $distribution = $this->dashboardService->getModeDistribution($emptyCollection);

        expect($distribution)->toBeArray()->toBeEmpty();
    });

    it('correctly counts procurements by mode', function () {
        $procurements = collect([
            ['id' => 'PR-001', 'procurement_mode' => 'competitive_bidding', 'procurement_mode_label' => 'Public Bidding'],
            ['id' => 'PR-002', 'procurement_mode' => 'competitive_bidding', 'procurement_mode_label' => 'Public Bidding'],
            ['id' => 'PR-003', 'procurement_mode' => 'small_value_procurement', 'procurement_mode_label' => 'Small Value Procurement'],
            ['id' => 'PR-004', 'procurement_mode' => 'direct_contracting', 'procurement_mode_label' => 'Direct Contracting'],
        ]);

        $distribution = $this->dashboardService->getModeDistribution($procurements);

        expect($distribution)->toBeArray()->toHaveCount(3);

        // Should be sorted by count descending
        expect($distribution[0]['mode'])->toBe('competitive_bidding');
        expect($distribution[0]['count'])->toBe(2);
        expect($distribution[0]['percentage'])->toBe(50.0);

        // SVP should be second or third
        $svp = collect($distribution)->firstWhere('mode', 'small_value_procurement');
        expect($svp['count'])->toBe(1);
        expect($svp['percentage'])->toBe(25.0);
    });

    it('handles unknown modes gracefully', function () {
        $procurements = collect([
            ['id' => 'PR-001', 'procurement_mode' => 'unknown', 'procurement_mode_label' => 'Unknown'],
            ['id' => 'PR-002', 'procurement_mode' => 'competitive_bidding', 'procurement_mode_label' => 'Public Bidding'],
        ]);

        $distribution = $this->dashboardService->getModeDistribution($procurements);

        expect($distribution)->toBeArray()->toHaveCount(2);

        $unknown = collect($distribution)->firstWhere('mode', 'unknown');
        expect($unknown)->not->toBeNull();
        expect($unknown['label'])->toBe('Unknown');
    });
});

describe('Mode Type Statistics (Competitive vs Alternative)', function () {
    it('correctly identifies competitive modes per NGPA IRR Sections 27-30', function () {
        $procurements = collect([
            ['id' => 'PR-001', 'procurement_mode' => 'competitive_bidding', 'is_alternative_mode' => false],
            ['id' => 'PR-002', 'procurement_mode' => 'limited_source_bidding', 'is_alternative_mode' => false],
            ['id' => 'PR-003', 'procurement_mode' => 'competitive_dialogue', 'is_alternative_mode' => false],
        ]);

        $typeStats = $this->dashboardService->getModeTypeStatistics($procurements);

        expect($typeStats['competitive']['count'])->toBe(3);
        expect($typeStats['competitive']['percentage'])->toBe(100.0);
        expect($typeStats['alternative']['count'])->toBe(0);
        expect($typeStats['total'])->toBe(3);
    });

    it('correctly identifies alternative modes per NGPA IRR Sections 31-37', function () {
        $procurements = collect([
            ['id' => 'PR-001', 'procurement_mode' => 'small_value_procurement', 'is_alternative_mode' => true],
            ['id' => 'PR-002', 'procurement_mode' => 'direct_contracting', 'is_alternative_mode' => true],
            ['id' => 'PR-003', 'procurement_mode' => 'negotiated_procurement', 'is_alternative_mode' => true],
        ]);

        $typeStats = $this->dashboardService->getModeTypeStatistics($procurements);

        expect($typeStats['alternative']['count'])->toBe(3);
        expect($typeStats['alternative']['percentage'])->toBe(100.0);
        expect($typeStats['competitive']['count'])->toBe(0);
        expect($typeStats['total'])->toBe(3);
    });

    it('correctly calculates mixed mode distribution', function () {
        $procurements = collect([
            ['id' => 'PR-001', 'procurement_mode' => 'competitive_bidding', 'is_alternative_mode' => false],
            ['id' => 'PR-002', 'procurement_mode' => 'limited_source_bidding', 'is_alternative_mode' => false],
            ['id' => 'PR-003', 'procurement_mode' => 'small_value_procurement', 'is_alternative_mode' => true],
            ['id' => 'PR-004', 'procurement_mode' => 'direct_contracting', 'is_alternative_mode' => true],
            ['id' => 'PR-005', 'procurement_mode' => 'negotiated_procurement', 'is_alternative_mode' => true],
        ]);

        $typeStats = $this->dashboardService->getModeTypeStatistics($procurements);

        expect($typeStats['competitive']['count'])->toBe(2);
        expect($typeStats['alternative']['count'])->toBe(3);
        expect($typeStats['competitive']['percentage'])->toBe(40.0);
        expect($typeStats['alternative']['percentage'])->toBe(60.0);
        expect($typeStats['total'])->toBe(5);
    });

    it('includes NGPA references for each mode type', function () {
        $procurements = collect([
            ['id' => 'PR-001', 'procurement_mode' => 'competitive_bidding', 'is_alternative_mode' => false],
        ]);

        $typeStats = $this->dashboardService->getModeTypeStatistics($procurements);

        expect($typeStats['competitive']['ngpa_reference'])->toBe('IRR Sections 27-30');
        expect($typeStats['alternative']['ngpa_reference'])->toBe('IRR Sections 31-37');
    });

    it('tracks unknown/unclassified modes separately', function () {
        $procurements = collect([
            ['id' => 'PR-001', 'procurement_mode' => 'competitive_bidding', 'is_alternative_mode' => false],
            ['id' => 'PR-002', 'procurement_mode' => null, 'is_alternative_mode' => null],
        ]);

        $typeStats = $this->dashboardService->getModeTypeStatistics($procurements);

        expect($typeStats['competitive']['count'])->toBe(1);
        expect($typeStats['unknown']['count'])->toBe(1);
        expect($typeStats['total'])->toBe(2);
    });
});

describe('Group Procurements By Mode', function () {
    it('groups procurements correctly by mode', function () {
        $procurements = collect([
            ['id' => 'PR-001', 'title' => 'Office Supplies', 'procurement_mode' => 'small_value_procurement', 'procurement_mode_label' => 'Small Value Procurement'],
            ['id' => 'PR-002', 'title' => 'IT Equipment', 'procurement_mode' => 'small_value_procurement', 'procurement_mode_label' => 'Small Value Procurement'],
            ['id' => 'PR-003', 'title' => 'Construction', 'procurement_mode' => 'competitive_bidding', 'procurement_mode_label' => 'Public Bidding'],
        ]);

        $grouped = $this->dashboardService->groupProcurementsByMode($procurements);

        expect($grouped)->toBeArray()->toHaveCount(2);

        // Should be sorted by count descending
        $svpGroup = collect($grouped)->firstWhere('mode', 'small_value_procurement');
        expect($svpGroup['count'])->toBe(2);
        expect($svpGroup['procurements'])->toHaveCount(2);
    });

    it('returns empty array for empty collection', function () {
        $grouped = $this->dashboardService->groupProcurementsByMode(collect());

        expect($grouped)->toBeArray()->toBeEmpty();
    });
});

describe('Comprehensive Mode Statistics', function () {
    it('returns all mode statistics in getModeStatistics', function () {
        $procurements = collect([
            ['id' => 'PR-001', 'procurement_mode' => 'competitive_bidding', 'procurement_mode_label' => 'Public Bidding', 'is_alternative_mode' => false],
            ['id' => 'PR-002', 'procurement_mode' => 'small_value_procurement', 'procurement_mode_label' => 'Small Value Procurement', 'is_alternative_mode' => true],
        ]);

        $stats = $this->dashboardService->getModeStatistics($procurements);

        expect($stats)->toHaveKeys(['distribution', 'type_breakdown', 'by_mode']);
        expect($stats['distribution'])->toBeArray();
        expect($stats['type_breakdown'])->toHaveKeys(['competitive', 'alternative', 'unknown', 'total']);
        expect($stats['by_mode'])->toBeArray();
    });
});

describe('Recent Procurements with Mode Data', function () {
    it('includes mode information in recent procurements', function () {
        // Create a mock procurement with mode data
        $procurements = collect([
            [
                'id' => 'PR-001',
                'title' => 'Test Procurement',
                'stage' => 'procurement_initiation',
                'status' => 'pending',
                'timestamp' => now()->toIso8601String(),
                'procurement_mode' => 'small_value_procurement',
                'procurement_mode_label' => 'Small Value Procurement',
                'is_alternative_mode' => true,
            ],
        ]);

        $recentProcurements = $this->dashboardService->getRecentProcurements($procurements);

        expect($recentProcurements)->toBeArray()->toHaveCount(1);
        expect($recentProcurements[0])->toHaveKeys([
            'id', 'title', 'stage', 'status',
            'procurement_mode', 'procurement_mode_label', 'is_alternative_mode',
        ]);
        expect($recentProcurements[0]['procurement_mode'])->toBe('small_value_procurement');
        expect($recentProcurements[0]['is_alternative_mode'])->toBeTrue();
    });
});

describe('Procurement Distribution with Mode Data', function () {
    it('includes mode information in distribution data', function () {
        $procurements = collect([
            [
                'id' => 'PR-001',
                'title' => 'Test Procurement',
                'stage' => 'procurement_initiation',
                'status' => 'pending',
                'timestamp' => now()->toIso8601String(),
                'procurement_mode' => 'direct_contracting',
                'procurement_mode_label' => 'Direct Contracting',
                'is_alternative_mode' => true,
            ],
        ]);

        $distribution = $this->dashboardService->getProcurementDistributionData($procurements);

        expect($distribution)->toBeArray()->toHaveCount(1);
        expect($distribution[0])->toHaveKeys([
            'id', 'title', 'stage', 'status',
            'procurement_mode', 'procurement_mode_label', 'is_alternative_mode',
        ]);
    });
});

describe('NGPA Mode Enum Integration', function () {
    it('validates isAlternativeMode returns correct values for all modes', function () {
        // Alternative modes per IRR Sections 31-37
        $alternativeModes = [
            ProcurementModeEnums::DIRECT_CONTRACTING,
            ProcurementModeEnums::DIRECT_ACQUISITION,
            ProcurementModeEnums::REPEAT_ORDER,
            ProcurementModeEnums::SMALL_VALUE_PROCUREMENT,
            ProcurementModeEnums::NEGOTIATED_PROCUREMENT,
            ProcurementModeEnums::DIRECT_SALES,
            ProcurementModeEnums::DIRECT_PROCUREMENT_FOR_STI,
        ];

        foreach ($alternativeModes as $mode) {
            expect($mode->isAlternativeMode())->toBeTrue(
                "Expected {$mode->value} to be an alternative mode"
            );
        }

        // Competitive modes per IRR Sections 27-30
        $competitiveModes = [
            ProcurementModeEnums::COMPETITIVE_BIDDING,
            ProcurementModeEnums::LIMITED_SOURCE_BIDDING,
            ProcurementModeEnums::COMPETITIVE_DIALOGUE,
            ProcurementModeEnums::UNSOLICITED_OFFER_WITH_BID_MATCHING,
        ];

        foreach ($competitiveModes as $mode) {
            expect($mode->isAlternativeMode())->toBeFalse(
                "Expected {$mode->value} to be a competitive mode"
            );
            expect($mode->isCompetitiveMode())->toBeTrue(
                "Expected {$mode->value} to return true for isCompetitiveMode()"
            );
        }
    });

    it('validates Municipality of Gloria threshold for SVP', function () {
        $svp = ProcurementModeEnums::SMALL_VALUE_PROCUREMENT;

        // Municipality of Gloria is 4th class, threshold should be ₱400,000
        expect($svp->thresholdAmount())->toBe(400000.0);
        expect($svp->getIrrSection())->toBe('Section 34');
    });

    it('validates Municipality of Gloria threshold for Direct Acquisition', function () {
        $da = ProcurementModeEnums::DIRECT_ACQUISITION;

        // Municipality of Gloria is 4th class, threshold should be ₱200,000
        expect($da->thresholdAmount())->toBe(200000.0);
        expect($da->getIrrSection())->toBe('Section 32');
    });
});
