<?php

namespace App\Http\Controllers;

use App\Enums\StreamEnums;
use App\Models\User;
use App\Services\ProcurementServices;
use Exception;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class BacSecretariatController extends BaseController
{
    private $services;

    // cache for user names to avoid repeated DB queries
    private array $userNameCache = [];

    public function __construct(ProcurementServices $services)
    {
        $this->services = $services;
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
            Log::info('Fetching BAC Secretariat dashboard data');

            $states = $this->services->getMultiChain()->listStreamItems(
                StreamEnums::STATUS->value,
                true,
                1000,
                -10,
                false
            );

            if ($states === null) {
                throw new Exception('Failed to retrieve stream items');
            }

            $procurementsByKey = $this->getProcurementsByKey($states);

            // compute priority actions once
            $allPriorityActions = $this->getPriorityActions($procurementsByKey);
            $priorityActions = array_slice($allPriorityActions, 0, 3);

            $dashboardData = [
                'recentProcurements' => $this->getRecentProcurements($procurementsByKey),
                'recentActivities' => $this->getRecentActivities(),
                'priorityActions' => $priorityActions,
                'stats' => $this->getDashboardStats($procurementsByKey, count($allPriorityActions)),
            ];

            Log::info('Successfully retrieved dashboard data', [
                'procurement_count' => count($procurementsByKey),
                'activities_count' => count($dashboardData['recentActivities']),
            ]);

            return Inertia::render('bac-secretariat/dashboard', $dashboardData);

        } catch (Exception $e) {
            Log::error('Failed to retrieve dashboard data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Inertia::render('bac-secretariat/dashboard', [
                'recentProcurements' => [],
                'recentActivities' => [],
                'priorityActions' => [],
                'stats' => $this->getEmptyStats(),
                'error' => 'Failed to retrieve dashboard data. Please try again later.',
            ]);
        }
    }

    private function getDashboardStats($procurementsByKey, int $pendingActions): array
    {
        try {
            return [
                'ongoingProjects' => $this->countOngoingProjects($procurementsByKey),
                'pendingActions' => $pendingActions,
                'completedBiddings' => $this->countCompletedBiddings($procurementsByKey),
                'totalDocuments' => $this->getTotalDocuments($procurementsByKey),
            ];
        } catch (Exception $e) {
            Log::error('Failed to calculate dashboard stats', [
                'error' => $e->getMessage(),
            ]);

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
                    if (!isset($data['procurement_id'], $data['procurement_title'])) {
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
            ->take(10)
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
            $allEvents = $this->services->getMultiChain()->listStreamItems(
                StreamEnums::EVENTS->value,
                true,  // verbose mode
                20,    // increased count
                -20,   // get last 20 items
                true   // local ordering
            );

            if (!$allEvents) {
                Log::warning('No events found in stream');

                return [];
            }

            return collect($allEvents)
                ->map(function ($item) {
                    $data = $item['data']['json'] ?? [];
                    if (!isset($data['procurement_id'], $data['procurement_title'])) {
                        return null;
                    }

                    $actionLabel = $this->services->getEventTypeLabelMapper()->getLabel(
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
                    $action = $this->services->getStageTransitionService()->getPriorityAction(
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
            $totalDocuments = 0;
            $documentCounts = [];
            $client = $this->services->getMultiChain();

            foreach ($procurementsByKey as $procurement) {
                try {
                    // use only current title for stream key generation
                    $streamKey = $this->services->getStreamKeyService()->generate(
                        $procurement['id'],
                        $procurement['title']
                    );

                    $documents = $client->listStreamKeyItems(
                        StreamEnums::DOCUMENTS->value,
                        $streamKey
                    );

                    // count unique document hashes
                    $uniqueDocCount = collect($documents ?? [])
                        ->map(fn($doc) => $doc['data']['json']['hash'] ?? '')
                        ->unique()
                        ->filter()
                        ->count();

                    $totalDocuments += $uniqueDocCount;

                    $documentCounts[] = [
                        'procurement_id' => $procurement['id'],
                        'procurement_title' => $procurement['title'],
                        'document_count' => $uniqueDocCount,
                    ];

                } catch (Exception $e) {
                    Log::warning("Failed to count documents for procurement {$procurement['id']}", [
                        'error' => $e->getMessage(),
                        'procurement_title' => $procurement['title'],
                    ]);
                    continue;
                }
            }

            // log summary of document counts
            Log::info('Document count breakdown', [
                'total_documents' => $totalDocuments,
                'procurement_count' => count($procurementsByKey),
                'document_counts_by_procurement' => $documentCounts
            ]);

            return $totalDocuments;

        } catch (Exception $e) {
            Log::error('Failed to calculate total documents', [
                'error' => $e->getMessage(),
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
