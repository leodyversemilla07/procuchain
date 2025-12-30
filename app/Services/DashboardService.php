<?php

namespace App\Services;

use App\DataTransferObjects\DocumentData;
use App\DataTransferObjects\EventData;
use App\Models\User;
use App\Repositories\DocumentRepository;
use App\Repositories\EventRepository;
use App\Repositories\ProcurementRepository;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Dashboard Service
 *
 * Provides shared dashboard functionality for all role-based dashboards.
 * Handles procurement data retrieval, transformation, and statistics calculation.
 *
 * Merged EventTypeLabelMapper into this service to reduce redundancy.
 *
 * NGPA Compliance:
 * - Supports all 11 NGPA procurement modes (IRR Sections 27-37)
 * - Provides mode-based statistics for Municipality of Gloria (4th Class)
 * - Tracks competitive vs alternative mode distribution
 */
class DashboardService
{
    private array $eventLabelMap = [
        'document_upload' => 'Uploaded Documents',
        'phase_transition' => 'Phase Transition',
        'publication' => 'Published Documents',
        'procurement completed' => 'Completed Procurement',
    ];

    public function __construct(
        private Manager $multichain,
        private EventRepository $eventRepository,
        private DocumentRepository $documentRepository,
        private UserService $userService,
        private ProcurementRepository $procurementRepository
    ) {}

    /**
     * Get user name from blockchain address with caching
     *
     * @param  string  $address  Blockchain address
     * @return string User name or 'Unknown'
     */
    public function getUserName(string $address): string
    {
        return $this->userService->getUserNameByAddress($address);
    }

