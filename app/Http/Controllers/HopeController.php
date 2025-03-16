<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;
use App\Services\MultiChainService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class HopeController extends BaseController
{
    private $multiChain;

    private const STREAM_DOCUMENTS = 'procurement.documents';

    private const STREAM_STATE = 'procurement.state';

    private const STREAM_EVENTS = 'procurement.events';

    public function __construct(MultiChainService $multiChain)
    {
        $this->multiChain = $multiChain;
        $this->middleware('auth');
        $this->middleware('role:hope');
    }

    public function index()
    {
        return Inertia::render('hope/dashboard');
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
                        'lastUpdated' => date('Y-m-d', strtotime($data['timestamp'] ?? 'now')),
                        'procurement_id' => $data['procurement_id'] ?? '',
                        'procurement_title' => $data['procurement_title'] ?? '',
                        'documentCount' => 0
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
                $sortedProcurements[$key]['documentCount'] = count($documents);
            }

            return Inertia::render('hope/procurements-list', [
                'procurements' => $sortedProcurements
            ]);

        } catch (Exception $e) {
            Log::error('Failed to retrieve procurements:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Inertia::render('hope/procurements-list', [
                'procurements' => [],
                'error' => 'Failed to retrieve procurements: ' . $e->getMessage()
            ]);
        }
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

            Log::info("Found " . count($documents) . " documents for procurement $procurementId");

            $potentialPrDocs = collect($documents)->filter(function ($doc) {
                $data = $doc['data'];
                $docType = strtolower($data['document_type'] ?? '');
                $fileKey = strtolower($data['file_key'] ?? '');

                return (
                    strpos($docType, 'purchase') !== false ||
                    strpos($docType, 'pr') !== false ||
                    strpos($fileKey, 'prinitiation') !== false ||
                    strpos($fileKey, 'purchase') !== false
                );
            });

            if ($potentialPrDocs->count() > 0) {
                Log::info("Found {$potentialPrDocs->count()} potential PR documents", [
                    'first_doc' => $potentialPrDocs->first()['data']
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
                        Log::info("Auto-tagged document as PR Initiation", ['doc_type' => $data['document_type'], 'file_key' => $data['file_key']]);
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
                'Monitoring'
            ];

            if (!isset($documentsByPhase['PR Initiation'])) {
                $prDocs = $parsedDocuments->filter(function ($doc) {
                    $docType = strtolower($doc['document_type'] ?? '');
                    $fileKey = strtolower($doc['file_key'] ?? '');

                    return (
                        strpos($docType, 'purchase') !== false ||
                        strpos($docType, 'pr') !== false ||
                        strpos($docType, 'aip') !== false ||
                        strpos($docType, 'certificate') !== false ||
                        strpos($fileKey, 'prinitiation') !== false ||
                        strpos($fileKey, '/pr/') !== false ||
                        strpos($fileKey, 'purchase') !== false
                    );
                })->values()->toArray();

                if (!empty($prDocs)) {
                    $documentsByPhase['PR Initiation'] = $prDocs;
                    Log::info("Added " . count($prDocs) . " PR Initiation documents that were not properly categorized");
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
                'pr_docs_count' => isset($documentsByPhase['PR Initiation']) ? count($documentsByPhase['PR Initiation']) : 0
            ]);

            return Inertia::render('hope/show', [
                'procurement' => $procurement,
                'now' => now()->toIso8601String(),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to retrieve procurement:', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Inertia::render('hope/show', [
                'error' => 'Failed to retrieve procurement: ' . $e->getMessage()
            ]);
        }
    }
}
