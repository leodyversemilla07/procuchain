<?php

namespace App\Services;

use App\DataTransferObjects\DocumentData;
use App\DataTransferObjects\EventData;
use App\Models\User;
use App\Repositories\DocumentRepository;
use App\Repositories\EventRepository;
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
        private UserService $userService
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
            return collect($allStates)
                ->map(function ($item) {
                    $data = $item['data']['json'] ?? [];
                    if (! isset($data['pr_number'], $data['procurement_title'])) {
                        Log::warning('Invalid procurement data structure', ['data' => $data]);

                        return null;
                    }

                    return [
                        'id' => $data['pr_number'],
                        'title' => $data['procurement_title'],
                        'stage' => $data['stage'] ?? '',
                        'status' => $data['current_status'] ?? $data['stage'] ?? '',
                        'user_address' => $data['user_address'] ?? '',
                        'user' => $this->getUserName($data['user_address'] ?? ''),
                        'timestamp' => $data['timestamp'] ?? '',
                        'blockchain_time' => $item['time'] ?? 0,
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
                ];
            })
            ->toArray();
    }

    /**
     * Get recent activities from blockchain events stream
     *
     * @return array Array of recent activities
     */
    public function getRecentActivities(): array
    {
        try {
            $limit = config('dashboard.display_limits.recent_activities_display');
            $eventDtos = $this->eventRepository->findRecent($limit * 2); // Fetch extra to allow for filtering

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
     *
     * @param  Collection  $procurementsByKey  Procurements collection
     * @return int Total document count
     */
    public function getTotalDocuments(Collection $procurementsByKey): int
    {
        try {
            $documentDtos = $this->documentRepository->all();

            if (empty($documentDtos)) {
                Log::warning('Failed to retrieve document stream items for dashboard stats.');

                return 0;
            }

            $documentCountMap = collect($documentDtos)
                ->groupBy(fn (DocumentData $doc) => $doc->pr_number)
                ->map(function ($docs) {
                    return collect($docs)->pluck('hash')->unique()->count();
                });

            $dashboardpr_numbers = $procurementsByKey->keys();
            $totalDocuments = $documentCountMap
                ->filter(fn ($count, $pr_number) => $dashboardpr_numbers->contains($pr_number))
                ->sum();

            Log::info('Dashboard document count calculated', ['total_documents' => $totalDocuments]);

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
        return $procurementsByKey->filter(function ($item) {
            return $item['stage'] !== 'Monitoring' ||
                ($item['stage'] === 'Monitoring' && $item['status'] !== 'Completed');
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
}