    /**
     * Get procurements grouped by key with latest status
     *
     * @param  array  $allStates  Raw status stream items from blockchain
     * @return Collection Collection of procurements keyed by procurement ID
     */
    public function getProcurementsByKey(array $allStates): Collection
    {
        try {
            // First pass: collect all PR numbers
            $prNumbers = collect($allStates)
                ->map(fn ($item) => $item['data']['json']['pr_number'] ?? null)
                ->filter()
                ->unique()
                ->values()
                ->all();

            // Build mode map for all PR numbers
            $modeMap = $this->buildProcurementModeMap($prNumbers);

            return collect($allStates)
                ->map(function ($item) use ($modeMap) {
                    $data = $item['data']['json'] ?? [];
                    if (! isset($data['pr_number'], $data['procurement_title'])) {
                        Log::warning('Invalid procurement data structure', ['data' => $data]);

                        return null;
                    }

                    $prNumber = $data['pr_number'];
                    $modeInfo = $modeMap[$prNumber] ?? null;

                    return [
                        'id' => $prNumber,
                        'title' => $data['procurement_title'],
                        'stage' => $data['stage'] ?? '',
                        'status' => $data['current_status'] ?? $data['stage'] ?? '',
                        'user_address' => $data['user_address'] ?? '',
                        'user' => $this->getUserName($data['user_address'] ?? ''),
                        'timestamp' => $data['timestamp'] ?? '',
                        'blockchain_time' => $item['time'] ?? 0,
                        'procurement_mode' => $modeInfo['value'] ?? null,
                        'procurement_mode_label' => $modeInfo['label'] ?? null,
                        'is_alternative_mode' => $modeInfo['is_alternative'] ?? null,
                    ];
                })
                ->filter()
                ->groupBy('id')
                ->map(function ($group) {
                    return $group->sortByDesc('blockchain_time')->first();
                });
        } catch (\Exception $e) {
            Log::error('Error processing procurement data', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get recent procurements for dashboard display
     *
     * @param  Collection  $procurementsByKey  Procurements collection
     * @return array Array of recent procurements
     */
    public function getRecentProcurements(Collection $procurementsByKey): array
    {
        return $procurementsByKey->sortByDesc('timestamp')
            ->take(config('dashboard.display_limits.recent_procurements'))
            ->values()
            ->map(function ($item) {
                $stageEnum = \App\Enums\StageEnums::tryFrom($item['stage']);
                $statusEnum = \App\Enums\StatusEnums::tryFrom($item['status']);

                return [
                    'id' => $item['id'],
                    'title' => $item['title'],
                    'stage' => $stageEnum ? $stageEnum->getDisplayName() : $item['stage'],
                    'status' => $statusEnum ? $statusEnum->getDisplayName() : $item['status'],
                    'procurement_mode' => $item['procurement_mode'] ?? null,
                    'procurement_mode_label' => $item['procurement_mode_label'] ?? null,
                    'is_alternative_mode' => $item['is_alternative_mode'] ?? null,
                ];
            })
            ->toArray();
    }

    /**
     * Get procurement distribution data for charts/visualization
     *
     * @param  Collection  $procurementsByKey  Procurements collection
     * @return array Array of procurement distribution data
     */
    public function getProcurementDistributionData(Collection $procurementsByKey): array
    {
        return $procurementsByKey->sortByDesc('timestamp')
            ->values()
            ->map(function ($item) {
                $stageEnum = \App\Enums\StageEnums::tryFrom($item['stage']);
                $statusEnum = \App\Enums\StatusEnums::tryFrom($item['status']);

                return [
                    'id' => $item['id'],
                    'title' => $item['title'],
                    'stage' => $stageEnum ? $stageEnum->getDisplayName() : $item['stage'],
                    'status' => $statusEnum ? $statusEnum->getDisplayName() : $item['status'],
                    'procurement_mode' => $item['procurement_mode'] ?? null,
                    'procurement_mode_label' => $item['procurement_mode_label'] ?? null,
                    'is_alternative_mode' => $item['is_alternative_mode'] ?? null,
                ];
            })
            ->toArray();
    }

    /**
     * Get recent activities from blockchain events stream
     * Optimized with pagination and limits
     *
     * @return array Array of recent activities
     */
    public function getRecentActivities(): array
    {
        try {
            $limit = config('dashboard.display_limits.recent_activities_display');
            // Fetch only the required amount plus some buffer for filtering
            $fetchLimit = min($limit * 2, config('dashboard.stream_limits.recent_activities'));
            $eventDtos = $this->eventRepository->findRecent($fetchLimit);

            if (empty($eventDtos)) {
                Log::warning('No events found in repository');

                return [];
            }

            return collect($eventDtos)
                ->map(function (EventData $event) {
                    $actionLabel = $this->getEventLabel(
                        $event->eventType,
                        $event->details
                    );

                    return [
                        'id' => $event->prNumber,
                        'title' => $event->procurementTitle,
                        'action' => $actionLabel,
                        'details' => $event->details,
                        'raw_event_type' => $event->eventType,
                        'stage' => $event->stage,
                        'date' => $event->timestamp,
                        'user' => $this->getUserName($event->userAddress),
                        'timestamp' => $event->timestamp->timestamp,
                    ];
                })
                ->sortByDesc('timestamp')
                ->take($limit)
                ->values()
                ->toArray();
        } catch (Exception $e) {
            Log::error('Failed to retrieve recent activities', [
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Get total document count for procurements on dashboard
     * Optimized with limits and better performance
     *
     * @param  Collection  $procurementsByKey  Procurements collection
     * @return int Total document count
     */
    public function getTotalDocuments(Collection $procurementsByKey): int
    {
        try {
            // Only fetch recent documents to improve performance
            $documentLimit = config('dashboard.stream_limits.document_items', 500);
            $documentDtos = $this->documentRepository->findRecent($documentLimit);

            if (empty($documentDtos)) {
                Log::warning('Failed to retrieve document stream items for dashboard stats.');

                return 0;
            }

            // Get unique procurement IDs from dashboard procurements
            $dashboardPrNumbers = $procurementsByKey->keys()->toArray();

            // Count unique documents per procurement, but only for dashboard procurements
            $documentCountMap = collect($documentDtos)
                ->filter(fn (DocumentData $doc) => in_array($doc->prNumber, $dashboardPrNumbers))
                ->groupBy(fn (DocumentData $doc) => $doc->prNumber)
                ->map(function ($docs) {
                    return collect($docs)->pluck('hash')->unique()->count();
                });

            $totalDocuments = $documentCountMap->sum();

            Log::info('Dashboard document count calculated', [
                'total_documents' => $totalDocuments,
                'procurements_counted' => count($dashboardPrNumbers),
                'documents_fetched' => count($documentDtos),
            ]);

            return $totalDocuments;
        } catch (Exception $e) {
            Log::error('Failed to calculate total documents for dashboard', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 0;
        }
    }

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
            if ($item['stage'] === \App\Enums\StageEnums::COMPLETED->value) {
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
            $stageEnum = \App\Enums\StageEnums::tryFrom($procurement['stage']);
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
            $stageEnum = \App\Enums\StageEnums::tryFrom($procurement['stage']);
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

    /**
     * Get event label from event type
     *
     * Merged from EventTypeLabelMapper
     */
    public function getEventLabel(string $eventType, string $details = ''): string
    {
        $eventType = strtolower($eventType);

        if (isset($this->eventLabelMap[$eventType])) {
            return $this->eventLabelMap[$eventType];
        }

        if ($eventType === 'decision' && str_contains(strtolower($details), 'pre-procurement')) {
            return 'Pre-Procurement Decision';
        } elseif ($eventType === 'decision') {
            return 'Decision Made';
        }

        return ucwords(str_replace('_', ' ', $eventType));
    }

    /**
     * Build a map of procurement modes by PR number
     *
     * @param  array  $prNumbers  Array of PR numbers
     * @return array<string, array{value: string, label: string, is_alternative: bool}>
     */
    private function buildProcurementModeMap(array $prNumbers): array
    {
        $modeMap = [];

        foreach ($prNumbers as $prNumber) {
            try {
                $procurement = $this->procurementRepository->findByProcurement($prNumber);
                if ($procurement && $procurement->procurementMode) {
                    $modeMap[$prNumber] = [
                        'value' => $procurement->procurementMode->value,
                        'label' => $procurement->procurementMode->getDisplayName(),
                        'is_alternative' => $procurement->procurementMode->isAlternativeMode(),
                    ];
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get procurement mode for dashboard', [
                    'pr_number' => $prNumber,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $modeMap;
    }

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
