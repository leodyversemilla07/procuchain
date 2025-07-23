<?php

namespace App\Http\Controllers;

use App\Enums\StreamEnums;
use App\Models\User;
use App\Services\MultichainService;
use App\Services\EventTypeLabelMapper;
use Exception;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class HopeController extends BaseController
{
    private MultichainService $multichainService;
    private EventTypeLabelMapper $eventTypeLabelMapper;
    private array $userNameCache = [];

    public function __construct(MultichainService $multichainService, EventTypeLabelMapper $eventTypeLabelMapper)
    {
        $this->multichainService = $multichainService;
        $this->eventTypeLabelMapper = $eventTypeLabelMapper;
        $this->middleware('auth');
        $this->middleware('role:hope');
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

    public function index()
    {
        try {
            Log::info('Fetching HOPE dashboard data');

            $procurementsByKey = Cache::remember('hope_dashboard_procurements_by_key', now()->addMinutes(5), function () {
                Log::info('Cache miss: Recalculating procurementsByKey for HOPE dashboard');
                $states = $this->multichainService->listStreamItems(
                    StreamEnums::STATUS->value, true, 10000, 0, false
                );
                if ($states === null) {
                    throw new Exception('Failed to retrieve status stream items for HOPE procurementsByKey cache');
                }

                return $this->getProcurementsByKey($states);
            });

            if ($procurementsByKey === null || $procurementsByKey->isEmpty()) {
                Log::warning('HOPE ProcurementsByKey is null or empty after cache check.');
                $procurementsByKey = collect();
            }

            $recentActivities = Cache::remember('hope_dashboard_recent_activities', now()->addMinutes(2), function () {
                return $this->getRecentActivities();
            });

            $stats = Cache::remember('hope_dashboard_stats', now()->addMinutes(5), function () use ($procurementsByKey) {
                Log::info('Cache miss: Recalculating HOPE dashboard stats');

                return $this->getDashboardStats($procurementsByKey, 0);
            });

            $dashboardData = [
                'recentProcurements' => $this->getRecentProcurements($procurementsByKey),
                'recentActivities' => $recentActivities,
                'stats' => $stats,
            ];

            Log::info('Successfully retrieved HOPE dashboard data');

            return Inertia::render('hope/dashboard', $dashboardData);

        } catch (Exception $e) {
            Log::error('Failed to retrieve HOPE dashboard data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Cache::forget('hope_dashboard_procurements_by_key');
            Cache::forget('hope_dashboard_recent_activities');
            Cache::forget('hope_dashboard_stats');
            Cache::forget('hope_dashboard_total_documents');

            return Inertia::render('hope/dashboard', [
                'recentProcurements' => [],
                'recentActivities' => [],
                'stats' => $this->getEmptyStats(),
                'error' => 'Failed to retrieve dashboard data. Please try again later.',
            ]);
        }
    }

    private function getDashboardStats($procurementsByKey, int $pendingActions): array
    {
        try {
            $totalDocuments = Cache::remember('hope_dashboard_total_documents', now()->addMinutes(5), function () use ($procurementsByKey) {
                Log::info('Cache miss: Recalculating total documents for HOPE dashboard stats');

                return $this->getTotalDocuments($procurementsByKey);
            });

            return [
                'ongoingProjects' => $this->countOngoingProjects($procurementsByKey),
                'completedBiddings' => $this->countCompletedBiddings($procurementsByKey),
                'totalDocuments' => $totalDocuments,
            ];
        } catch (Exception $e) {
            Log::error('Failed to calculate HOPE dashboard stats', ['error' => $e->getMessage()]);
            Cache::forget('hope_dashboard_total_documents');

            return $this->getEmptyStats();
        }
    }

    private function getEmptyStats(): array
    {
        return [
            'ongoingProjects' => 0,
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
                        Log::warning('Invalid procurement data structure in HOPE context', ['data' => $data]);

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
            Log::error('Error processing procurement data for HOPE', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function getRecentProcurements($procurementsByKey)
    {
        return $procurementsByKey->sortByDesc('timestamp')
            ->take(5)
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
                StreamEnums::EVENTS->value, true, 20, -20, true
            );

            if (! $allEvents) {
                Log::warning('No events found in stream for HOPE dashboard');

                return [];
            }

            return collect($allEvents)
                ->map(function ($item) {
                    $data = $item['data']['json'] ?? [];
                    if (! isset($data['procurement_id'], $data['procurement_title'])) {
                        return null;
                    }
                    $actionLabel = $this->eventTypeLabelMapper->getLabel(
                        $data['event_type'] ?? '', $data['details'] ?? ''
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
            Log::error('Failed to retrieve recent activities for HOPE', [
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    private function getTotalDocuments($procurementsByKey)
    {
        try {
            $client = $this->multichainService;
            $documentItems = $client->listStreamItems(
                StreamEnums::DOCUMENTS->value, true, 2000, 0, false
            );

            if ($documentItems === null) {
                Log::warning('Failed to retrieve document stream items for HOPE dashboard stats.');

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

            Log::info('HOPE dashboard document count calculated', ['total_documents' => $totalDocuments]);

            return $totalDocuments;

        } catch (Exception $e) {
            Log::error('Failed to calculate total documents for HOPE dashboard', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 0;
        }
    }

    private function countCompletedBiddings($procurementsByKey)
    {
        return $procurementsByKey->filter(function ($item) {
            return in_array($item['stage'], [
                'Notice Of Award', 'Performance Bond', 'Contract And PO',
                'Notice To Proceed', 'Monitoring', 'Completed',
            ]);
        })->count();
    }
}
