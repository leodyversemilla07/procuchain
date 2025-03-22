<?php

namespace App\Http\Controllers;

use App\Services\MultichainService;
use Exception;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class BacSecretariatController extends BaseController
{
    private $multiChain;

    private const STREAM_DOCUMENTS = 'procurement.documents';

    private const STREAM_STATE = 'procurement.state';

    private const STREAM_EVENTS = 'procurement.events';

    public function __construct(MultichainService $multiChain)
    {
        $this->multiChain = $multiChain;
        $this->middleware('auth');
        $this->middleware('role:bac_secretariat');
    }

    public function index()
    {
        try {
            // Fetch all procurement states from the blockchain
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            // Process and group procurements by ID
            $procurementsByKey = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];
                    return [
                        'id' => $data['procurement_id'] ?? '',
                        'title' => $data['procurement_title'] ?? '',
                        'phase' => $data['phase_identifier'] ?? '',
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

            // Get the most recent procurements
            $recentProcurements = $procurementsByKey->sortByDesc('timestamp')
                ->take(10)
                ->values()
                ->map(function ($item) {
                    return [
                        'id' => $item['id'],
                        'title' => $item['title'],
                        'phase' => $item['phase'],
                        'state' => $item['state']
                    ];
                })
                ->toArray();

            // Calculate statistics
            $ongoingProjects = $procurementsByKey->filter(function ($item) {
                return $item['phase'] !== 'Monitoring' ||
                    ($item['phase'] === 'Monitoring' && $item['state'] !== 'Completed');
            })->count();

            // Fetch recent activities from events stream - improved version
            $allEvents = $this->multiChain->listStreamItems(self::STREAM_EVENTS, true, 300, -300);
            $recentActivities = collect($allEvents)
                ->map(function ($item) {
                    $data = $item['data'];
                    
                    // Format the action label for better readability
                    $actionLabel = $this->formatActionLabel($data['event_type'] ?? '', $data['details'] ?? '');
                    
                    return [
                        'id' => $data['procurement_id'] ?? '',
                        'title' => $data['procurement_title'] ?? '',
                        'action' => $actionLabel,
                        'details' => $data['details'] ?? '',
                        'raw_event_type' => $data['event_type'] ?? '',
                        'phase' => $data['phase_identifier'] ?? '',
                        'date' => $data['timestamp'] ?? now()->toIso8601String(),
                        'user' => \App\Models\User::where('blockchain_address', $data['user_address'] ?? '')->first()?->name ?? 'Unknown',
                        'timestamp' => strtotime($data['timestamp'] ?? 'now'),
                    ];
                })
                ->filter(function ($item) {
                    // Ensure we have valid data
                    return !empty($item['id']) && !empty($item['title']);
                })
                ->sortByDesc('timestamp')
                ->take(8)  // Show more recent activities
                ->values()
                ->toArray();

            // Identify priority actions based on procurement states
            $priorityActions = [];
            foreach ($procurementsByKey as $procurement) {
                $id = $procurement['id'];
                $title = $procurement['title'];
                $phase = $procurement['phase'];
                $state = $procurement['state'];

                if ($phase === 'PR Initiation' && $state === 'PR Submitted') {
                    $priorityActions[] = [
                        'id' => $id,
                        'title' => $title,
                        'action' => 'Continue PR Processing',
                        'route' => "/bac-secretariat/procurements-list",
                    ];
                } elseif ($phase === 'Pre-Procurement' && $state === 'Pre-Procurement Conference Held') {
                    $priorityActions[] = [
                        'id' => $id,
                        'title' => $title,
                        'action' => 'Upload Pre-Procurement Documents',
                        'route' => "/bac-secretariat/pre-procurement-upload/{$id}",
                    ];
                } elseif ($phase === 'Bid Invitation' && ($state === 'Pre-Procurement Completed' || $state === 'Pre-Procurement Skipped')) {
                    $priorityActions[] = [
                        'id' => $id,
                        'title' => $title,
                        'action' => 'Upload Bid Invitation',
                        'route' => "/bac-secretariat/bid-invitation-upload/{$id}",
                    ];
                } elseif ($phase === 'Bid Opening' && $state === 'Bid Invitation Published') {
                    $priorityActions[] = [
                        'id' => $id,
                        'title' => $title,
                        'action' => 'Upload Bid Submission',
                        'route' => "/bac-secretariat/bid-submission-upload/{$id}",
                    ];
                } elseif ($phase === 'Bid Evaluation' && $state === 'Bids Opened') {
                    $priorityActions[] = [
                        'id' => $id,
                        'title' => $title,
                        'action' => 'Upload Bid Evaluation',
                        'route' => "/bac-secretariat/bid-evaluation-upload/{$id}",
                    ];
                } elseif ($phase === 'Post-Qualification' && $state === 'Bids Evaluated') {
                    $priorityActions[] = [
                        'id' => $id,
                        'title' => $title,
                        'action' => 'Upload Post-Qualification Documents',
                        'route' => "/bac-secretariat/post-qualification-upload/{$id}",
                    ];
                } elseif ($phase === 'BAC Resolution' && $state === 'Post-Qualification Verified') {
                    $priorityActions[] = [
                        'id' => $id,
                        'title' => $title,
                        'action' => 'Record BAC Resolution',
                        'route' => "/bac-secretariat/bac-resolution-upload/{$id}",
                    ];
                } elseif ($phase === 'Notice Of Award' && $state === 'Resolution Recorded') {
                    $priorityActions[] = [
                        'id' => $id,
                        'title' => $title,
                        'action' => 'Upload Notice of Award',
                        'route' => "/bac-secretariat/noa-upload/{$id}",
                    ];
                } elseif ($phase === 'Performance Bond' && $state === 'Awarded') {
                    $priorityActions[] = [
                        'id' => $id,
                        'title' => $title,
                        'action' => 'Upload Performance Bond',
                        'route' => "/bac-secretariat/performance-bond-upload/{$id}",
                    ];
                } elseif ($phase === 'Contract And PO' && $state === 'Performance Bond Recorded') {
                    $priorityActions[] = [
                        'id' => $id,
                        'title' => $title,
                        'action' => 'Upload Contract and PO',
                        'route' => "/bac-secretariat/contract-po-upload/{$id}",
                    ];
                } elseif ($phase === 'Notice To Proceed' && $state === 'Contract And PO Recorded') {
                    $priorityActions[] = [
                        'id' => $id,
                        'title' => $title,
                        'action' => 'Upload Notice to Proceed',
                        'route' => "/bac-secretariat/ntp-upload/{$id}",
                    ];
                } elseif ($phase === 'Monitoring' && $state !== 'Completed') {
                    $priorityActions[] = [
                        'id' => $id,
                        'title' => $title,
                        'action' => 'Mark Procurement as Complete',
                        'route' => "/bac-secretariat/procurements-list",
                    ];
                }
            }

            // Count total documents
            $totalDocuments = 0;
            foreach ($procurementsByKey as $procurement) {
                $id = $procurement['id'];
                $title = $procurement['title'];
                $streamKey = $this->getStreamKey($id, $title);
                $documents = $this->multiChain->listStreamKeyItems(self::STREAM_DOCUMENTS, $streamKey);
                $totalDocuments += count($documents);
            }

            // Count completed biddings
            $completedBiddings = $procurementsByKey->filter(function ($item) {
                return in_array($item['phase'], [
                    'Notice Of Award',
                    'Performance Bond',
                    'Contract And PO',
                    'Notice To Proceed',
                    'Monitoring',
                    'Completed'
                ]);
            })->count();

            // Prepare stats
            $stats = [
                'ongoingProjects' => $ongoingProjects,
                'pendingActions' => count($priorityActions),
                'completedBiddings' => $completedBiddings,
                'totalDocuments' => $totalDocuments
            ];

            return Inertia::render('bac-secretariat/dashboard', [
                'recentProcurements' => $recentProcurements,
                'recentActivities' => $recentActivities,
                'priorityActions' => array_slice($priorityActions, 0, 3),
                'stats' => $stats
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
                    'totalDocuments' => 0
                ],
                'error' => 'Failed to retrieve dashboard data: ' . $e->getMessage()
            ]);
        }
    }

    private function getStreamKey($procurementId, $procurementTitle)
    {
        return $procurementId . '-' . preg_replace('/[^a-zA-Z0-9-]/', '-', $procurementTitle);
    }

    public function indexProcurementsList()
    {
        try {
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            $procurementsByKey = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];

                    return [
                        'id' => $data['procurement_id'] ?? '',
                        'title' => $data['procurement_title'] ?? '',
                        'phase_identifier' => $data['phase_identifier'] ?? '',
                        'current_state' => $data['current_state'] ?? '',
                        'user_address' => $data['user_address'] ?? '',
                        'timestamp' => $data['timestamp'] ?? '',
                        'last_updated' => date('Y-m-d', strtotime($data['timestamp'] ?? 'now')),
                        'procurement_id' => $data['procurement_id'] ?? '',
                        'procurement_title' => $data['procurement_title'] ?? '',
                        'document_count' => 0,
                    ];
                })
                ->groupBy('id')
                ->map(function ($group) {
                    return $group->sortByDesc('timestamp')->first();
                })
                ->values();

            $sortedProcurements = $procurementsByKey->sortByDesc('timestamp')->values()->toArray();

            foreach ($sortedProcurements as $key => $procurement) {
                $procId = $procurement['id'];
                $procTitle = $procurement['title'];
                $streamKey = $this->getStreamKey($procId, $procTitle);

                $documents = $this->multiChain->listStreamKeyItems(self::STREAM_DOCUMENTS, $streamKey);
                $sortedProcurements[$key]['document_count'] = count($documents);
            }

            return Inertia::render('bac-secretariat/procurements-list', [
                'procurements' => $sortedProcurements,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to retrieve procurements:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Inertia::render('bac-secretariat/procurements-list', [
                'procurements' => [],
                'error' => 'Failed to retrieve procurements: ' . $e->getMessage(),
            ]);
        }
    }

    public function showPrInitiation()
    {
        return Inertia::render('bac-secretariat/procurement-phase/pr-initiation');
    }

    public function showProcurement($procurementId)
    {
        try {
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            $procurementStates = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];

                    return [
                        'item' => $item,
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? '',
                        'timestamp' => $data['timestamp'] ?? '',
                        'phase_identifier' => $data['phase_identifier'] ?? '',
                        'current_state' => $data['current_state'] ?? '',
                    ];
                })
                ->filter(function ($mappedItem) use ($procurementId) {
                    return $mappedItem['procurementId'] === $procurementId;
                })
                ->sortByDesc('timestamp');

            if ($procurementStates->isEmpty()) {
                return Inertia::render('bac-secretariat/show', ['message' => 'Procurement not found']);
            }

            $latestState = $procurementStates->first();
            $procurementTitle = $latestState['data']['procurement_title'] ?? '';
            $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

            $documents = $this->multiChain->listStreamKeyItems(self::STREAM_DOCUMENTS, $streamKey, 1000);

            Log::info('Found ' . count($documents) . " documents for procurement $procurementId");

            $potentialPrDocs = collect($documents)->filter(function ($doc) {
                $data = $doc['data'];
                $docType = strtolower($data['document_type'] ?? '');
                $fileKey = strtolower($data['file_key'] ?? '');

                return
                    strpos($docType, 'purchase') !== false ||
                    strpos($docType, 'pr') !== false ||
                    strpos($fileKey, 'prinitiation') !== false ||
                    strpos($fileKey, 'purchase') !== false;
            });

            if ($potentialPrDocs->count() > 0) {
                Log::info("Found {$potentialPrDocs->count()} potential PR documents", [
                    'first_doc' => $potentialPrDocs->first()['data'],
                ]);
            }

            $parsedDocuments = collect($documents)->map(function ($doc) {
                $data = $doc['data'];

                $spaces_url = '';
                if (isset($data['file_key'])) {
                    $spaces_url = Storage::disk('spaces')->temporaryUrl($data['file_key'], now()->addMinutes(30));
                }

                $phase_identifier = $data['phase_identifier'] ?? '';
                if (empty($phase_identifier)) {
                    $docType = strtolower($data['document_type'] ?? '');
                    $fileKey = strtolower($data['file_key'] ?? '');

                    if (
                        strpos($docType, 'purchase') !== false ||
                        strpos($docType, 'pr') !== false ||
                        strpos($fileKey, 'prinitiation') !== false ||
                        strpos($fileKey, 'pr/') !== false ||
                        strpos($fileKey, '/pr/') !== false ||
                        strpos($fileKey, 'purchase') !== false
                    ) {
                        $phase_identifier = 'PR Initiation';
                        Log::info('Auto-tagged document as PR Initiation', ['doc_type' => $data['document_type'], 'file_key' => $data['file_key']]);
                    }
                }

                return [
                    'procurement_id' => $data['procurement_id'] ?? '',
                    'procurement_title' => $data['procurement_title'] ?? '',
                    'user_address' => $data['user_address'] ?? '',
                    'timestamp' => $data['timestamp'] ?? '',
                    'phase_identifier' => $phase_identifier,
                    'document_index' => $data['document_index'] ?? 0,
                    'document_type' => $data['document_type'] ?? '',
                    'hash' => $data['hash'] ?? '',
                    'file_key' => $data['file_key'] ?? '',
                    'file_size' => $data['file_size'] ?? 0,
                    'phase_metadata' => $data['phase_metadata'] ?? [],
                    'spaces_url' => $spaces_url,
                    'formatted_date' => isset($data['timestamp']) ?
                        date('M d, Y h:i A', strtotime($data['timestamp'])) : '',
                ];
            });

            $documentsByPhase = $parsedDocuments->groupBy('phase_identifier')->map(function ($docs) {
                return $docs->sortByDesc('timestamp')->values();
            })->toArray();

            $procurementPhases = [
                'PR Initiation',
                'Pre-Procurement',
                'Bid Invitation',
                'Bid Opening',
                'Bid Evaluation',
                'Post-Qualification',
                'BAC Resolution',
                'Notice Of Award',
                'Performance Bond',
                'Contract And PO',
                'Notice To Proceed',
                'Monitoring',
            ];

            if (!isset($documentsByPhase['PR Initiation'])) {
                $prDocs = $parsedDocuments->filter(function ($doc) {
                    $docType = strtolower($doc['document_type'] ?? '');
                    $fileKey = strtolower($doc['file_key'] ?? '');

                    return
                        strpos($docType, 'purchase') !== false ||
                        strpos($docType, 'pr') !== false ||
                        strpos($docType, 'aip') !== false ||
                        strpos($docType, 'certificate') !== false ||
                        strpos($fileKey, 'prinitiation') !== false ||
                        strpos($fileKey, '/pr/') !== false ||
                        strpos($fileKey, 'purchase') !== false;
                })->values()->toArray();

                if (!empty($prDocs)) {
                    $documentsByPhase['PR Initiation'] = $prDocs;
                    Log::info('Added ' . count($prDocs) . ' PR Initiation documents that were not properly categorized');
                }
            }

            foreach ($procurementPhases as $phase) {
                if (!isset($documentsByPhase[$phase])) {
                    $documentsByPhase[$phase] = [];
                }
            }

            $events = $this->multiChain->listStreamKeyItems(self::STREAM_EVENTS, $streamKey);
            $parsedEvents = collect($events)->map(function ($event) {
                $data = $event['data'];

                return [
                    'procurement_id' => $data['procurement_id'] ?? '',
                    'procurement_title' => $data['procurement_title'] ?? '',
                    'user_address' => $data['user_address'] ?? '',
                    'timestamp' => $data['timestamp'] ?? '',
                    'phase_identifier' => $data['phase_identifier'] ?? '',
                    'event_type' => $data['event_type'] ?? '',
                    'details' => $data['details'] ?? '',
                    'category' => $data['category'] ?? '',
                    'severity' => $data['severity'] ?? '',
                    'document_count' => $data['document_count'] ?? 0,
                    'formatted_date' => isset($data['timestamp']) ?
                        date('M d, Y h:i A', strtotime($data['timestamp'])) : '',
                ];
            })->sortByDesc('timestamp')->values()->toArray();

            $eventsByPhase = collect($parsedEvents)->groupBy('phase_identifier')->map(function ($events) {
                return $events->values();
            })->toArray();

            $phaseHistory = $procurementStates->groupBy('phase_identifier')
                ->map(function ($phaseStates) {
                    return $phaseStates->sortBy('timestamp')->values();
                })->toArray();

            $phaseSummary = [];
            foreach ($procurementPhases as $phase) {
                $phaseStates = collect($phaseHistory[$phase] ?? []);
                $phaseDocuments = isset($documentsByPhase[$phase]) ? $documentsByPhase[$phase] : [];
                $phaseEvents = isset($eventsByPhase[$phase]) ? $eventsByPhase[$phase] : [];

                $latestPhaseState = $phaseStates->isEmpty() ? null : $phaseStates->sortByDesc('timestamp')->first();

                $isCompleted = !empty($latestPhaseState);
                $isSkipped = $isCompleted && strpos($latestPhaseState['current_state'], 'Skipped') !== false;

                $status = 'Not Started';
                if ($isSkipped) {
                    $status = 'Skipped';
                } elseif ($isCompleted) {
                    if ($phase === $latestState['phase_identifier']) {
                        $status = 'Current';
                    } else {
                        $status = 'Completed';
                    }
                } elseif (array_search($phase, $procurementPhases) < array_search($latestState['phase_identifier'], $procurementPhases)) {
                    $status = 'Completed';
                }

                $startDate = $phaseStates->isEmpty() ? null :
                    date('Y-m-d H:i:s', strtotime($phaseStates->first()['timestamp'] ?? 'now'));
                $endDate = $phaseStates->isEmpty() ? null :
                    date('Y-m-d H:i:s', strtotime($phaseStates->sortByDesc('timestamp')->first()['timestamp'] ?? 'now'));

                $phaseSummary[$phase] = [
                    'name' => $phase,
                    'status' => $status,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'document_count' => count($phaseDocuments),
                    'event_count' => count($phaseEvents),
                    'latest_state' => $latestPhaseState ? $latestPhaseState['current_state'] : null,
                ];
            }

            $procurementState = [
                'procurement_id' => $latestState['data']['procurement_id'] ?? '',
                'procurement_title' => $latestState['data']['procurement_title'] ?? '',
                'user_address' => $latestState['data']['user_address'] ?? '',
                'timestamp' => $latestState['data']['timestamp'] ?? '',
                'phase_identifier' => $latestState['data']['phase_identifier'] ?? '',
                'current_state' => $latestState['data']['current_state'] ?? '',
                'formatted_date' => isset($latestState['data']['timestamp']) ?
                    date('M d, Y h:i A', strtotime($latestState['data']['timestamp'])) : '',
            ];

            $timeline = $procurementStates->map(function ($state) {
                return [
                    'phase' => $state['phase_identifier'],
                    'state' => $state['current_state'],
                    'timestamp' => $state['timestamp'],
                    'formatted_date' => isset($state['timestamp']) ?
                        date('M d, Y h:i A', strtotime($state['timestamp'])) : '',
                ];
            })->values()->toArray();

            $procurement = [
                'id' => $procurementId,
                'title' => $procurementTitle,
                'documents' => $parsedDocuments->toArray(),
                'documents_by_phase' => $documentsByPhase,
                'state' => $procurementState,
                'events' => $parsedEvents,
                'events_by_phase' => $eventsByPhase,
                'phase_summary' => $phaseSummary,
                'phase_history' => $phaseHistory,
                'timeline' => $timeline,
                'current_phase' => $latestState['phase_identifier'],
                'phases' => $procurementPhases,
            ];

            Log::info('Documents by phase in show method:', [
                'phase_keys' => array_keys($documentsByPhase),
                'pr_docs_count' => isset($documentsByPhase['PR Initiation']) ? count($documentsByPhase['PR Initiation']) : 0,
            ]);

            return Inertia::render('bac-secretariat/show', [
                'procurement' => $procurement,
                'now' => now()->toIso8601String(),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to retrieve procurement:', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Inertia::render('bac-secretariat/show', [
                'error' => 'Failed to retrieve procurement: ' . $e->getMessage(),
            ]);
        }
    }

    public function showPreProcurementUpload($id)
    {
        try {
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            $procurementStates = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];

                    return [
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? '',
                    ];
                })
                ->filter(function ($mappedItem) use ($id) {
                    return $mappedItem['procurementId'] === $id;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['timestamp'] ?? '';
                });

            if ($procurementStates->isEmpty()) {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'Procurement not found');
            }

            $latestState = $procurementStates->first();

            if ($latestState['data']['current_state'] !== 'Pre-Procurement Conference Held') {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'This procurement is not eligible for pre-procurement document upload');
            }

            $procurement = [
                'id' => $id,
                'title' => $latestState['data']['procurement_title'] ?? 'Unknown',
                'current_state' => $latestState['data']['current_state'] ?? '',
            ];

            return Inertia::render('bac-secretariat/procurement-phase/pre-procurement-upload', [
                'procurement' => $procurement,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to load pre-procurement upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading pre-procurement upload form: ' . $e->getMessage());
        }
    }

    public function showBidInvitationUpload($id)
    {
        try {
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            $procurementStates = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];

                    return [
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? '',
                    ];
                })
                ->filter(function ($mappedItem) use ($id) {
                    return $mappedItem['procurementId'] === $id;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['timestamp'] ?? '';
                });

            if ($procurementStates->isEmpty()) {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'Procurement not found');
            }

            $latestState = $procurementStates->first();

            $phaseIdentifier = $latestState['data']['phase_identifier'] ?? '';
            $currentState = $latestState['data']['current_state'] ?? '';

            if (
                $phaseIdentifier !== 'Bid Invitation' ||
                ($currentState !== 'Pre-Procurement Skipped' && $currentState !== 'Pre-Procurement Completed')
            ) {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'This procurement is not eligible for bid invitation document upload');
            }

            $procurement = [
                'id' => $id,
                'title' => $latestState['data']['procurement_title'] ?? 'Unknown',
                'current_state' => $latestState['data']['current_state'] ?? '',
                'phase_identifier' => $latestState['data']['phase_identifier'] ?? '',
            ];

            return Inertia::render('bac-secretariat/procurement-phase/bid-invitation-upload', [
                'procurement' => $procurement,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to load bid invitation upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading bid invitation upload form: ' . $e->getMessage());
        }
    }

    public function showBidSubmissionUpload($id)
    {
        try {
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            $procurementStates = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];

                    return [
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? '',
                    ];
                })
                ->filter(function ($mappedItem) use ($id) {
                    return $mappedItem['procurementId'] === $id;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['timestamp'] ?? '';
                });

            if ($procurementStates->isEmpty()) {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'Procurement not found');
            }

            $latestState = $procurementStates->first();

            $phaseIdentifier = $latestState['data']['phase_identifier'] ?? '';
            $currentState = $latestState['data']['current_state'] ?? '';

            if ($phaseIdentifier !== 'Bid Opening' || $currentState !== 'Bid Invitation Published') {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'This procurement is not eligible for bid submission and opening');
            }

            $procurement = [
                'id' => $id,
                'title' => $latestState['data']['procurement_title'] ?? 'Unknown',
                'current_state' => $latestState['data']['current_state'] ?? '',
                'phase_identifier' => $latestState['data']['phase_identifier'] ?? '',
            ];

            return Inertia::render('bac-secretariat/procurement-phase/bid-submission-upload', [
                'procurement' => $procurement,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to load bid submission upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading bid submission upload form: ' . $e->getMessage());
        }
    }

    public function showBidEvaluationUpload($id)
    {
        try {
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            $procurementStates = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];

                    return [
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? '',
                    ];
                })
                ->filter(function ($mappedItem) use ($id) {
                    return $mappedItem['procurementId'] === $id;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['timestamp'] ?? '';
                });

            if ($procurementStates->isEmpty()) {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'Procurement not found');
            }

            $latestState = $procurementStates->first();

            $phaseIdentifier = $latestState['data']['phase_identifier'] ?? '';
            $currentState = $latestState['data']['current_state'] ?? '';

            if ($phaseIdentifier !== 'Bid Evaluation' || $currentState !== 'Bids Opened') {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'This procurement is not eligible for bid evaluation upload');
            }

            $procurement = [
                'id' => $id,
                'title' => $latestState['data']['procurement_title'] ?? 'Unknown',
                'current_state' => $latestState['data']['current_state'] ?? '',
                'phase_identifier' => $latestState['data']['phase_identifier'] ?? '',
            ];

            return Inertia::render('bac-secretariat/procurement-phase/bid-evaluation-upload', [
                'procurement' => $procurement,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to load bid evaluation upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading bid evaluation upload form: ' . $e->getMessage());
        }
    }

    public function showPostQualificationUpload($id)
    {
        try {
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            $procurementStates = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];

                    return [
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? '',
                    ];
                })
                ->filter(function ($mappedItem) use ($id) {
                    return $mappedItem['procurementId'] === $id;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['timestamp'] ?? '';
                });

            if ($procurementStates->isEmpty()) {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'Procurement not found');
            }

            $latestState = $procurementStates->first();

            $phaseIdentifier = $latestState['data']['phase_identifier'] ?? '';
            $currentState = $latestState['data']['current_state'] ?? '';

            Log::info('Showing Post-Qualification upload form', [
                'procurement_id' => $id,
                'phase_identifier' => $phaseIdentifier,
                'current_state' => $currentState,
            ]);

            if ($phaseIdentifier !== 'Post-Qualification' || $currentState !== 'Bids Evaluated') {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'This procurement is not eligible for post-qualification document upload');
            }

            $procurement = [
                'id' => $id,
                'title' => $latestState['data']['procurement_title'] ?? 'Unknown',
                'current_state' => $latestState['data']['current_state'] ?? '',
                'phase_identifier' => $latestState['data']['phase_identifier'] ?? '',
            ];

            return Inertia::render('bac-secretariat/procurement-phase/post-qualification-upload', [
                'procurement' => $procurement,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to load post-qualification upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading post-qualification upload form: ' . $e->getMessage());
        }
    }

    public function showBacResolutionUpload($id)
    {
        try {
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            $procurementStates = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];

                    return [
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? '',
                    ];
                })
                ->filter(function ($mappedItem) use ($id) {
                    return $mappedItem['procurementId'] === $id;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['timestamp'] ?? '';
                });

            if ($procurementStates->isEmpty()) {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'Procurement not found');
            }

            $latestState = $procurementStates->first();

            $phaseIdentifier = $latestState['data']['phase_identifier'] ?? '';
            $currentState = $latestState['data']['current_state'] ?? '';

            Log::info('Showing BAC Resolution upload form', [
                'procurement_id' => $id,
                'phase_identifier' => $phaseIdentifier,
                'current_state' => $currentState,
            ]);

            if ($phaseIdentifier !== 'BAC Resolution' || $currentState !== 'Post-Qualification Verified') {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'This procurement is not eligible for BAC Resolution document upload');
            }

            $procurement = [
                'id' => $id,
                'title' => $latestState['data']['procurement_title'] ?? 'Unknown',
                'current_state' => $latestState['data']['current_state'] ?? '',
                'phase_identifier' => $latestState['data']['phase_identifier'] ?? '',
            ];

            return Inertia::render('bac-secretariat/procurement-phase/bac-resolution-upload', [
                'procurement' => $procurement,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to load BAC Resolution upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading BAC Resolution upload form: ' . $e->getMessage());
        }
    }

    public function showNoaUpload($id)
    {
        try {
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            $procurementStates = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];

                    return [
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? '',
                    ];
                })
                ->filter(function ($mappedItem) use ($id) {
                    return $mappedItem['procurementId'] === $id;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['timestamp'] ?? '';
                });

            if ($procurementStates->isEmpty()) {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'Procurement not found');
            }

            $latestState = $procurementStates->first();

            $phaseIdentifier = $latestState['data']['phase_identifier'] ?? '';
            $currentState = $latestState['data']['current_state'] ?? '';

            Log::info('Showing Notice of Award upload form', [
                'procurement_id' => $id,
                'phase_identifier' => $phaseIdentifier,
                'current_state' => $currentState,
            ]);

            if ($phaseIdentifier !== 'Notice Of Award' || $currentState !== 'Resolution Recorded') {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'This procurement is not eligible for Notice of Award document upload');
            }

            $procurement = [
                'id' => $id,
                'title' => $latestState['data']['procurement_title'] ?? 'Unknown',
                'current_state' => $latestState['data']['current_state'] ?? '',
                'phase_identifier' => $latestState['data']['phase_identifier'] ?? '',
            ];

            return Inertia::render('bac-secretariat/procurement-phase/noa-upload', [
                'procurement' => $procurement,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to load Notice of Award upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading Notice of Award upload form: ' . $e->getMessage());
        }
    }

    public function showPerformanceBondUpload($id)
    {
        try {
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            $procurementStates = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];

                    return [
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? '',
                    ];
                })
                ->filter(function ($mappedItem) use ($id) {
                    return $mappedItem['procurementId'] === $id;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['timestamp'] ?? '';
                });

            if ($procurementStates->isEmpty()) {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'Procurement not found');
            }

            $latestState = $procurementStates->first();

            $phaseIdentifier = $latestState['data']['phase_identifier'] ?? '';
            $currentState = $latestState['data']['current_state'] ?? '';

            Log::info('Showing Performance Bond upload form', [
                'procurement_id' => $id,
                'phase_identifier' => $phaseIdentifier,
                'current_state' => $currentState,
            ]);

            if ($phaseIdentifier !== 'Performance Bond' || $currentState !== 'Awarded') {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'This procurement is not eligible for Performance Bond document upload');
            }

            $procurement = [
                'id' => $id,
                'title' => $latestState['data']['procurement_title'] ?? 'Unknown',
                'current_state' => $latestState['data']['current_state'] ?? '',
                'phase_identifier' => $latestState['data']['phase_identifier'] ?? '',
            ];

            return Inertia::render('bac-secretariat/procurement-phase/performance-bond-upload', [
                'procurement' => $procurement,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to load Performance Bond upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading Performance Bond upload form: ' . $e->getMessage());
        }
    }

    public function showContractPOUpload($id)
    {
        try {
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            $procurementStates = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];

                    return [
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? '',
                    ];
                })
                ->filter(function ($mappedItem) use ($id) {
                    return $mappedItem['procurementId'] === $id;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['timestamp'] ?? '';
                });

            if ($procurementStates->isEmpty()) {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'Procurement not found');
            }

            $latestState = $procurementStates->first();

            $phaseIdentifier = $latestState['data']['phase_identifier'] ?? '';
            $currentState = $latestState['data']['current_state'] ?? '';

            Log::info('Showing Contract and PO upload form', [
                'procurement_id' => $id,
                'phase_identifier' => $phaseIdentifier,
                'current_state' => $currentState,
            ]);

            if ($phaseIdentifier !== 'Contract And PO' || $currentState !== 'Performance Bond Recorded') {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'This procurement is not eligible for Contract and PO document upload');
            }

            $procurement = [
                'id' => $id,
                'title' => $latestState['data']['procurement_title'] ?? 'Unknown',
                'current_state' => $latestState['data']['current_state'] ?? '',
                'phase_identifier' => $latestState['data']['phase_identifier'] ?? '',
            ];

            return Inertia::render('bac-secretariat/procurement-phase/contract-po-upload', [
                'procurement' => $procurement,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to load Contract and PO upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading Contract and PO upload form: ' . $e->getMessage());
        }
    }

    public function showNTPUpload($id)
    {
        try {
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            $procurementStates = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];

                    return [
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? '',
                    ];
                })
                ->filter(function ($mappedItem) use ($id) {
                    return $mappedItem['procurementId'] === $id;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['timestamp'] ?? '';
                });

            if ($procurementStates->isEmpty()) {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'Procurement not found');
            }

            $latestState = $procurementStates->first();

            $phaseIdentifier = $latestState['data']['phase_identifier'] ?? '';
            $currentState = $latestState['data']['current_state'] ?? '';

            Log::info('Showing Notice to Proceed upload form', [
                'procurement_id' => $id,
                'phase_identifier' => $phaseIdentifier,
                'current_state' => $currentState,
            ]);

            if ($phaseIdentifier !== 'Notice To Proceed' || $currentState !== 'Contract And PO Recorded') {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'This procurement is not eligible for Notice to Proceed document upload');
            }

            $procurement = [
                'id' => $id,
                'title' => $latestState['data']['procurement_title'] ?? 'Unknown',
                'current_state' => $latestState['data']['current_state'] ?? '',
                'phase_identifier' => $latestState['data']['phase_identifier'] ?? '',
            ];

            return Inertia::render('bac-secretariat/procurement-phase/ntp-upload', [
                'procurement' => $procurement,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to load Notice to Proceed upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading Notice to Proceed upload form: ' . $e->getMessage());
        }
    }

    public function showMonitoringUpload($id)
    {
        try {
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            $procurementStates = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];

                    return [
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? '',
                    ];
                })
                ->filter(function ($mappedItem) use ($id) {
                    return $mappedItem['procurementId'] === $id;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['timestamp'] ?? '';
                });

            if ($procurementStates->isEmpty()) {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'Procurement not found');
            }

            $latestState = $procurementStates->first();

            $phaseIdentifier = $latestState['data']['phase_identifier'] ?? '';

            Log::info('Showing Monitoring upload form', [
                'procurement_id' => $id,
                'phase_identifier' => $phaseIdentifier,
            ]);

            if ($phaseIdentifier !== 'Monitoring') {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'This procurement is not in the Monitoring phase');
            }

            $procurement = [
                'id' => $id,
                'title' => $latestState['data']['procurement_title'] ?? 'Unknown',
                'current_state' => $latestState['data']['current_state'] ?? '',
                'phase_identifier' => $latestState['data']['phase_identifier'] ?? '',
            ];

            return Inertia::render('bac-secretariat/procurement-phase/monitoring-upload', [
                'procurement' => $procurement,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to load Monitoring upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading Monitoring upload form: ' . $e->getMessage());
        }
    }

    public function showCompleteStatus($id)
    {
        try {
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            $procurementStates = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];

                    return [
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? '',
                    ];
                })
                ->filter(function ($mappedItem) use ($id) {
                    return $mappedItem['procurementId'] === $id;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['timestamp'] ?? '';
                });

            if ($procurementStates->isEmpty()) {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'Procurement not found');
            }

            $latestState = $procurementStates->first();

            $phaseIdentifier = $latestState['data']['phase_identifier'] ?? '';
            $currentState = $latestState['data']['current_state'] ?? '';

            Log::info('Showing Complete Status form', [
                'procurement_id' => $id,
                'phase_identifier' => $phaseIdentifier,
                'current_state' => $currentState,
            ]);

            if ($phaseIdentifier !== 'Monitoring') {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'This procurement is not eligible for completion');
            }

            $procurement = [
                'id' => $id,
                'title' => $latestState['data']['procurement_title'] ?? 'Unknown',
                'current_state' => $latestState['data']['current_state'] ?? '',
                'phase_identifier' => $latestState['data']['phase_identifier'] ?? '',
            ];

            return Inertia::render('bac-secretariat/procurement-phase/complete-status', [
                'procurement' => $procurement,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to load Complete Status form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading Complete Status form: ' . $e->getMessage());
        }
    }

    /**
     * Convert raw event types to more readable action labels
     * 
     * @param string $eventType The raw event type
     * @param string $details Additional details about the event
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
}
