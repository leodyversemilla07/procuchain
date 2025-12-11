<?php

namespace App\Services;

use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Repositories\ProcurementRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Procurement Mode Analytics Service
 *
 * Provides comprehensive analytics for procurement modes aligned with NGPA
 * (RA 12009) IRR requirements. Supports Municipality of Gloria's
 * procurement reporting needs.
 *
 * NGPA References:
 * - Competitive Modes: IRR Sections 27-30
 * - Alternative Modes: IRR Sections 31-37
 * - Municipality of Gloria (4th Class): ₱200,000 SVP/DA threshold
 */
class ProcurementModeAnalyticsService
{
    public function __construct(
        private ProcurementRepository $procurementRepository,
        private DashboardService $dashboardService
    ) {}

    /**
     * Generate comprehensive mode analytics report
     *
     * @param  Collection  $procurementsByKey  Procurements collection with mode data
     * @param  string  $timeRange  Time range filter (all, 30_days, 90_days, year)
     * @return array Complete analytics data
     */
    public function getModeAnalytics(Collection $procurementsByKey, string $timeRange = 'all'): array
    {
        try {
            $filteredProcurements = $this->filterByTimeRange($procurementsByKey, $timeRange);

            return [
                'summary' => $this->getModeSummary($filteredProcurements),
                'mode_distribution' => $this->dashboardService->getModeDistribution($filteredProcurements),
                'type_breakdown' => $this->dashboardService->getModeTypeStatistics($filteredProcurements),
                'mode_performance' => $this->getModePerformanceMetrics($filteredProcurements),
                'threshold_analysis' => $this->getThresholdAnalysis($filteredProcurements),
                'ngpa_compliance' => $this->getNgpaComplianceMetrics($filteredProcurements),
                'stage_by_mode' => $this->getStageDistributionByMode($filteredProcurements),
                'time_range' => $timeRange,
                'generated_at' => now()->toIso8601String(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate mode analytics', [
                'error' => $e->getMessage(),
                'time_range' => $timeRange,
            ]);

            return $this->getEmptyAnalytics();
        }
    }

