<?php

namespace App\Http\Controllers;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Enums\StreamEnums;
use App\Handlers\ProcurementViewHandler;
use App\Services\MultichainService;
use App\Services\StreamKeyService;
use Exception;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class BacSecretariatController extends BaseController
{
    private $multiChain;

    private $streamKeyService;

    private $procurementHandler;

    public function __construct(MultichainService $multiChain, StreamKeyService $streamKeyService, ProcurementViewHandler $procurementHandler)
    {
        $this->multiChain = $multiChain;
        $this->streamKeyService = $streamKeyService;
        $this->procurementHandler = $procurementHandler;
        $this->middleware('auth');
        $this->middleware('role:bac_secretariat');
    }

    public function index()
    {
        try {
            $allStates = $this->multiChain->listStreamItems(StreamEnums::STATUS->value, true, 1000, -1000);

            // Handle null response from multichain
            if ($allStates === null) {
                Log::warning('Multichain returned null for stream items');
                $allStates = [];
            }

            $procurementsByKey = $this->getProcurementsByKey($allStates);

            $recentProcurements = $this->getRecentProcurements($procurementsByKey);
            $ongoingProjects = $procurementsByKey->filter(function ($item) {
                return $item['phase'] !== 'Monitoring' ||
                    ($item['phase'] === 'Monitoring' && $item['state'] !== 'Completed');
            })->count();

            $recentActivities = $this->getRecentActivities();
            $priorityActions = $this->getPriorityActions($procurementsByKey);
            $totalDocuments = $this->getTotalDocuments($procurementsByKey);
            $completedBiddings = $this->countCompletedBiddings($procurementsByKey);

            $stats = [
                'ongoingProjects' => $ongoingProjects,
                'pendingActions' => count($priorityActions),
                'completedBiddings' => $completedBiddings,
                'totalDocuments' => $totalDocuments,
            ];

            return Inertia::render('bac-secretariat/dashboard', [
                'recentProcurements' => $recentProcurements,
                'recentActivities' => $recentActivities,
                'priorityActions' => array_slice($priorityActions, 0, 3),
                'stats' => $stats,
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
                'stats' => [
                    'ongoingProjects' => 0,
                    'pendingActions' => 0,
                    'completedBiddings' => 0,
                    'totalDocuments' => 0,
                ],
                'error' => 'Failed to retrieve dashboard data: '.$e->getMessage(),
            ]);
        }
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
                    'state' => $data['current_state'] ?? '',
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
                    'status' => $item['status'],
                ];
            })
            ->toArray();
    }

    private function getRecentActivities()
    {
        $allEvents = $this->multiChain->listStreamItems(StreamEnums::EVENTS->value, true, 300, -300);

        return collect($allEvents)
            ->map(function ($item) {
                $data = $item['data'];
                $actionLabel = $this->formatActionLabel($data['event_type'] ?? '', $data['details'] ?? '');

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
                return ! empty($item['id']) && ! empty($item['title']);
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
            $id = $procurement['id'];
            $title = $procurement['title'];
            $stage = $procurement['phase'];
            $status = $procurement['state'];

            if (
                $stage === StageEnums::PROCUREMENT_INITIATION->getDisplayName() &&
                $status === StatusEnums::PROCUREMENT_SUBMITTED->getDisplayName()
            ) {
                $priorityActions[] = [
                    'id' => $id,
                    'title' => $title,
                    'action' => 'Continue Procurement Processing',
                    'route' => '/bac-secretariat/procurements-list',
                ];
            } elseif (
                $stage === StageEnums::PRE_PROCUREMENT_CONFERENCE->getDisplayName() &&
                $status === StatusEnums::PRE_PROCUREMENT_CONFERENCE_HELD->getDisplayName()
            ) {
                $priorityActions[] = [
                    'id' => $id,
                    'title' => $title,
                    'action' => 'Upload Pre-Procurement Conference Documents',
                    'route' => "/bac-secretariat/pre-procurement-upload/{$id}",
                ];
            } elseif (
                $stage === StageEnums::BIDDING_DOCUMENTS->getDisplayName() &&
                ($status === StatusEnums::PRE_PROCUREMENT_CONFERENCE_COMPLETED->getDisplayName() ||
                    $status === StatusEnums::PRE_PROCUREMENT_CONFERENCE_SKIPPED->getDisplayName())
            ) {
                $priorityActions[] = [
                    'id' => $id,
                    'title' => $title,
                    'action' => 'Upload Bidding Documents',
                    'route' => "/bac-secretariat/bid-invitation-upload/{$id}",
                ];
            } elseif (
                $stage === StageEnums::BID_OPENING->getDisplayName() &&
                $status === StatusEnums::BIDDING_DOCUMENTS_PUBLISHED->getDisplayName()
            ) {
                $priorityActions[] = [
                    'id' => $id,
                    'title' => $title,
                    'action' => 'Upload Bid Opening Documents',
                    'route' => "/bac-secretariat/bid-submission-upload/{$id}",
                ];
            } elseif (
                $stage === StageEnums::BID_EVALUATION->getDisplayName() &&
                $status === StatusEnums::BIDS_OPENED->getDisplayName()
            ) {
                $priorityActions[] = [
                    'id' => $id,
                    'title' => $title,
                    'action' => 'Upload Bid Evaluation Documents',
                    'route' => "/bac-secretariat/bid-evaluation-upload/{$id}",
                ];
            } elseif (
                $stage === StageEnums::POST_QUALIFICATION->getDisplayName() &&
                $status === StatusEnums::BIDS_EVALUATED->getDisplayName()
            ) {
                $priorityActions[] = [
                    'id' => $id,
                    'title' => $title,
                    'action' => 'Upload Post-Qualification Documents',
                    'route' => "/bac-secretariat/post-qualification-upload/{$id}",
                ];
            } elseif (
                $stage === StageEnums::BAC_RESOLUTION->getDisplayName() &&
                $status === StatusEnums::POST_QUALIFICATION_VERIFIED->getDisplayName()
            ) {
                $priorityActions[] = [
                    'id' => $id,
                    'title' => $title,
                    'action' => 'Record BAC Resolution Documents',
                    'route' => "/bac-secretariat/bac-resolution-upload/{$id}",
                ];
            } elseif (
                $stage === StageEnums::NOTICE_OF_AWARD->getDisplayName() &&
                $status === StatusEnums::RESOLUTION_RECORDED->getDisplayName()
            ) {
                $priorityActions[] = [
                    'id' => $id,
                    'title' => $title,
                    'action' => 'Upload Notice of Award Documents',
                    'route' => "/bac-secretariat/noa-upload/{$id}",
                ];
            } elseif (
                $stage === StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO->getDisplayName() &&
                $status === StatusEnums::AWARDED->getDisplayName()
            ) {
                $priorityActions[] = [
                    'id' => $id,
                    'title' => $title,
                    'action' => 'Upload Performance Bond, Contract, and PO Documents',
                    'route' => "/bac-secretariat/performance-bond-upload/{$id}",
                ];
            } elseif (
                $stage === StageEnums::NOTICE_TO_PROCEED->getDisplayName() &&
                $status === StatusEnums::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED->getDisplayName()
            ) {
                $priorityActions[] = [
                    'id' => $id,
                    'title' => $title,
                    'action' => 'Upload Notice to Proceed Documents',
                    'route' => "/bac-secretariat/ntp-upload/{$id}",
                ];
            } elseif (
                $stage === StageEnums::MONITORING->getDisplayName() &&
                $status !== StatusEnums::COMPLETED->getDisplayName()
            ) {
                $priorityActions[] = [
                    'id' => $id,
                    'title' => $title,
                    'action' => 'Mark Procurement as Complete',
                    'route' => '/bac-secretariat/procurements-list',
                ];
            }
        }

        return $priorityActions;
    }

    private function getTotalDocuments($procurementsByKey)
    {
        $totalDocuments = 0;
        foreach ($procurementsByKey as $procurement) {
            $streamKey = $this->streamKeyService->generate($procurement['id'], $procurement['title']);
            $documents = $this->multiChain->listStreamKeyItems(StreamEnums::DOCUMENTS->value, $streamKey);
            $totalDocuments += count($documents);
        }

        return $totalDocuments;
    }

    private function countCompletedBiddings($procurementsByKey)
    {
        return $procurementsByKey->filter(function ($item) {
            return in_array($item['phase'], [
                'Notice Of Award',
                'Performance Bond',
                'Contract And PO',
                'Notice To Proceed',
                'Monitoring',
                'Completed',
            ]);
        })->count();
    }

    /**
     * Convert raw event types to more readable action labels
     *
     * @param  string  $eventType  The raw event type
     * @param  string  $details  Additional details about the event
     * @return string Formatted action label
     */
    private function formatActionLabel(string $eventType, string $details): string
    {
        switch (strtolower($eventType)) {
            case 'document_upload':
                return 'Uploaded Documents';
            case 'phase_transition':
                return 'Phase Transition';
            case 'decision':
                if (strpos(strtolower($details), 'pre-procurement') !== false) {
                    return 'Pre-Procurement Decision';
                }

                return 'Decision Made';
            case 'publication':
                return 'Published Documents';
            case 'procurement completed':
                return 'Completed Procurement';
            default:
                // Try to format the raw event type for better display
                $words = explode('_', $eventType);
                $formatted = array_map('ucfirst', $words);

                return implode(' ', $formatted);
        }
    }

    public function indexProcurementsList()
    {
        try {
            $procurements = $this->procurementHandler->getProcurementsList();

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
            $procurement = $this->procurementHandler->getProcurementDetails($procurementId);

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
