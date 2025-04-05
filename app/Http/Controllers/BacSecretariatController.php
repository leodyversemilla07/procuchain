<?php

namespace App\Http\Controllers;

use App\Enums\StreamEnums;
use App\Services\BacSecretariatServices;
use Exception;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class BacSecretariatController extends BaseController
{
    private $services;

    public function __construct(BacSecretariatServices $services)
    {
        $this->services = $services;
        $this->setupMiddleware();
    }

    private function setupMiddleware(): void
    {
        $this->middleware('auth');
        $this->middleware('role:bac_secretariat');
    }

    public function index()
    {
        try {
            $allStates = $this->services->getMultiChain()->listStreamItems(StreamEnums::STATUS->value, true, 1000, -1000);
            if ($allStates === null) {
                Log::warning('Multichain returned null for stream items');
                $allStates = [];
            }

            $procurementsByKey = $this->getProcurementsByKey($allStates);

            return Inertia::render('bac-secretariat/dashboard', [
                'recentProcurements' => $this->getRecentProcurements($procurementsByKey),
                'recentActivities' => $this->getRecentActivities(),
                'priorityActions' => array_slice($this->getPriorityActions($procurementsByKey), 0, 3),
                'stats' => $this->getDashboardStats($procurementsByKey),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to retrieve dashboard data:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Inertia::render('bac-secretariat/dashboard', [
                'recentProcurements' => [],
                'recentActivities' => [],
                'priorityActions' => [],
                'stats' => $this->getEmptyStats(),
                'error' => 'Failed to retrieve dashboard data: '.$e->getMessage(),
            ]);
        }
    }

    private function getDashboardStats($procurementsByKey): array 
    {
        return [
            'ongoingProjects' => $this->countOngoingProjects($procurementsByKey),
            'pendingActions' => count($this->getPriorityActions($procurementsByKey)),
            'completedBiddings' => $this->countCompletedBiddings($procurementsByKey),
            'totalDocuments' => $this->getTotalDocuments($procurementsByKey),
        ];
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
        return collect($allStates)
            ->map(function ($item) {
                $data = $item['data'];

                return [
                    'id' => $data['procurement_id'] ?? '',
                    'title' => $data['procurement_title'] ?? '',
                    'stage' => $data['stage'] ?? '',
                    'status' => $data['current_status'] ?? '',
                    'user_address' => $data['user_address'] ?? '',
                    'user' => \App\Models\User::where('blockchain_address', $data['user_address'] ?? '')->first()?->name ?? 'Unknown',
                    'timestamp' => $data['timestamp'] ?? '',
                ];
            })
            ->groupBy('id')
            ->map(function ($group) {
                return $group->sortByDesc('timestamp')->first();
            });
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
                    'status' => $item['status']
                ];
            })
            ->toArray();
    }

    private function getRecentActivities()
    {
        $allEvents = $this->services->getMultiChain()->listStreamItems(StreamEnums::EVENTS->value, true, 300, -300);

        return collect($allEvents)
            ->map(function ($item) {
                $data = $item['data'];
                $actionLabel = $this->services->getEventTypeLabelMapper()->getLabel(
                    $data['event_type'] ?? '',
                    $data['details'] ?? ''
                );

                return [
                    'id' => $data['procurement_id'] ?? '',
                    'title' => $data['procurement_title'] ?? '',
                    'action' => $actionLabel,
                    'details' => $data['details'] ?? '',
                    'raw_event_type' => $data['event_type'] ?? '',
                    'stage' => $data['stage_identifier'] ?? '',
                    'date' => $data['timestamp'] ?? now()->toIso8601String(),
                    'user' => \App\Models\User::where('blockchain_address', $data['user_address'] ?? '')->first()?->name ?? 'Unknown',
                    'timestamp' => strtotime($data['timestamp'] ?? 'now'),
                ];
            })
            ->filter(function ($item) {
                return !empty($item['id']) && !empty($item['title']);
            })
            ->sortByDesc('timestamp')
            ->take(8)
            ->values()
            ->toArray();
    }

    private function getPriorityActions($procurementsByKey)
    {
        $priorityActions = [];

        foreach ($procurementsByKey as $procurement) {
            $action = $this->services->getStageTransitionService()->getPriorityAction(
                $procurement['stage'],
                $procurement['status'],
                $procurement['id'],
                $procurement['title']
            );

            if ($action !== null) {
                $priorityActions[] = $action;
            }
        }

        return $priorityActions;
    }

    private function getTotalDocuments($procurementsByKey)
    {
        $totalDocuments = 0;
        foreach ($procurementsByKey as $procurement) {
            $streamKey = $this->services->getStreamKeyService()->generate($procurement['id'], $procurement['title']);
            $documents = $this->services->getMultiChain()->listStreamKeyItems(StreamEnums::DOCUMENTS->value, $streamKey);
            $totalDocuments += count($documents);
        }

        return $totalDocuments;
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

    public function indexProcurementsList()
    {
        try {
            $procurements = $this->services->getProcurementHandler()->getProcurementsList();

            return Inertia::render('procurements/procurements-list', [
                'procurements' => $procurements,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to retrieve procurements:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Inertia::render('procurements/procurements-list', [
                'procurements' => [],
                'error' => 'Failed to retrieve procurements: '.$e->getMessage(),
            ]);
        }
    }

    public function showProcurement($procurementId)
    {
        try {
            $procurement = $this->services->getProcurementHandler()->getProcurementDetails($procurementId);

            if (! $procurement) {
                return Inertia::render('procurements/show', ['message' => 'Procurement not found']);
            }

            return Inertia::render('procurements/show', [
                'procurement' => $procurement,
                'now' => now()->toIso8601String(),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to retrieve procurement:', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Inertia::render('procurements/show', [
                'error' => 'Failed to retrieve procurement: '.$e->getMessage(),
            ]);
        }
    }
}