    /**
     * Get mode summary statistics
     *
     * @param  Collection  $procurements  Filtered procurements collection
     * @return array Summary data
     */
    public function getModeSummary(Collection $procurements): array
    {
        $total = $procurements->count();
        $competitiveCount = $procurements->filter(fn ($p) => ($p['is_alternative_mode'] ?? null) === false)->count();
        $alternativeCount = $procurements->filter(fn ($p) => ($p['is_alternative_mode'] ?? null) === true)->count();

        $uniqueModes = $procurements
            ->pluck('procurement_mode')
            ->filter()
            ->unique()
            ->count();

        return [
            'total_procurements' => $total,
            'competitive_count' => $competitiveCount,
            'alternative_count' => $alternativeCount,
            'unique_modes_used' => $uniqueModes,
            'competitive_percentage' => $total > 0 ? round(($competitiveCount / $total) * 100, 1) : 0,
            'alternative_percentage' => $total > 0 ? round(($alternativeCount / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Get performance metrics per mode
     *
     * Tracks completion rates and average time per stage for each mode
     *
     * @param  Collection  $procurements  Filtered procurements collection
     * @return array Performance metrics by mode
     */
    public function getModePerformanceMetrics(Collection $procurements): array
    {
        $metrics = [];
        $grouped = $procurements->groupBy('procurement_mode');

        foreach ($grouped as $mode => $modeProcurements) {
            if ($mode === null) {
                continue;
            }

            $modeEnum = ProcurementModeEnums::tryFrom($mode);
            $total = $modeProcurements->count();

            // Count by stage phase
            $preProcurement = 0;
            $procurement = 0;
            $postProcurement = 0;
            $completed = 0;

            foreach ($modeProcurements as $proc) {
                $stageEnum = StageEnums::tryFrom($proc['stage'] ?? '');
                if ($stageEnum) {
                    $phase = $stageEnum->getPhase();
                    match ($phase) {
                        'pre_procurement' => $preProcurement++,
                        'procurement' => $procurement++,
                        'post_procurement' => $postProcurement++,
                        default => null,
                    };

                    if ($stageEnum === StageEnums::MONITORING) {
                        $completed++;
                    }
                }
            }

            $metrics[] = [
                'mode' => $mode,
                'label' => $modeEnum?->getDisplayName() ?? $mode,
                'irr_section' => $modeEnum?->getIrrSection() ?? 'N/A',
                'is_alternative' => $modeEnum?->isAlternativeMode() ?? false,
                'total_count' => $total,
                'phase_distribution' => [
                    'pre_procurement' => $preProcurement,
                    'procurement' => $procurement,
                    'post_procurement' => $postProcurement,
                ],
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            ];
        }

        // Sort by total count descending
        usort($metrics, fn ($a, $b) => $b['total_count'] <=> $a['total_count']);

        return $metrics;
    }

    /**
     * Get threshold analysis for modes with ABC limits
     *
     * Per Municipality of Gloria (4th Class Municipality):
     * - SVP threshold: ₱200,000 (IRR Section 34.2)
     * - Direct Acquisition threshold: ₱200,000 (IRR Section 32)
     *
     * @param  Collection  $procurements  Filtered procurements collection
     * @return array Threshold analysis
     */
    public function getThresholdAnalysis(Collection $procurements): array
    {
        $svpThreshold = 400000.0; // 4th class municipality
        $daThreshold = 200000.0;

        $svpProcurements = $procurements->filter(
            fn ($p) => ($p['procurement_mode'] ?? '') === ProcurementModeEnums::SMALL_VALUE_PROCUREMENT->value
        );

        $daProcurements = $procurements->filter(
            fn ($p) => ($p['procurement_mode'] ?? '') === ProcurementModeEnums::DIRECT_ACQUISITION->value
        );

        return [
            'municipality_class' => '4th Class',
            'municipality_name' => 'Municipality of Gloria, Oriental Mindoro',
            'svp' => [
                'threshold' => $svpThreshold,
                'threshold_formatted' => '₱'.number_format($svpThreshold, 2),
                'irr_section' => 'Section 34.2',
                'count' => $svpProcurements->count(),
                'percentage' => $procurements->count() > 0
                    ? round(($svpProcurements->count() / $procurements->count()) * 100, 1)
                    : 0,
            ],
            'direct_acquisition' => [
                'threshold' => $daThreshold,
                'threshold_formatted' => '₱'.number_format($daThreshold, 2),
                'irr_section' => 'Section 32',
                'count' => $daProcurements->count(),
                'percentage' => $procurements->count() > 0
                    ? round(($daProcurements->count() / $procurements->count()) * 100, 1)
                    : 0,
            ],
        ];
    }

    /**
     * Get NGPA compliance metrics
     *
     * Validates that procurements follow NGPA IRR requirements
     *
     * @param  Collection  $procurements  Filtered procurements collection
     * @return array Compliance metrics
     */
    public function getNgpaComplianceMetrics(Collection $procurements): array
    {
        $total = $procurements->count();
        $withMode = $procurements->filter(fn ($p) => ! empty($p['procurement_mode']))->count();

        // Group by IRR section
        $bySection = [];
        foreach (ProcurementModeEnums::cases() as $mode) {
            $section = $mode->getIrrSection();
            $count = $procurements->filter(
                fn ($p) => ($p['procurement_mode'] ?? '') === $mode->value
            )->count();

            if ($count > 0) {
                if (! isset($bySection[$section])) {
                    $bySection[$section] = [
                        'section' => $section,
                        'modes' => [],
                        'total_count' => 0,
                    ];
                }
                $bySection[$section]['modes'][] = [
                    'mode' => $mode->value,
                    'label' => $mode->getDisplayName(),
                    'count' => $count,
                ];
                $bySection[$section]['total_count'] += $count;
            }
        }

        return [
            'total_procurements' => $total,
            'with_valid_mode' => $withMode,
            'mode_compliance_rate' => $total > 0 ? round(($withMode / $total) * 100, 1) : 0,
            'by_irr_section' => array_values($bySection),
        ];
    }

    /**
     * Get stage distribution per mode
     *
     * @param  Collection  $procurements  Filtered procurements collection
     * @return array Stage distribution by mode
     */
    public function getStageDistributionByMode(Collection $procurements): array
    {
        $distribution = [];
        $grouped = $procurements->groupBy('procurement_mode');

        foreach ($grouped as $mode => $modeProcurements) {
            if ($mode === null) {
                continue;
            }

            $modeEnum = ProcurementModeEnums::tryFrom($mode);
            $stageBreakdown = [];

            $stageGrouped = $modeProcurements->groupBy('stage');
            foreach ($stageGrouped as $stage => $stageProcurements) {
                $stageEnum = StageEnums::tryFrom($stage);
                $stageBreakdown[] = [
                    'stage' => $stage,
                    'label' => $stageEnum?->getDisplayName() ?? $stage,
                    'count' => $stageProcurements->count(),
                ];
            }

            // Sort stages by count descending
            usort($stageBreakdown, fn ($a, $b) => $b['count'] <=> $a['count']);

            $distribution[] = [
                'mode' => $mode,
                'label' => $modeEnum?->getDisplayName() ?? $mode,
                'total' => $modeProcurements->count(),
                'stages' => $stageBreakdown,
            ];
        }

        // Sort by total count descending
        usort($distribution, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $distribution;
    }

    /**
     * Filter procurements by time range
     *
     * @param  Collection  $procurements  All procurements
     * @param  string  $timeRange  Time range filter
     * @return Collection Filtered procurements
     */
    private function filterByTimeRange(Collection $procurements, string $timeRange): Collection
    {
        if ($timeRange === 'all') {
            return $procurements;
        }

        $cutoffDate = match ($timeRange) {
            '30_days' => now()->subDays(30),
            '90_days' => now()->subDays(90),
            'year' => now()->subYear(),
            default => null,
        };

        if (! $cutoffDate) {
            return $procurements;
        }

        return $procurements->filter(function ($procurement) use ($cutoffDate) {
            $timestamp = $procurement['timestamp'] ?? null;
            if (! $timestamp) {
                return true; // Include if no timestamp
            }

            try {
                $procTime = \Carbon\Carbon::parse($timestamp);

                return $procTime->gte($cutoffDate);
            } catch (\Exception $e) {
                return true; // Include if timestamp parsing fails
            }
        });
    }

    /**
     * Get empty analytics structure for error fallback
     *
     * @return array Empty analytics
     */
    private function getEmptyAnalytics(): array
    {
        return [
            'summary' => [
                'total_procurements' => 0,
                'competitive_count' => 0,
                'alternative_count' => 0,
                'unique_modes_used' => 0,
                'competitive_percentage' => 0,
                'alternative_percentage' => 0,
            ],
            'mode_distribution' => [],
            'type_breakdown' => [
                'competitive' => ['count' => 0, 'percentage' => 0],
                'alternative' => ['count' => 0, 'percentage' => 0],
                'unknown' => ['count' => 0, 'percentage' => 0],
                'total' => 0,
            ],
            'mode_performance' => [],
            'threshold_analysis' => [],
            'ngpa_compliance' => [],
            'stage_by_mode' => [],
            'time_range' => 'all',
            'generated_at' => now()->toIso8601String(),
            'error' => true,
        ];
    }
}
