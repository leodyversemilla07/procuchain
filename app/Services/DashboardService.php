<?php

namespace App\Services;

use App\Enums\StreamEnums;
use App\Models\User;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Dashboard Service
 *
 * Provides shared dashboard functionality for all role-based dashboards.
 * Handles procurement data retrieval, transformation, and statistics calculation.
 */
class DashboardService
{
    private array $userNameCache = [];

    public function __construct(
        private MultichainService $multichainService,
        private EventTypeLabelMapper $eventTypeLabelMapper
    ) {}

    /**
     * Get user name from blockchain address with caching
     *
     * @param  string  $address  Blockchain address
     * @return string User name or 'Unknown'
     */
    public function getUserName(string $address): string
    {
        if (isset($this->userNameCache[$address])) {
            return $this->userNameCache[$address];
        }

        try {
            $name = User::where('blockchain_address', $address)->first()?->name ?? 'Unknown';
        } catch (Exception $e) {
            Log::warning("Failed to retrieve user name for address: $address", ['error' => $e->getMessage()]);
            $name = 'Unknown';
        }

        return $this->userNameCache[$address] = $name;
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
                    if (! isset($data['procurement_id'], $data['procurement_title'])) {
                        Log::warning('Invalid procurement data structure', ['data' => $data]);

                        return null;
                    }

                    return [
                        'id' => $data['procurement_id'],
                        'title' => $data['procurement_title'],
                        'stage' => $data['stage'] ?? '',
                        'status' => $data['current_status'] ?? $data['stage'] ?? '',
                        'user_address' => $data['user_address'] ?? '',
                        'user' => $this->getUserName($data['user_address'] ?? ''),
                        'timestamp' => $data['timestamp'] ?? '',
                    ];
                })
                ->filter()
                ->groupBy('id')
                ->map(function ($group) {
                    return $group->sortByDesc('timestamp')->first();
                });
        } catch (Exception $e) {
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
                return [
                    'id' => $item['id'],
                    'title' => $item['title'],
                    'stage' => $item['stage'],
                    'status' => $item['status'],
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
                return [
                    'id' => $item['id'],
                    'title' => $item['title'],
                    'stage' => $item['stage'],
                    'status' => $item['status'],
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
            $allEvents = $this->multichainService->listStreamItems(
                StreamEnums::EVENTS->value,
                true,
                config('dashboard.stream_limits.recent_activities'),
                config('dashboard.stream_limits.recent_activities_offset'),
                true
            );

            if (! $allEvents) {
                Log::warning('No events found in stream');

                return [];
            }

            return collect($allEvents)
                ->map(function ($item) {
                    $data = $item['data']['json'] ?? [];
                    if (! isset($data['procurement_id'], $data['procurement_title'])) {
                        return null;
                    }

                    $actionLabel = $this->eventTypeLabelMapper->getLabel(
                        $data['event_type'] ?? '',
                        $data['details'] ?? ''
                    );

                    return [
                        'id' => $data['procurement_id'],
                        'title' => $data['procurement_title'],
                        'action' => $actionLabel,
                        'details' => $data['details'] ?? '',
                        'raw_event_type' => $data['event_type'] ?? '',
                        'stage' => $data['stage_identifier'] ?? '',
                        'date' => $data['timestamp'] ?? now()->toIso8601String(),
                        'user' => $this->getUserName($data['user_address'] ?? ''),
                        'timestamp' => strtotime($data['timestamp'] ?? 'now'),
                    ];
                })
                ->filter()
                ->sortByDesc('timestamp')
                ->take(config('dashboard.display_limits.recent_activities_display'))
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
            $documentItems = $this->multichainService->listStreamItems(
                StreamEnums::DOCUMENTS->value,
                true,
                config('dashboard.stream_limits.document_items'),
                0,
                false
            );

            if ($documentItems === null) {
                Log::warning('Failed to retrieve document stream items for dashboard stats.');

                return 0;
            }

            $documentCountMap = collect($documentItems)
                ->filter(fn ($item) => isset($item['data']['json']['procurement_id']) && isset($item['data']['json']['hash']))
                ->groupBy(fn ($item) => $item['data']['json']['procurement_id'])
                ->map(function ($items) {
                    return collect($items)->map(fn ($item) => $item['data']['json']['hash'])->unique()->count();
                });

            $dashboardProcurementIds = $procurementsByKey->keys();
            $totalDocuments = $documentCountMap
                ->filter(fn ($count, $procurementId) => $dashboardProcurementIds->contains($procurementId))
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
}
