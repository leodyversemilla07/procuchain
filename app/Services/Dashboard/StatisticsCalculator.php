<?php

namespace App\Services\Dashboard;

use App\Enums\StageEnums;
use Illuminate\Support\Collection;

/**
 * Statistics Calculator
 *
 * Handles pure data-transformation statistics for the dashboard:
 * ongoing/completed counts, phase grouping, and phase statistics.
 */
class StatisticsCalculator
{
    /**
     * Count ongoing projects (not completed)
     *
     * @param  Collection  $procurementsByKey  Procurements collection
     * @return int Count of ongoing projects
     */
    public function countOngoingProjects(Collection $procurementsByKey): int
    {
        $completedStages = config('dashboard.completed_bidding_stages');

        return $procurementsByKey->filter(function ($item) use ($completedStages) {
            // Exclude if explicitly in completed stages list
            if (in_array($item['stage'], $completedStages)) {
                return false;
            }

            // Also exclude if Enum matches COMPLETED (just in case config is missing it)
            if ($item['stage'] === StageEnums::COMPLETED->value) {
                return false;
            }

            return true;
        })->count();
    }

    /**
     * Count completed biddings (procurements in post-award stages)
     *
     * @param  Collection  $procurementsByKey  Procurements collection
     * @return int Count of completed biddings
     */
    public function countCompletedBiddings(Collection $procurementsByKey): int
    {
        return $procurementsByKey->filter(function ($item) {
            return in_array($item['stage'], config('dashboard.completed_bidding_stages'));
        })->count();
    }

    /**
     * Get empty stats array for error fallback
     *
     * @return array Empty stats structure
     */
    public function getEmptyStats(): array
    {
        return [
            'ongoingProjects' => 0,
            'completedBiddings' => 0,
            'totalDocuments' => 0,
        ];
    }

    /**
     * Calculate dashboard statistics
     *
     * @param  Collection  $procurementsByKey  Procurements collection
     * @param  int  $totalDocuments  Total document count
     * @return array Dashboard statistics
     */
    public function calculateStats(Collection $procurementsByKey, int $totalDocuments): array
    {
        return [
            'ongoingProjects' => $this->countOngoingProjects($procurementsByKey),
            'completedBiddings' => $this->countCompletedBiddings($procurementsByKey),
            'totalDocuments' => $totalDocuments,
        ];
    }

    /**
     * Group procurements by phase
     *
     * @param  Collection  $procurementsByKey  Procurements collection
     * @return array Array grouped by phase
     */
    public function groupProcurementsByPhase(Collection $procurementsByKey): array
    {
        $grouped = [
            'pre_procurement' => [],
            'procurement' => [],
            'post_procurement' => [],
        ];

        foreach ($procurementsByKey as $procurement) {
            $stageEnum = StageEnums::tryFrom($procurement['stage']);
            if ($stageEnum) {
                $phase = $stageEnum->getPhase();
                $grouped[$phase][] = $procurement;
            }
        }

        return [
            'pre_procurement' => [
                'title' => 'Pre-Procurement (Planning & Preparation)',
                'count' => count($grouped['pre_procurement']),
                'procurements' => $grouped['pre_procurement'],
            ],
            'procurement' => [
                'title' => 'Procurement (Bidding & Evaluation)',
                'count' => count($grouped['procurement']),
                'procurements' => $grouped['procurement'],
            ],
            'post_procurement' => [
                'title' => 'Post-Procurement (Award & Implementation)',
                'count' => count($grouped['post_procurement']),
                'procurements' => $grouped['post_procurement'],
            ],
        ];
    }

    /**
     * Get phase statistics
     *
     * @param  Collection  $procurementsByKey  Procurements collection
     * @return array Phase-based statistics
     */
    public function getPhaseStatistics(Collection $procurementsByKey): array
    {
        $stats = [
            'pre_procurement' => 0,
            'procurement' => 0,
            'post_procurement' => 0,
        ];

        foreach ($procurementsByKey as $procurement) {
            $stageEnum = StageEnums::tryFrom($procurement['stage']);
            if ($stageEnum) {
                $phase = $stageEnum->getPhase();
                $stats[$phase]++;
            }
        }

        return [
            'pre_procurement' => [
                'label' => 'Pre-Procurement',
                'count' => $stats['pre_procurement'],
                'percentage' => $procurementsByKey->count() > 0
                    ? round(($stats['pre_procurement'] / $procurementsByKey->count()) * 100, 1)
                    : 0,
            ],
            'procurement' => [
                'label' => 'Procurement',
                'count' => $stats['procurement'],
                'percentage' => $procurementsByKey->count() > 0
                    ? round(($stats['procurement'] / $procurementsByKey->count()) * 100, 1)
                    : 0,
            ],
            'post_procurement' => [
                'label' => 'Post-Procurement',
                'count' => $stats['post_procurement'],
                'percentage' => $procurementsByKey->count() > 0
                    ? round(($stats['post_procurement'] / $procurementsByKey->count()) * 100, 1)
                    : 0,
            ],
            'total' => $procurementsByKey->count(),
        ];
    }
}
