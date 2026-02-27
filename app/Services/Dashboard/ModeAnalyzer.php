<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Collection;

/**
 * Mode Analyzer
 *
 * Handles procurement-mode analysis for the dashboard:
 * mode distribution, competitive vs alternative breakdown, and grouping by mode.
 *
 * NGPA Compliance:
 * - Supports all 11 NGPA procurement modes (IRR Sections 27-37)
 * - Provides mode-based statistics for Municipality of Gloria (4th Class)
 * - Tracks competitive vs alternative mode distribution
 */
class ModeAnalyzer
{
    /**
     * Get mode distribution statistics
     *
     * NGPA Compliance: Tracks distribution across all 11 procurement modes
     * per IRR Sections 27-37, supporting both competitive and alternative methods.
     *
     * @param  Collection  $procurementsByKey  Procurements collection with mode data
     * @return array Mode distribution data for charts
     */
    public function getModeDistribution(Collection $procurementsByKey): array
    {
        $distribution = [];
        $total = $procurementsByKey->count();

        foreach ($procurementsByKey as $procurement) {
            $mode = $procurement['procurement_mode'] ?? 'unknown';
            $label = $procurement['procurement_mode_label'] ?? 'Unknown';

            if (! isset($distribution[$mode])) {
                $distribution[$mode] = [
                    'mode' => $mode,
                    'label' => $label,
                    'count' => 0,
                    'percentage' => 0,
                ];
            }

            $distribution[$mode]['count']++;
        }

        // Calculate percentages
        foreach ($distribution as $mode => $data) {
            $distribution[$mode]['percentage'] = $total > 0
                ? round(($data['count'] / $total) * 100, 1)
                : 0;
        }

        // Sort by count descending
        uasort($distribution, fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_values($distribution);
    }

    /**
     * Get mode type statistics (competitive vs alternative)
     *
     * NGPA Compliance:
     * - Competitive modes: Competitive Bidding, Limited Source, etc. (IRR Sections 27-30)
     * - Alternative modes: Direct Contracting, SVP, etc. (IRR Sections 31-37)
     *
     * @param  Collection  $procurementsByKey  Procurements collection with mode data
     * @return array Mode type statistics
     */
    public function getModeTypeStatistics(Collection $procurementsByKey): array
    {
        $competitive = 0;
        $alternative = 0;
        $unknown = 0;

        foreach ($procurementsByKey as $procurement) {
            $isAlternative = $procurement['is_alternative_mode'] ?? null;

            if ($isAlternative === true) {
                $alternative++;
            } elseif ($isAlternative === false) {
                $competitive++;
            } else {
                $unknown++;
            }
        }

        $total = $procurementsByKey->count();

        return [
            'competitive' => [
                'label' => 'Competitive Bidding Modes',
                'description' => 'Public Bidding, Limited Source Bidding, etc.',
                'ngpa_reference' => 'IRR Sections 27-30',
                'count' => $competitive,
                'percentage' => $total > 0 ? round(($competitive / $total) * 100, 1) : 0,
            ],
            'alternative' => [
                'label' => 'Alternative Modes',
                'description' => 'Direct Contracting, SVP, Negotiated, etc.',
                'ngpa_reference' => 'IRR Sections 31-37',
                'count' => $alternative,
                'percentage' => $total > 0 ? round(($alternative / $total) * 100, 1) : 0,
            ],
            'unknown' => [
                'label' => 'Unclassified',
                'count' => $unknown,
                'percentage' => $total > 0 ? round(($unknown / $total) * 100, 1) : 0,
            ],
            'total' => $total,
        ];
    }

    /**
     * Group procurements by mode
     *
     * @param  Collection  $procurementsByKey  Procurements collection with mode data
     * @return array Procurements grouped by mode
     */
    public function groupProcurementsByMode(Collection $procurementsByKey): array
    {
        $grouped = [];

        foreach ($procurementsByKey as $procurement) {
            $mode = $procurement['procurement_mode'] ?? 'unknown';
            $label = $procurement['procurement_mode_label'] ?? 'Unknown';

            if (! isset($grouped[$mode])) {
                $grouped[$mode] = [
                    'mode' => $mode,
                    'label' => $label,
                    'procurements' => [],
                    'count' => 0,
                ];
            }

            $grouped[$mode]['procurements'][] = $procurement;
            $grouped[$mode]['count']++;
        }

        // Sort by count descending
        uasort($grouped, fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_values($grouped);
    }

    /**
     * Get comprehensive mode statistics for dashboard
     *
     * Provides all mode-related statistics in one call for dashboard efficiency.
     *
     * @param  Collection  $procurementsByKey  Procurements collection with mode data
     * @return array Comprehensive mode statistics
     */
    public function getModeStatistics(Collection $procurementsByKey): array
    {
        return [
            'distribution' => $this->getModeDistribution($procurementsByKey),
            'type_breakdown' => $this->getModeTypeStatistics($procurementsByKey),
            'by_mode' => $this->groupProcurementsByMode($procurementsByKey),
        ];
    }
}
