<?php

namespace App\Http\Controllers;

use App\Enums\StreamEnums;
use App\Models\User;
use App\Services\EventTypeLabelMapper;
use App\Services\MultichainService;
use App\Services\ProcurementStageTransitionService;
use Exception;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class BacSecretariatController extends BaseController
{
    private MultichainService $multichainService;

    private EventTypeLabelMapper $eventTypeLabelMapper;

    private ProcurementStageTransitionService $stageTransitionService;

    private array $userNameCache = [];

    public function __construct(
        MultichainService $multichainService,
        ProcurementStageTransitionService $stageTransitionService,
        EventTypeLabelMapper $eventTypeLabelMapper
    ) {
        $this->multichainService = $multichainService;
        $this->stageTransitionService = $stageTransitionService;
        $this->eventTypeLabelMapper = $eventTypeLabelMapper;
        $this->setupMiddleware();
    }

    private function setupMiddleware(): void
    {
        $this->middleware('auth');
        $this->middleware('role:bac_secretariat');
    }

    private function getUserName(string $address): string
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

    public function dashboard()
    {
        try {
            Log::info('Fetching Bids and Awards Committee Secretariat Dashboard data');

            // Cache procurementsByKey for 5 minutes
            $procurementsByKey = Cache::remember('dashboard_procurements_by_key', now()->addMinutes(5), function () {
                Log::info('Cache miss: Recalculating procurementsByKey for dashboard');
                $states = $this->multichainService->listStreamItems(
                    StreamEnums::STATUS->value,
                    true,
                    10000, // Consider if this limit needs adjustment or pagination
                    0,
                    false
                );

                if ($states === null) {
                    throw new Exception('Failed to retrieve status stream items for procurementsByKey cache');
                }

                return $this->getProcurementsByKey($states);
            });

            if ($procurementsByKey === null || $procurementsByKey->isEmpty()) {
                Log::warning('ProcurementsByKey is null or empty after cache check.');
                $procurementsByKey = collect();
            }

            // Cache recent activities for 2 minutes
            $recentActivities = Cache::remember('dashboard_recent_activities', now()->addMinutes(2), function () {
                return $this->getRecentActivities();
            });

            // Cache priority actions for 2 minutes
            $priorityActions = Cache::remember('dashboard_priority_actions', now()->addMinutes(2), function () use ($procurementsByKey) {
                $allPriorityActions = $this->getPriorityActions($procurementsByKey);

                return array_slice($allPriorityActions, 0, 3);
            });

            // Cache count of all priority actions for 2 minutes
            $allPriorityActionsCount = Cache::remember('dashboard_priority_actions_count', now()->addMinutes(2), function () use ($procurementsByKey) {
                return count($this->getPriorityActions($procurementsByKey));
            });
            // Cache procurement distribution data for 5 minutes
            $procurementDistribution = Cache::remember('dashboard_procurement_distribution', now()->addMinutes(5), function () use ($procurementsByKey) {
                return $this->getProcurementDistributionData($procurementsByKey);
            });
            $stats = Cache::remember('dashboard_stats', now()->addMinutes(5), function () use ($procurementsByKey, $allPriorityActionsCount) {
                Log::info('Cache miss: Recalculating dashboard stats');

                return $this->getDashboardStats($procurementsByKey, $allPriorityActionsCount);
            });

            $dashboardData = [
                'recentProcurements' => $this->getRecentProcurements($procurementsByKey),
                'procurementDistribution' => $procurementDistribution,
                'recentActivities' => $recentActivities,
                'priorityActions' => $priorityActions,
                'stats' => $stats,
            ];

            Log::info('Successfully retrieved dashboard data', [
                'procurement_count' => $procurementsByKey ? $procurementsByKey->count() : 0,
                'recent_procurements_count' => count($dashboardData['recentProcurements']),
                'distribution_data_count' => count($dashboardData['procurementDistribution']),
                'activities_count' => count($dashboardData['recentActivities']),
                'stats_from_cache' => Cache::has('dashboard_stats'),
                'procurements_from_cache' => Cache::has('dashboard_procurements_by_key'),
            ]);

            return Inertia::render('bac-secretariat/dashboard', $dashboardData);

        } catch (Exception $e) {
            Log::error('Failed to retrieve dashboard data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Cache::forget('dashboard_procurements_by_key');
            Cache::forget('dashboard_stats');
            Cache::forget('dashboard_total_documents');
            Cache::forget('dashboard_recent_activities');
            Cache::forget('dashboard_priority_actions');
            Cache::forget('dashboard_priority_actions_count');
            Cache::forget('dashboard_procurement_distribution');

            return Inertia::render('bac-secretariat/dashboard', [
                'recentProcurements' => [],
                'procurementDistribution' => [],
                'recentActivities' => [],
                'priorityActions' => [],
                'stats' => $this->getEmptyStats(),
                'error' => 'Failed to retrieve dashboard data. Please try again later.',
            ]);
        }
    }

    private function getDashboardStats($procurementsByKey, int $pendingActions): array
    {
        // This function now receives $procurementsByKey, no need to fetch status again
        try {
            // Cache the total documents calculation within this stats block if needed,
            // or rely on the outer caching of the entire stats array.
            // Caching getTotalDocuments separately might offer finer control if it's the slowest part.
            $totalDocuments = Cache::remember('dashboard_total_documents', now()->addMinutes(5), function () use ($procurementsByKey) {
                Log::info('Cache miss: Recalculating total documents for dashboard stats');

                // Pass $procurementsByKey if needed by the optimized getTotalDocuments
                return $this->getTotalDocuments($procurementsByKey);
            });

            return [
                'ongoingProjects' => $this->countOngoingProjects($procurementsByKey),
                'pendingActions' => $pendingActions,
                'completedBiddings' => $this->countCompletedBiddings($procurementsByKey),
                'totalDocuments' => $totalDocuments, // Use cached/recalculated total
            ];
        } catch (Exception $e) {
            Log::error('Failed to calculate dashboard stats', [
                'error' => $e->getMessage(),
            ]);
            Cache::forget('dashboard_total_documents'); // Clear specific cache on error

            return $this->getEmptyStats();
        }
    }

    private function getEmptyStats(): array
    {
        return [
            'ongoingProjects' => 0,
            'pendingActions' => 0,
            'completedBiddings' => 0,
            'totalDocuments' => 0,
        ];
    }

    private function countOngoingProjects($procurementsByKey): int
    {
        return $procurementsByKey->filter(function ($item) {
            return $item['stage'] !== 'Monitoring' ||
                ($item['stage'] === 'Monitoring' && $item['status'] !== 'Completed');
        })->count();
    }

    private function getProcurementsByKey($allStates)
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
            Log::error('Error processing procurement data', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function getRecentProcurements($procurementsByKey)
    {
        return $procurementsByKey->sortByDesc('timestamp')
            ->take(5) // Keep only 5 for the recent procurements table
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

    private function getProcurementDistributionData($procurementsByKey)
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

    private function getRecentActivities()
    {
        try {
            $allEvents = $this->multichainService->listStreamItems(
                StreamEnums::EVENTS->value,
                true,
                20,
                -20,
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

                    $actionLabel = $this->eventTypeLabelMapper->getLabel($data['event_type'] ?? '', $data['details'] ?? '');

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
                ->take(8)
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

    private function getPriorityActions($procurementsByKey)
    {
        try {
            $priorityActions = [];

            foreach ($procurementsByKey as $procurement) {
                try {
                    $action = $this->stageTransitionService->getPriorityAction(
                        $procurement['stage'],
                        $procurement['status'],
                        $procurement['id'],
                        $procurement['title']
                    );

                    if ($action !== null) {
                        $priorityActions[] = $action;
                    }
                } catch (Exception $e) {
                    Log::warning("Failed to get priority action for procurement {$procurement['id']}", [
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }
            }

            return $priorityActions;

        } catch (Exception $e) {
            Log::error('Failed to retrieve priority actions', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function getTotalDocuments($procurementsByKey)
    {
        try {
            $client = $this->multichainService;

            // Fetch all document items (for all procurements)
            $documentItems = $client->listStreamItems(
                StreamEnums::DOCUMENTS->value,
                true,
                2000, // Reduced from 10000 to 2000 for performance
                0,
                false
            );

            if ($documentItems === null) {
                Log::warning('Failed to retrieve document stream items for dashboard stats.');

                return 0;
            }

            // Build a map: procurement_id => document count (unique by hash)
            $documentCountMap = collect($documentItems)
                ->filter(fn ($item) => isset($item['data']['json']['procurement_id']) && isset($item['data']['json']['hash']))
                ->groupBy(fn ($item) => $item['data']['json']['procurement_id'])
                ->map(function ($items) {
                    return collect($items)
                        ->map(fn ($item) => $item['data']['json']['hash'])
                        ->unique()
                        ->count();
                });

            // Get the IDs of procurements relevant to the dashboard
            $dashboardProcurementIds = $procurementsByKey->keys();

            // Sum the counts only for the procurements relevant to the dashboard
            $totalDocuments = $documentCountMap
                ->filter(fn ($count, $procurementId) => $dashboardProcurementIds->contains($procurementId))
                ->sum();

            // Optional: Log the breakdown for debugging
            $documentCounts = $documentCountMap
                ->filter(fn ($count, $procurementId) => $dashboardProcurementIds->contains($procurementId))
                ->map(function ($count, $procurementId) use ($procurementsByKey) {
                    return [
                        'procurement_id' => $procurementId,
                        'procurement_title' => $procurementsByKey->get($procurementId)['title'] ?? 'N/A',
                        'document_count' => $count,
                    ];
                })
                ->values()
                ->toArray();

            Log::info('Dashboard document count breakdown', [
                'total_documents' => $totalDocuments,
                'procurement_count_on_dashboard' => $procurementsByKey->count(),
                'document_counts_by_procurement' => $documentCounts,
            ]);

            return $totalDocuments;

        } catch (Exception $e) {
            Log::error('Failed to calculate total documents for dashboard', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(), // Include trace for better debugging
            ]);

            return 0;
        }
    }

    private function countCompletedBiddings($procurementsByKey)
    {
        return $procurementsByKey->filter(function ($item) {
            return in_array($item['stage'], [
                'Notice Of Award',
                'Performance Bond',
                'Contract And PO',
                'Notice To Proceed',
                'Monitoring',
                'Completed',
            ]);
        })->count();
    }
}
