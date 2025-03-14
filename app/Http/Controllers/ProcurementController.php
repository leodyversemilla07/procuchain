<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MultiChainService;
use Exception;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProcurementController extends BaseController
{
    private $multiChain;

    private const STREAM_DOCUMENTS = 'procurement.documents';

    private const STREAM_STATE = 'procurement.state';

    private const STREAM_EVENTS = 'procurement.events';

    public function __construct(MultiChainService $multiChain)
    {
        $this->multiChain = $multiChain;
        $this->middleware('auth');
    }

    private function getUserBlockchainAddress()
    {
        $user = Auth::user();

        if (!$user) {
            Log::error('User not authenticated when trying to get blockchain address');
            throw new Exception('User must be logged in to perform this action');
        }

        if (empty($user->blockchain_address)) {
            Log::error('User has no blockchain address', ['user_id' => $user->id]);
            throw new Exception('Blockchain address not set for this user. Please contact system administrator.');
        }

        return $user->blockchain_address;
    }

    private function getStreamKey($procurementId, $procurementTitle)
    {
        return $procurementId . '-' . preg_replace('/[^a-zA-Z0-9-]/', '-', $procurementTitle);
    }

    private function publishDocuments($procurementId, $procurementTitle, $phaseIdentifier, $state, $metadataArray, $userAddress, $requiresManualPublish = false)
    {
        $timestamp = now()->toIso8601String();
        $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

        try {
            // Ensure user address is valid
            if (empty($userAddress)) {
                throw new Exception("Cannot publish documents: BAC secretariat blockchain address is not set");
            }

            $documentItems = [];
            foreach ($metadataArray as $index => $metadata) {
                $docData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'phase_identifier' => $phaseIdentifier,
                    'timestamp' => $timestamp,
                    'document_index' => $index + 1,
                    'document_type' => $metadata['document_type'],
                    'hash' => $metadata['hash'],
                    'file_key' => $metadata['file_key'],
                    'user_address' => $userAddress,
                    'file_size' => $metadata['file_size'],
                    'phase_metadata' => array_diff_key($metadata, array_flip(['document_type', 'hash', 'file_key', 'file_size'])),
                ];
                $documentItems[] = [
                    'key' => $streamKey,
                    'data' => $docData,
                ];
            }
            $this->multiChain->publishMultiFrom($userAddress, self::STREAM_DOCUMENTS, $documentItems);

            $this->updateState($procurementId, $procurementTitle, $state, $phaseIdentifier, $userAddress, $timestamp);
            $this->logEvent($procurementId, $procurementTitle, $phaseIdentifier, 'Uploaded ' . count($metadataArray) . " finalized $phaseIdentifier documents", count($metadataArray), $userAddress, 'document_upload', 'workflow', 'info', $timestamp);
        } catch (Exception $e) {
            Log::error('Error publishing documents', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
                'user_address' => $userAddress
            ]);
            throw $e;
        }
    }

    private function updateState($procurementId, $procurementTitle, $state, $phaseIdentifier, $userAddress, $timestamp)
    {
        $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

        $stateData = [
            'procurement_id' => $procurementId,
            'procurement_title' => $procurementTitle,
            'current_state' => $state,
            'phase_identifier' => $phaseIdentifier,
            'timestamp' => $timestamp,
            'user_address' => $userAddress,
        ];

        $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $stateData);
    }

    private function logEvent($procurementId, $procurementTitle, $phaseIdentifier, $details, $documentCount, $userAddress, $eventType, $category, $severity, $timestamp)
    {
        $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

        $eventData = [
            'procurement_id' => $procurementId,
            'procurement_title' => $procurementTitle,
            'event_type' => $eventType,
            'phase_identifier' => $phaseIdentifier,
            'timestamp' => $timestamp,
            'user_address' => $userAddress,
            'details' => $details,
            'category' => $category,
            'severity' => $severity,
            'document_count' => $documentCount,
        ];

        $this->multiChain->publishFrom($userAddress, self::STREAM_EVENTS, $streamKey, $eventData);
    }

    /**
     * Handle phase transition across all streams consistently.
     * 
     * @param string $procurementId
     * @param string $procurementTitle
     * @param string $fromState
     * @param string $toState
     * @param string $fromPhase
     * @param string $toPhase
     * @param string $userAddress
     * @param string $details
     * @return void
     */
    private function handlePhaseTransition($procurementId, $procurementTitle, $fromState, $toState, $fromPhase, $toPhase, $userAddress, $details)
    {
        // Log transition start
        Log::info('Phase transition beginning', [
            'procurement_id' => $procurementId,
            'from_phase' => $fromPhase,
            'to_phase' => $toPhase,
            'from_state' => $fromState,
            'to_state' => $toState
        ]);

        $timestamp = now()->toIso8601String();
        $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

        // Update state with new phase explicitly
        $stateData = [
            'procurement_id' => $procurementId,
            'procurement_title' => $procurementTitle,
            'current_state' => $toState,
            'phase_identifier' => $toPhase,
            'timestamp' => $timestamp,
            'user_address' => $userAddress,
        ];

        // Direct publish to state stream to ensure phase is updated
        $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $stateData);

        // Log the phase transition event - using the new phase
        $this->logEvent(
            $procurementId,
            $procurementTitle,
            $toPhase,
            $details,
            0,
            $userAddress,
            'phase_transition',
            'workflow',
            'info',
            $timestamp
        );

        Log::info('Phase transition completed', [
            'procurement_id' => $procurementId,
            'new_phase' => $toPhase,
            'new_state' => $toState
        ]);
    }

    /**
     * Display a listing of all procurements.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        try {
            // Get all states from the blockchain to find the latest state for each procurement
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            // Group by procurement ID to find the latest state for each
            $procurementsByKey = collect($allStates)
                ->map(function ($item) {
                    // Parse the JSON data if it's in string format
                    $data = $item['data'];
                    return [
                        'id' => $data['procurement_id'] ?? '',
                        'title' => $data['procurement_title'] ?? '',
                        'phase_identifier' => $data['phase_identifier'] ?? '',
                        'current_state' => $data['current_state'] ?? '',
                        'user_address' => $data['user_address'] ?? '',
                        'timestamp' => $data['timestamp'] ?? '',
                        // Using timestamp as lastUpdated for the UI
                        'lastUpdated' => date('Y-m-d', strtotime($data['timestamp'] ?? 'now')),
                        'procurement_id' => $data['procurement_id'] ?? '',
                        'procurement_title' => $data['procurement_title'] ?? '',
                        // This will be updated later after counting documents
                        'documentCount' => 0
                    ];
                })
                ->groupBy('id')
                ->map(function ($group) {
                    // Return only the latest state for each procurement ID
                    return $group->sortByDesc('timestamp')->first();
                })
                ->values()
                ->toArray(); // Convert to array before modifying elements

            // Get document counts for each procurement - now working with a plain array
            foreach ($procurementsByKey as $key => $procurement) {
                $procId = $procurement['id'];
                $procTitle = $procurement['title'];
                $streamKey = $this->getStreamKey($procId, $procTitle);

                // Count documents for this procurement
                $documents = $this->multiChain->listStreamKeyItems(self::STREAM_DOCUMENTS, $streamKey);
                $procurementsByKey[$key]['documentCount'] = count($documents);
            }

            // Return the data to the index page using Inertia
            return Inertia::render('bac-secretariat/procurements-list', [
                'procurements' => $procurementsByKey
            ]);

        } catch (Exception $e) {
            Log::error('Failed to retrieve procurements:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // If there's an error, we'll return an empty array to avoid breaking the frontend
            return Inertia::render('bac-secretariat/procurements-list', [
                'procurements' => [],
                'error' => 'Failed to retrieve procurements: ' . $e->getMessage()
            ]);
        }
    }

    public function show($procurementId)
    {
        try {
            // Get all states for this procurement ID to find the complete history
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            // Filter to get only states for this procurement
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

            // Get the latest state
            $latestState = $procurementStates->first();
            $procurementTitle = $latestState['data']['procurement_title'] ?? '';
            $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

            // Get documents for this procurement - increased limit to 1000 to ensure all documents are retrieved
            $documents = $this->multiChain->listStreamKeyItems(self::STREAM_DOCUMENTS, $streamKey, 1000);

            // Debug log for PR Initiation issues
            Log::info("Found " . count($documents) . " documents for procurement $procurementId");

            // Check for documents that might be PR Initiation but aren't properly tagged
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

                // Generate spaces URL
                $spaces_url = '';
                if (isset($data['file_key'])) {
                    $spaces_url = Storage::disk('spaces')->temporaryUrl($data['file_key'], now()->addMinutes(30));
                }

                // If phase_identifier is missing but looks like a PR document, tag it
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

            // Group documents by phase for easier navigation
            $documentsByPhase = $parsedDocuments->groupBy('phase_identifier')->map(function ($docs) {
                return $docs->sortByDesc('timestamp')->values();
            })->toArray();

            // Define the standard procurement phases in order
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

            // Check if PR Initiation documents are missing but exist in procurement documents
            // This addresses the case where documents might exist but aren't properly categorized
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

            // Ensure all phases are represented in documents_by_phase, even if empty
            foreach ($procurementPhases as $phase) {
                if (!isset($documentsByPhase[$phase])) {
                    $documentsByPhase[$phase] = [];
                }
            }

            // Get events for this procurement
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

            // Group events by phase for better organization
            $eventsByPhase = collect($parsedEvents)->groupBy('phase_identifier')->map(function ($events) {
                return $events->values();
            })->toArray();

            // Create phase history by grouping states
            $phaseHistory = $procurementStates->groupBy('phase_identifier')
                ->map(function ($phaseStates) {
                    return $phaseStates->sortBy('timestamp')->values();
                })->toArray();

            // Create a phase summary with status, dates, and counts
            $phaseSummary = [];
            foreach ($procurementPhases as $phase) {
                // Fix: Ensure we always have a Collection, not an array
                $phaseStates = collect($phaseHistory[$phase] ?? []);
                $phaseDocuments = isset($documentsByPhase[$phase]) ? $documentsByPhase[$phase] : [];
                $phaseEvents = isset($eventsByPhase[$phase]) ? $eventsByPhase[$phase] : [];

                // Find the latest state for this phase
                $latestPhaseState = $phaseStates->isEmpty() ? null : $phaseStates->sortByDesc('timestamp')->first();

                // Determine if the phase was completed or skipped
                $isCompleted = !empty($latestPhaseState);
                $isSkipped = $isCompleted && strpos($latestPhaseState['current_state'], 'Skipped') !== false;

                // Determine phase status
                $status = 'Not Started';
                if ($isSkipped) {
                    $status = 'Skipped';
                } elseif ($isCompleted) {
                    // Check if this is the current phase
                    if ($phase === $latestState['phase_identifier']) {
                        $status = 'Current';
                    } else {
                        $status = 'Completed';
                    }
                } elseif (array_search($phase, $procurementPhases) < array_search($latestState['phase_identifier'], $procurementPhases)) {
                    // If phase is before the current phase, it should be completed
                    $status = 'Completed';
                }

                // Start and end dates
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

            // Format state according to BlockchainProcurementState interface
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

            // Create a timeline of all state changes
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
                'phases' => $procurementPhases, // All possible phases in order
            ];


            // Log for debugging the phases
            Log::info('Documents by phase in show method:', [
                'phase_keys' => array_keys($documentsByPhase),
                'pr_docs_count' => isset($documentsByPhase['PR Initiation']) ? count($documentsByPhase['PR Initiation']) : 0
            ]);

            // Return the data to the view
            return Inertia::render('bac-secretariat/show', [
                'procurement' => $procurement,
                'now' => now()->toIso8601String(),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to retrieve procurement:', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Inertia::render('bac-secretariat/show', [
                'error' => 'Failed to retrieve procurement: ' . $e->getMessage()
            ]);
        }
    }



    // Phase 1: PR Initiation
    public function publishPrInitiation(Request $request)
    {
        $procurementId = $request->input('procurement_id');
        $procurementTitle = $request->input('procurement_title');
        $prFile = $request->file('pr_file');
        $prMetadata = $request->input('pr_metadata');
        $supportingFiles = $request->file('supporting_files', []);
        $supportingMetadata = $request->input('supporting_metadata', []);

        $timestamp = now()->toIso8601String();

        try {
            // $userAddress = "15rdrfaw6xydP81YhJkSXXuHR4T7vFviung6NW";
            $userAddress = $this->getUserBlockchainAddress();

            $metadataArray = [];

            if ($prFile) {
                $prFileKey = "$procurementId-$procurementTitle/PRInitiation/$procurementId-$procurementTitle-PurchaseRequest." . $prFile->getClientOriginalExtension();
                Storage::disk('spaces')->put($prFileKey, file_get_contents($prFile), 'private');
                $prHash = hash('sha256', file_get_contents($prFile->getRealPath()));

                $metadataArray[] = [
                    'document_type' => $prMetadata['document_type'] ?? 'Purchase Request',
                    'hash' => $prHash,
                    'file_key' => $prFileKey,
                    'file_size' => $prFile->getSize(),
                    'submission_date' => $prMetadata['submission_date'],
                    'municipal_offices' => $prMetadata['municipal_offices'],
                    'signatory_details' => $prMetadata['signatory_details'],
                ];
            }

            foreach ($supportingFiles as $index => $file) {
                $documentType = preg_replace('/[^a-zA-Z0-9-]/', '-', $supportingMetadata[$index]['document_type']);
                $fileKey = "$procurementId-$procurementTitle/PRInitiation/$procurementId-$procurementTitle-supporting-$documentType-" . ($index + 1) . '.' . $file->getClientOriginalExtension();
                Storage::disk('spaces')->put($fileKey, file_get_contents($file), 'private');
                $hash = hash('sha256', file_get_contents($file->getRealPath()));

                $metadataArray[] = [
                    'document_type' => $supportingMetadata[$index]['document_type'],
                    'hash' => $hash,
                    'file_key' => $fileKey,
                    'file_size' => $file->getSize(),
                    'submission_date' => $supportingMetadata[$index]['submission_date'] ?? $prMetadata['submission_date'],
                    'municipal_offices' => $supportingMetadata[$index]['municipal_offices'] ?? $prMetadata['municipal_offices'],
                    'signatory_details' => $supportingMetadata[$index]['signatory_details'] ?? $prMetadata['signatory_details'],
                ];
            }

            $this->publishDocuments($procurementId, $procurementTitle, 'PR Initiation', 'PR Submitted', $metadataArray, $userAddress);

            // Return JSON response for success case instead of redirecting
            return response()->json([
                'success' => true,
                'message' => 'Documents published successfully',
                'procurementId' => $procurementId,
                'procurementTitle' => $procurementTitle,
                'documentCount' => count($metadataArray),
                'timestamp' => $timestamp
            ]);
        } catch (Exception $e) {
            // Return JSON response for error case
            return response()->json([
                'success' => false,
                'errorMessage' => 'Failed to publish documents: ' . $e->getMessage(),
                'procurementId' => $procurementId,
                'procurementTitle' => $procurementTitle
            ], 500);
        }
    }

    // Phase 2: Pre-Procurement Conference Decision
    public function publishPreProcurementDecision(Request $request)
    {
        $procurementId = $request->input('procurement_id');
        $procurementTitle = $request->input('procurement_title');
        $conferenceHeld = $request->boolean('conference_held');
        $timestamp = now()->toIso8601String();

        try {
            $userAddress = $this->getUserBlockchainAddress();

            if ($conferenceHeld) {
                // Conference was held - update state using direct API call
                $streamKey = $this->getStreamKey($procurementId, $procurementTitle);
                $stateData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'current_state' => 'Pre-Procurement Conference Held',
                    'phase_identifier' => 'Pre-Procurement',
                    'timestamp' => $timestamp,
                    'user_address' => $userAddress,
                ];
                $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $stateData);

                // Log the decision event
                $this->logEvent(
                    $procurementId,
                    $procurementTitle,
                    'Pre-Procurement',
                    'Pre-procurement conference was held - documents pending upload',
                    0,
                    $userAddress,
                    'decision',
                    'workflow',
                    'info',
                    $timestamp
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Pre-procurement conference recorded as held. Please upload the conference documents.',
                    'nextPhase' => 'Pre-Procurement',
                    'procurementId' => $procurementId,
                    'procurementTitle' => $procurementTitle,
                    'documentsRequired' => true
                ]);
            } else {
                // Conference was not held - skip to Bid Invitation phase
                // Handle phase transition from PR Initiation to Bid Invitation
                $this->handlePhaseTransition(
                    $procurementId,
                    $procurementTitle,
                    'PR Submitted',           // from state
                    'Pre-Procurement Skipped', // to state
                    'PR Initiation',          // from phase
                    'Bid Invitation',         // to phase
                    $userAddress,
                    'Pre-procurement conference skipped - proceeding to Bid Invitation'
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Pre-procurement conference skipped. Proceeding to Bid Invitation phase.',
                    'nextPhase' => 'Bid Invitation',
                    'procurementId' => $procurementId,
                    'procurementTitle' => $procurementTitle,
                    'documentsRequired' => false
                ]);
            }
        } catch (Exception $e) {
            Log::error('Error processing pre-procurement decision', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'errorMessage' => 'Failed to process pre-procurement decision: ' . $e->getMessage()
            ], 500);
        }
    }

    // Upload Pre-Procurement Documents (new endpoint)
    public function uploadPreProcurementDocuments(Request $request)
    {
        $procurementId = $request->input('procurement_id');
        $procurementTitle = $request->input('procurement_title');
        $minutesFile = $request->file('minutes_file');
        $attendanceFile = $request->file('attendance_file');
        $meetingDate = $request->input('meeting_date');
        $participants = $request->input('participants');
        $timestamp = now()->toIso8601String();

        try {
            $userAddress = $this->getUserBlockchainAddress();
            $metadataArray = [];

            // Process minutes file
            if ($minutesFile) {
                $fileKey = "$procurementId-$procurementTitle/PreProcurement/$procurementId-$procurementTitle-Minutes." . $minutesFile->getClientOriginalExtension();
                Storage::disk('spaces')->put($fileKey, file_get_contents($minutesFile), 'private');
                $hash = hash('sha256', file_get_contents($minutesFile->getRealPath()));

                $metadataArray[] = [
                    'document_type' => 'Minutes',
                    'hash' => $hash,
                    'file_key' => $fileKey,
                    'file_size' => $minutesFile->getSize(),
                    'meeting_date' => $meetingDate,
                    'participants' => $participants,
                ];
            }

            // Process attendance file
            if ($attendanceFile) {
                $fileKey = "$procurementId-$procurementTitle/PreProcurement/$procurementId-$procurementTitle-Attendance." . $attendanceFile->getClientOriginalExtension();
                Storage::disk('spaces')->put($fileKey, file_get_contents($attendanceFile), 'private');
                $hash = hash('sha256', file_get_contents($attendanceFile->getRealPath()));

                $metadataArray[] = [
                    'document_type' => 'Attendance',
                    'hash' => $hash,
                    'file_key' => $fileKey,
                    'file_size' => $attendanceFile->getSize(),
                    'meeting_date' => $meetingDate,
                    'participants' => $participants,
                ];
            }

            // Get stream key
            $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

            // Prepare document items for blockchain
            $documentItems = [];
            foreach ($metadataArray as $index => $metadata) {
                $docData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'phase_identifier' => 'Pre-Procurement', // Use current phase for documents
                    'timestamp' => $timestamp,
                    'document_index' => $index + 1,
                    'document_type' => $metadata['document_type'],
                    'hash' => $metadata['hash'],
                    'file_key' => $metadata['file_key'],
                    'user_address' => $userAddress,
                    'file_size' => $metadata['file_size'],
                    'phase_metadata' => array_diff_key($metadata, array_flip(['document_type', 'hash', 'file_key', 'file_size'])),
                ];
                $documentItems[] = [
                    'key' => $streamKey,
                    'data' => $docData,
                ];
            }

            // 1. Publish documents with current phase
            $this->multiChain->publishMultiFrom($userAddress, self::STREAM_DOCUMENTS, $documentItems);

            // 2. Log document upload event
            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Pre-Procurement',
                'Uploaded ' . count($metadataArray) . " finalized Pre-Procurement documents",
                count($metadataArray),
                $userAddress,
                'document_upload',
                'workflow',
                'info',
                $timestamp
            );

            // 3. Update state to completed in current phase
            $intermediateStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Pre-Procurement Completed',
                'phase_identifier' => 'Pre-Procurement', // Still in Pre-Procurement phase
                'timestamp' => $timestamp,
                'user_address' => $userAddress,
            ];
            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $intermediateStateData);

            // 4. Now perform the phase transition with slight delay to ensure proper ordering
            $newTimestamp = now()->addSecond()->toIso8601String();

            // 5. Transition to next phase with a new explicit state update
            $transitionStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Pre-Procurement Completed',
                'phase_identifier' => 'Bid Invitation', // Change to next phase
                'timestamp' => $newTimestamp, // Use newer timestamp to ensure this becomes the latest state
                'user_address' => $userAddress,
            ];

            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $transitionStateData);

            // 6. Log the phase transition event
            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Bid Invitation', // Use the destination phase
                'Proceeding to Bid Invitation phase after completing Pre-Procurement',
                0,
                $userAddress,
                'phase_transition',
                'workflow',
                'info',
                $newTimestamp
            );

            Log::info('Phase transition completed', [
                'procurement_id' => $procurementId,
                'from_phase' => 'Pre-Procurement',
                'to_phase' => 'Bid Invitation',
                'current_state' => 'Pre-Procurement Completed'
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')->with([
                'success' => true,
                'message' => 'Pre-procurement conference documents uploaded successfully. Proceeding to Bid Invitation phase.'
            ]);

        } catch (Exception $e) {
            Log::error('Error uploading pre-procurement documents', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to upload pre-procurement documents: ' . $e->getMessage()
            ]);
        }
    }

    // Phase 3: Bid Invitation Publication
    public function publishBidInvitation(Request $request)
    {
        $procurementId = $request->input('procurement_id');
        $procurementTitle = $request->input('procurement_title');
        $bidInvitationFile = $request->file('bid_invitation_file');
        $metadata = $request->input('metadata');
        $timestamp = now()->toIso8601String();

        try {
            $userAddress = $this->getUserBlockchainAddress();

            $metadataArray = [];

            if ($bidInvitationFile) {
                $fileKey = "$procurementId-$procurementTitle/BidInvitation/$procurementId-$procurementTitle-BidInvitation." . $bidInvitationFile->getClientOriginalExtension();
                Storage::disk('spaces')->put($fileKey, file_get_contents($bidInvitationFile), 'private');
                $hash = hash('sha256', file_get_contents($bidInvitationFile->getRealPath()));

                $metadataArray[] = [
                    'document_type' => 'Bid Invitation',
                    'hash' => $hash,
                    'file_key' => $fileKey,
                    'file_size' => $bidInvitationFile->getSize(),
                    'submission_date' => $metadata['submission_date'] ?? now()->format('Y-m-d'),
                    'signatory_details' => $metadata['signatory_details'] ?? '',
                ];
            }

            // Get stream key
            $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

            // Prepare document items for blockchain
            $documentItems = [];
            foreach ($metadataArray as $index => $metadata) {
                $docData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'phase_identifier' => 'Bid Invitation', // Use current phase for documents
                    'timestamp' => $timestamp,
                    'document_index' => $index + 1,
                    'document_type' => $metadata['document_type'],
                    'hash' => $metadata['hash'],
                    'file_key' => $metadata['file_key'],
                    'user_address' => $userAddress,
                    'file_size' => $metadata['file_size'],
                    'phase_metadata' => array_diff_key($metadata, array_flip(['document_type', 'hash', 'file_key', 'file_size'])),
                ];
                $documentItems[] = [
                    'key' => $streamKey,
                    'data' => $docData,
                ];
            }

            // 1. Publish documents with current phase
            $this->multiChain->publishMultiFrom($userAddress, self::STREAM_DOCUMENTS, $documentItems);

            // 2. Log document upload event
            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Bid Invitation',
                'Uploaded ' . count($metadataArray) . " finalized Bid Invitation documents",
                count($metadataArray),
                $userAddress,
                'document_upload',
                'workflow',
                'info',
                $timestamp
            );

            // 3. Update state to "Bid Invitation Published" in current phase
            $currentStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Bid Invitation Published',
                'phase_identifier' => 'Bid Invitation',
                'timestamp' => $timestamp,
                'user_address' => $userAddress,
            ];
            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $currentStateData);

            // 4. Log additional publication event
            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Bid Invitation',
                'Published bid invitation to PhilGEPS',
                1,
                $userAddress,
                'publication',
                'workflow',
                'info',
                $timestamp
            );

            // 5. Now perform the phase transition with slight delay to ensure proper ordering
            $newTimestamp = now()->addSecond()->toIso8601String();

            // 6. Transition to next phase with a new explicit state update
            $transitionStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Bid Invitation Published',
                'phase_identifier' => 'Bid Opening', // Change to next phase
                'timestamp' => $newTimestamp, // Use newer timestamp to ensure this becomes the latest state
                'user_address' => $userAddress,
            ];

            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $transitionStateData);

            // 7. Log the phase transition event
            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Bid Opening', // Use the destination phase
                'Proceeding to Bid Opening phase after publishing Bid Invitation',
                0,
                $userAddress,
                'phase_transition',
                'workflow',
                'info',
                $newTimestamp
            );

            Log::info('Phase transition completed', [
                'procurement_id' => $procurementId,
                'from_phase' => 'Bid Invitation',
                'to_phase' => 'Bid Opening',
                'current_state' => 'Bid Invitation Published'
            ]);

            // Changed from JSON response to redirect response
            return redirect()->route('bac-secretariat.procurements-list.index')->with([
                'success' => true,
                'message' => 'Bid invitation published successfully. Proceeding to Bid Opening phase.'
            ]);

        } catch (Exception $e) {
            Log::error('Error publishing bid invitation', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to publish bid invitation: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Upload bid submission documents.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function uploadBidSubmissionDocuments(Request $request)
    {
        $procurementId = $request->input('procurement_id');
        $procurementTitle = $request->input('procurement_title');
        $bidDocuments = $request->file('bid_documents', []);
        $biddersData = $request->input('bidders_data', []);
        $openingDateTime = $request->input('opening_date_time');
        $timestamp = now()->toIso8601String();

        try {
            $userAddress = $this->getUserBlockchainAddress();
            $metadataArray = [];

            foreach ($bidDocuments as $index => $file) {
                if ($file && isset($biddersData[$index])) {
                    $bidderName = $biddersData[$index]['bidder_name'] ?? 'Unknown Bidder';
                    $bidValue = $biddersData[$index]['bid_value'] ?? '0';

                    $safeNamePart = preg_replace('/[^a-zA-Z0-9-]/', '-', $bidderName);
                    $fileKey = "$procurementId-$procurementTitle/BidOpening/$procurementId-$procurementTitle-Bid-$safeNamePart." . $file->getClientOriginalExtension();

                    Storage::disk('spaces')->put($fileKey, file_get_contents($file), 'private');
                    $hash = hash('sha256', file_get_contents($file->getRealPath()));

                    $metadataArray[] = [
                        'document_type' => 'Bid Document',
                        'hash' => $hash,
                        'file_key' => $fileKey,
                        'file_size' => $file->getSize(),
                        'bidder_name' => $bidderName,
                        'bid_value' => $bidValue,
                        'opening_date_time' => $openingDateTime,
                    ];
                }
            }

            // Only proceed if we have documents
            if (count($metadataArray) > 0) {
                // Get stream key
                $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

                // Prepare document items for blockchain
                $documentItems = [];
                foreach ($metadataArray as $index => $metadata) {
                    $docData = [
                        'procurement_id' => $procurementId,
                        'procurement_title' => $procurementTitle,
                        'phase_identifier' => 'Bid Opening', // Use current phase for documents
                        'timestamp' => $timestamp,
                        'document_index' => $index + 1,
                        'document_type' => $metadata['document_type'],
                        'hash' => $metadata['hash'],
                        'file_key' => $metadata['file_key'],
                        'user_address' => $userAddress,
                        'file_size' => $metadata['file_size'],
                        'phase_metadata' => array_diff_key($metadata, array_flip(['document_type', 'hash', 'file_key', 'file_size'])),
                    ];
                    $documentItems[] = [
                        'key' => $streamKey,
                        'data' => $docData,
                    ];
                }

                // 1. Publish documents with current phase
                $this->multiChain->publishMultiFrom($userAddress, self::STREAM_DOCUMENTS, $documentItems);

                // 2. Log document upload event
                $this->logEvent(
                    $procurementId,
                    $procurementTitle,
                    'Bid Opening',
                    'Uploaded ' . count($metadataArray) . " finalized Bid Opening documents",
                    count($metadataArray),
                    $userAddress,
                    'document_upload',
                    'workflow',
                    'info',
                    $timestamp
                );

                // 3. Update state to completed in current phase
                $currentStateData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'current_state' => 'Bids Opened',
                    'phase_identifier' => 'Bid Opening', // Still in Bid Opening phase
                    'timestamp' => $timestamp,
                    'user_address' => $userAddress,
                ];
                $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $currentStateData);

                // 4. Now perform the phase transition with slight delay to ensure proper ordering
                $newTimestamp = now()->addSecond()->toIso8601String();

                // 5. Transition to next phase with a new explicit state update
                $transitionStateData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'current_state' => 'Bids Opened',
                    'phase_identifier' => 'Bid Evaluation', // Change to next phase
                    'timestamp' => $newTimestamp, // Use newer timestamp to ensure this becomes the latest state
                    'user_address' => $userAddress,
                ];

                $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $transitionStateData);

                // 6. Log the phase transition event
                $this->logEvent(
                    $procurementId,
                    $procurementTitle,
                    'Bid Evaluation', // Use the destination phase
                    'Proceeding to Bid Evaluation phase after opening bids',
                    0,
                    $userAddress,
                    'phase_transition',
                    'workflow',
                    'info',
                    $newTimestamp
                );

                Log::info('Phase transition completed', [
                    'procurement_id' => $procurementId,
                    'from_phase' => 'Bid Opening',
                    'to_phase' => 'Bid Evaluation',
                    'current_state' => 'Bids Opened'
                ]);

                return redirect()->route('bac-secretariat.procurements-list.index')->with([
                    'success' => true,
                    'message' => count($metadataArray) . ' bid documents uploaded successfully. Proceeding to Bid Evaluation phase.'
                ]);
            } else {
                return redirect()->back()->withErrors([
                    'error' => 'No valid bid documents were provided.'
                ]);
            }

        } catch (Exception $e) {
            Log::error('Error uploading bid documents', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to upload bid documents: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Upload bid evaluation documents.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function uploadBidEvaluationDocuments(Request $request)
    {
        $procurementId = $request->input('procurement_id');
        $procurementTitle = $request->input('procurement_title');
        $summaryFile = $request->file('summary_file');
        $abstractFile = $request->file('abstract_file');
        $evaluationDate = $request->input('evaluation_date');
        $evaluatorNames = $request->input('evaluator_names');
        $timestamp = now()->toIso8601String();

        try {
            $userAddress = $this->getUserBlockchainAddress();
            $metadataArray = [];

            // Process summary file
            if ($summaryFile) {
                $fileKey = "$procurementId-$procurementTitle/BidEvaluation/$procurementId-$procurementTitle-EvaluationSummary." . $summaryFile->getClientOriginalExtension();
                Storage::disk('spaces')->put($fileKey, file_get_contents($summaryFile), 'private');
                $hash = hash('sha256', file_get_contents($summaryFile->getRealPath()));

                $metadataArray[] = [
                    'document_type' => 'Evaluation Summary',
                    'hash' => $hash,
                    'file_key' => $fileKey,
                    'file_size' => $summaryFile->getSize(),
                    'evaluation_date' => $evaluationDate,
                    'evaluator_names' => $evaluatorNames,
                ];
            }

            // Process abstract file
            if ($abstractFile) {
                $fileKey = "$procurementId-$procurementTitle/BidEvaluation/$procurementId-$procurementTitle-Abstract." . $abstractFile->getClientOriginalExtension();
                Storage::disk('spaces')->put($fileKey, file_get_contents($abstractFile), 'private');
                $hash = hash('sha256', file_get_contents($abstractFile->getRealPath()));

                $metadataArray[] = [
                    'document_type' => 'Abstract',
                    'hash' => $hash,
                    'file_key' => $fileKey,
                    'file_size' => $abstractFile->getSize(),
                    'evaluation_date' => $evaluationDate,
                    'evaluator_names' => $evaluatorNames,
                ];
            }

            // Get stream key
            $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

            // Prepare document items for blockchain
            $documentItems = [];
            foreach ($metadataArray as $index => $metadata) {
                $docData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'phase_identifier' => 'Bid Evaluation', // Use current phase for documents
                    'timestamp' => $timestamp,
                    'document_index' => $index + 1,
                    'document_type' => $metadata['document_type'],
                    'hash' => $metadata['hash'],
                    'file_key' => $metadata['file_key'],
                    'user_address' => $userAddress,
                    'file_size' => $metadata['file_size'],
                    'phase_metadata' => array_diff_key($metadata, array_flip(['document_type', 'hash', 'file_key', 'file_size'])),
                ];
                $documentItems[] = [
                    'key' => $streamKey,
                    'data' => $docData,
                ];
            }

            // 1. Publish documents with current phase
            $this->multiChain->publishMultiFrom($userAddress, self::STREAM_DOCUMENTS, $documentItems);

            // 2. Log document upload event
            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Bid Evaluation',
                'Uploaded ' . count($metadataArray) . " finalized Bid Evaluation documents",
                count($metadataArray),
                $userAddress,
                'document_upload',
                'workflow',
                'info',
                $timestamp
            );

            // 3. Update state to completed in current phase
            $currentStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Bids Evaluated',
                'phase_identifier' => 'Bid Evaluation',
                'timestamp' => $timestamp,
                'user_address' => $userAddress,
            ];
            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $currentStateData);

            // Log completion of current phase
            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Bid Evaluation',
                'Completed bid evaluation with ' . count($metadataArray) . " documents",
                count($metadataArray),
                $userAddress,
                'state_change',
                'workflow',
                'info',
                $timestamp
            );

            // Add a small sleep to ensure blockchain transaction ordering
            usleep(500000); // 500ms delay

            // 4. Now perform the phase transition with slight delay to ensure proper ordering
            $newTimestamp = now()->toIso8601String(); // Get a fresh timestamp after the delay

            // 5. Transition to next phase with a new explicit state update
            $transitionStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Bids Evaluated',
                'phase_identifier' => 'Post-Qualification', // Change to next phase
                'timestamp' => $newTimestamp, // Use newer timestamp to ensure this becomes the latest state
                'user_address' => $userAddress,
            ];

            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $transitionStateData);

            // 6. Log the phase transition event
            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Post-Qualification', // Use the destination phase
                'Proceeding to Post-Qualification phase after completing Bid Evaluation',
                0,
                $userAddress,
                'phase_transition',
                'workflow',
                'info',
                $newTimestamp
            );

            Log::info('Phase transition completed', [
                'procurement_id' => $procurementId,
                'from_phase' => 'Bid Evaluation',
                'to_phase' => 'Post-Qualification',
                'current_state' => 'Bids Evaluated'
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')->with([
                'success' => true,
                'message' => 'Bid evaluation documents uploaded successfully. Proceeding to Post-Qualification phase.'
            ]);

        } catch (Exception $e) {
            Log::error('Error uploading bid evaluation documents', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to upload bid evaluation documents: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Upload post-qualification documents.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function uploadPostQualificationDocuments(Request $request)
    {
        $procurementId = $request->input('procurement_id');
        $procurementTitle = $request->input('procurement_title');
        $taxReturnFile = $request->file('tax_return_file');
        $financialStatementFile = $request->file('financial_statement_file');
        $verificationReportFile = $request->file('verification_report_file');
        $submissionDate = $request->input('submission_date');
        $outcome = $request->input('outcome');
        $timestamp = now()->toIso8601String();

        try {
            $userAddress = $this->getUserBlockchainAddress();
            $metadataArray = [];

            // Process tax return file
            if ($taxReturnFile) {
                $fileKey = "$procurementId-$procurementTitle/PostQualification/$procurementId-$procurementTitle-TaxReturn." . $taxReturnFile->getClientOriginalExtension();
                Storage::disk('spaces')->put($fileKey, file_get_contents($taxReturnFile), 'private');
                $hash = hash('sha256', file_get_contents($taxReturnFile->getRealPath()));

                $metadataArray[] = [
                    'document_type' => 'Tax Return',
                    'hash' => $hash,
                    'file_key' => $fileKey,
                    'file_size' => $taxReturnFile->getSize(),
                    'submission_date' => $submissionDate,
                    'outcome' => $outcome,
                ];
            }

            // Process financial statement file
            if ($financialStatementFile) {
                $fileKey = "$procurementId-$procurementTitle/PostQualification/$procurementId-$procurementTitle-FinancialStatement." . $financialStatementFile->getClientOriginalExtension();
                Storage::disk('spaces')->put($fileKey, file_get_contents($financialStatementFile), 'private');
                $hash = hash('sha256', file_get_contents($financialStatementFile->getRealPath()));

                $metadataArray[] = [
                    'document_type' => 'Financial Statement',
                    'hash' => $hash,
                    'file_key' => $fileKey,
                    'file_size' => $financialStatementFile->getSize(),
                    'submission_date' => $submissionDate,
                    'outcome' => $outcome,
                ];
            }

            // Process verification report file
            if ($verificationReportFile) {
                $fileKey = "$procurementId-$procurementTitle/PostQualification/$procurementId-$procurementTitle-VerificationReport." . $verificationReportFile->getClientOriginalExtension();
                Storage::disk('spaces')->put($fileKey, file_get_contents($verificationReportFile), 'private');
                $hash = hash('sha256', file_get_contents($verificationReportFile->getRealPath()));

                $metadataArray[] = [
                    'document_type' => 'Verification Report',
                    'hash' => $hash,
                    'file_key' => $fileKey,
                    'file_size' => $verificationReportFile->getSize(),
                    'submission_date' => $submissionDate,
                    'outcome' => $outcome,
                ];
            }

            // Get stream key
            $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

            // Prepare document items for blockchain
            $documentItems = [];
            foreach ($metadataArray as $index => $metadata) {
                $docData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'phase_identifier' => 'Post-Qualification', // Use current phase for documents
                    'timestamp' => $timestamp,
                    'document_index' => $index + 1,
                    'document_type' => $metadata['document_type'],
                    'hash' => $metadata['hash'],
                    'file_key' => $metadata['file_key'],
                    'user_address' => $userAddress,
                    'file_size' => $metadata['file_size'],
                    'phase_metadata' => array_diff_key($metadata, array_flip(['document_type', 'hash', 'file_key', 'file_size'])),
                ];
                $documentItems[] = [
                    'key' => $streamKey,
                    'data' => $docData,
                ];
            }

            // 1. Publish documents with current phase
            $this->multiChain->publishMultiFrom($userAddress, self::STREAM_DOCUMENTS, $documentItems);

            // 2. Log document upload event
            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Post-Qualification',
                'Uploaded ' . count($metadataArray) . " finalized Post-Qualification documents (" . $outcome . ")",
                count($metadataArray),
                $userAddress,
                'document_upload',
                'workflow',
                'info',
                $timestamp
            );

            // 3. Update state to completed in current phase
            $verifiedState = $outcome === 'Verified' ? 'Post-Qualification Verified' : 'Post-Qualification Failed';
            $currentStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => $verifiedState,
                'phase_identifier' => 'Post-Qualification',
                'timestamp' => $timestamp,
                'user_address' => $userAddress,
            ];
            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $currentStateData);

            // 4. Now perform the phase transition with slight delay to ensure proper ordering
            $newTimestamp = now()->addSecond()->toIso8601String();

            // 5. Transition to next phase with a new explicit state update
            // Only proceed to next phase if verification is successful
            if ($outcome === 'Verified') {
                $transitionStateData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'current_state' => $verifiedState,
                    'phase_identifier' => 'BAC Resolution', // Change to next phase
                    'timestamp' => $newTimestamp, // Use newer timestamp to ensure this becomes the latest state
                    'user_address' => $userAddress,
                ];

                $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $transitionStateData);

                // 6. Log the phase transition event
                $this->logEvent(
                    $procurementId,
                    $procurementTitle,
                    'BAC Resolution', // Use the destination phase
                    'Proceeding to BAC Resolution phase after successful Post-Qualification',
                    0,
                    $userAddress,
                    'phase_transition',
                    'workflow',
                    'info',
                    $newTimestamp
                );

                Log::info('Phase transition completed', [
                    'procurement_id' => $procurementId,
                    'from_phase' => 'Post-Qualification',
                    'to_phase' => 'BAC Resolution',
                    'current_state' => $verifiedState
                ]);
            } else {
                // Log event for failed post-qualification
                $this->logEvent(
                    $procurementId,
                    $procurementTitle,
                    'Post-Qualification',
                    'Post-Qualification failed - procurement process halted',
                    0,
                    $userAddress,
                    'status_update',
                    'workflow',
                    'warning',
                    $newTimestamp
                );

                Log::info('Post-qualification failed', [
                    'procurement_id' => $procurementId,
                    'outcome' => $outcome
                ]);
            }

            return redirect()->route('bac-secretariat.procurements-list.index')->with([
                'success' => true,
                'message' => 'Post-qualification documents uploaded successfully with outcome: ' . $outcome
            ]);

        } catch (Exception $e) {
            Log::error('Error uploading post-qualification documents', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to upload post-qualification documents: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Upload BAC Resolution document.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function uploadBacResolutionDocument(Request $request)
    {
        $procurementId = $request->input('procurement_id');
        $procurementTitle = $request->input('procurement_title');
        $bacResolutionFile = $request->file('bac_resolution_file');
        $issuanceDate = $request->input('issuance_date');
        $signatoryDetails = $request->input('signatory_details');
        $timestamp = now()->toIso8601String();

        try {
            $userAddress = $this->getUserBlockchainAddress();
            $metadataArray = [];

            // Process BAC Resolution file
            if ($bacResolutionFile) {
                $fileKey = "$procurementId-$procurementTitle/BACResolution/$procurementId-$procurementTitle-BACResolution." . $bacResolutionFile->getClientOriginalExtension();
                Storage::disk('spaces')->put($fileKey, file_get_contents($bacResolutionFile), 'private');
                $hash = hash('sha256', file_get_contents($bacResolutionFile->getRealPath()));

                $metadataArray[] = [
                    'document_type' => 'BAC Resolution',
                    'hash' => $hash,
                    'file_key' => $fileKey,
                    'file_size' => $bacResolutionFile->getSize(),
                    'issuance_date' => $issuanceDate,
                    'signatory_details' => $signatoryDetails,
                ];
            }

            // Get stream key
            $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

            // Prepare document items for blockchain
            $documentItems = [];
            foreach ($metadataArray as $index => $metadata) {
                $docData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'phase_identifier' => 'BAC Resolution', // Use current phase for documents
                    'timestamp' => $timestamp,
                    'document_index' => $index + 1,
                    'document_type' => $metadata['document_type'],
                    'hash' => $metadata['hash'],
                    'file_key' => $metadata['file_key'],
                    'user_address' => $userAddress,
                    'file_size' => $metadata['file_size'],
                    'phase_metadata' => array_diff_key($metadata, array_flip(['document_type', 'hash', 'file_key', 'file_size'])),
                ];
                $documentItems[] = [
                    'key' => $streamKey,
                    'data' => $docData,
                ];
            }

            // 1. Publish documents with current phase
            $this->multiChain->publishMultiFrom($userAddress, self::STREAM_DOCUMENTS, $documentItems);

            // 2. Log document upload event
            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'BAC Resolution',
                'Uploaded ' . count($metadataArray) . " finalized BAC Resolution document",
                count($metadataArray),
                $userAddress,
                'document_upload',
                'workflow',
                'info',
                $timestamp
            );

            // 3. Update state in current phase
            $currentStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Resolution Recorded',
                'phase_identifier' => 'BAC Resolution',
                'timestamp' => $timestamp,
                'user_address' => $userAddress,
            ];
            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $currentStateData);

            // 4. Now perform the phase transition with slight delay to ensure proper ordering
            $newTimestamp = now()->addSecond()->toIso8601String();

            // 5. Transition to next phase with a new explicit state update
            $transitionStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Resolution Recorded',
                'phase_identifier' => 'Notice Of Award', // Change to next phase
                'timestamp' => $newTimestamp, // Use newer timestamp to ensure this becomes the latest state
                'user_address' => $userAddress,
            ];

            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $transitionStateData);

            // 6. Log the phase transition event
            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Notice Of Award', // Use the destination phase
                'Proceeding to Notice Of Award phase after recording BAC Resolution',
                0,
                $userAddress,
                'phase_transition',
                'workflow',
                'info',
                $newTimestamp
            );

            Log::info('Phase transition completed', [
                'procurement_id' => $procurementId,
                'from_phase' => 'BAC Resolution',
                'to_phase' => 'Notice Of Award',
                'current_state' => 'Resolution Recorded'
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')->with([
                'success' => true,
                'message' => 'BAC Resolution document uploaded successfully. Proceeding to Notice Of Award phase.'
            ]);

        } catch (Exception $e) {
            Log::error('Error uploading BAC Resolution document', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to upload BAC Resolution document: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Upload Notice of Award document.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function uploadNoaDocument(Request $request)
    {
        $procurementId = $request->input('procurement_id');
        $procurementTitle = $request->input('procurement_title');
        $noaFile = $request->file('noa_file');
        $issuanceDate = $request->input('issuance_date');
        $signatoryDetails = $request->input('signatory_details');
        $timestamp = now()->toIso8601String();

        try {
            $userAddress = $this->getUserBlockchainAddress();
            $metadataArray = [];

            // Process Notice of Award file
            if ($noaFile) {
                $fileKey = "$procurementId-$procurementTitle/NoticeOfAward/$procurementId-$procurementTitle-NOA." . $noaFile->getClientOriginalExtension();
                Storage::disk('spaces')->put($fileKey, file_get_contents($noaFile), 'private');
                $hash = hash('sha256', file_get_contents($noaFile->getRealPath()));

                $metadataArray[] = [
                    'document_type' => 'Notice of Award',
                    'hash' => $hash,
                    'file_key' => $fileKey,
                    'file_size' => $noaFile->getSize(),
                    'issuance_date' => $issuanceDate,
                    'signatory_details' => $signatoryDetails,
                ];
            }

            // Get stream key
            $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

            // Prepare document items for blockchain
            $documentItems = [];
            foreach ($metadataArray as $index => $metadata) {
                $docData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'phase_identifier' => 'Notice Of Award', // Use current phase for documents
                    'timestamp' => $timestamp,
                    'document_index' => $index + 1,
                    'document_type' => $metadata['document_type'],
                    'hash' => $metadata['hash'],
                    'file_key' => $metadata['file_key'],
                    'user_address' => $userAddress,
                    'file_size' => $metadata['file_size'],
                    'phase_metadata' => array_diff_key($metadata, array_flip(['document_type', 'hash', 'file_key', 'file_size'])),
                ];
                $documentItems[] = [
                    'key' => $streamKey,
                    'data' => $docData,
                ];
            }

            // 1. Publish documents with current phase
            $this->multiChain->publishMultiFrom($userAddress, self::STREAM_DOCUMENTS, $documentItems);

            // 2. Log document upload event
            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Notice Of Award',
                'Uploaded ' . count($metadataArray) . " finalized Notice of Award document",
                count($metadataArray),
                $userAddress,
                'document_upload',
                'workflow',
                'info',
                $timestamp
            );

            // 3. Update state in current phase
            $currentStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Awarded',
                'phase_identifier' => 'Notice Of Award',
                'timestamp' => $timestamp,
                'user_address' => $userAddress,
            ];
            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $currentStateData);

            // 4. Log additional publication event
            $publicationTimestamp = now()->addSecond()->toIso8601String();
            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Notice Of Award',
                'Published Notice of Award to PhilGEPS',
                1,
                $userAddress,
                'publication',
                'workflow',
                'info',
                $publicationTimestamp
            );

            // 5. Now perform the phase transition with slight delay to ensure proper ordering
            $newTimestamp = now()->addSeconds(2)->toIso8601String();

            // 6. Transition to next phase with a new explicit state update
            $transitionStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Awarded',
                'phase_identifier' => 'Performance Bond', // Change to next phase
                'timestamp' => $newTimestamp, // Use newer timestamp to ensure this becomes the latest state
                'user_address' => $userAddress,
            ];

            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $transitionStateData);

            // 7. Log the phase transition event
            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Performance Bond', // Use the destination phase
                'Proceeding to Performance Bond phase after recording Notice of Award',
                0,
                $userAddress,
                'phase_transition',
                'workflow',
                'info',
                $newTimestamp
            );

            Log::info('Phase transition completed', [
                'procurement_id' => $procurementId,
                'from_phase' => 'Notice Of Award',
                'to_phase' => 'Performance Bond',
                'current_state' => 'Awarded'
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')->with([
                'success' => true,
                'message' => 'Notice of Award document uploaded and published successfully. Proceeding to Performance Bond phase.'
            ]);

        } catch (Exception $e) {
            Log::error('Error uploading Notice of Award document', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to upload Notice of Award document: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Upload Performance Bond document.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function uploadPerformanceBondDocument(Request $request)
    {
        $procurementId = $request->input('procurement_id');
        $procurementTitle = $request->input('procurement_title');
        $performanceBondFile = $request->file('performance_bond_file');
        $submissionDate = $request->input('submission_date');
        $bondAmount = $request->input('bond_amount');
        $timestamp = now()->toIso8601String();

        try {
            $userAddress = $this->getUserBlockchainAddress();
            $metadataArray = [];

            // Process Performance Bond file
            if ($performanceBondFile) {
                $fileKey = "$procurementId-$procurementTitle/PerformanceBond/$procurementId-$procurementTitle-PerformanceBond." . $performanceBondFile->getClientOriginalExtension();
                Storage::disk('spaces')->put($fileKey, file_get_contents($performanceBondFile), 'private');
                $hash = hash('sha256', file_get_contents($performanceBondFile->getRealPath()));

                $metadataArray[] = [
                    'document_type' => 'Performance Bond',
                    'hash' => $hash,
                    'file_key' => $fileKey,
                    'file_size' => $performanceBondFile->getSize(),
                    'submission_date' => $submissionDate,
                    'bond_amount' => $bondAmount,
                ];
            }

            // Get stream key
            $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

            // Prepare document items for blockchain
            $documentItems = [];
            foreach ($metadataArray as $index => $metadata) {
                $docData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'phase_identifier' => 'Performance Bond', // Use current phase for documents
                    'timestamp' => $timestamp,
                    'document_index' => $index + 1,
                    'document_type' => $metadata['document_type'],
                    'hash' => $metadata['hash'],
                    'file_key' => $metadata['file_key'],
                    'user_address' => $userAddress,
                    'file_size' => $metadata['file_size'],
                    'phase_metadata' => array_diff_key($metadata, array_flip(['document_type', 'hash', 'file_key', 'file_size'])),
                ];
                $documentItems[] = [
                    'key' => $streamKey,
                    'data' => $docData,
                ];
            }

            // 1. Publish documents with current phase
            $this->multiChain->publishMultiFrom($userAddress, self::STREAM_DOCUMENTS, $documentItems);

            // 2. Log document upload event
            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Performance Bond',
                'Uploaded ' . count($metadataArray) . " finalized Performance Bond document",
                count($metadataArray),
                $userAddress,
                'document_upload',
                'workflow',
                'info',
                $timestamp
            );

            // 3. Update state in current phase
            $currentStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Performance Bond Recorded',
                'phase_identifier' => 'Performance Bond',
                'timestamp' => $timestamp,
                'user_address' => $userAddress,
            ];
            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $currentStateData);

            // 4. Now perform the phase transition with slight delay to ensure proper ordering
            $newTimestamp = now()->addSecond()->toIso8601String();

            // 5. Transition to next phase with a new explicit state update
            $transitionStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Performance Bond Recorded',
                'phase_identifier' => 'Contract And PO', // Change to next phase
                'timestamp' => $newTimestamp, // Use newer timestamp to ensure this becomes the latest state
                'user_address' => $userAddress,
            ];

            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $transitionStateData);

            // 6. Log the phase transition event
            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Contract And PO', // Use the destination phase
                'Proceeding to Contract And PO phase after recording Performance Bond',
                0,
                $userAddress,
                'phase_transition',
                'workflow',
                'info',
                $newTimestamp
            );

            Log::info('Phase transition completed', [
                'procurement_id' => $procurementId,
                'from_phase' => 'Performance Bond',
                'to_phase' => 'Contract And PO',
                'current_state' => 'Performance Bond Recorded'
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')->with([
                'success' => true,
                'message' => 'Performance Bond document uploaded successfully. Proceeding to Contract And PO phase.'
            ]);

        } catch (Exception $e) {
            Log::error('Error uploading Performance Bond document', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to upload Performance Bond document: ' . $e->getMessage()
            ]);
        }
    }



    /**
     * Upload Contract and PO documents.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function uploadContractPODocuments(Request $request)
    {
        $procurementId = $request->input('procurement_id');
        $procurementTitle = $request->input('procurement_title');
        $contractFile = $request->file('contract_file');
        $poFile = $request->file('po_file');
        $signingDate = $request->input('signing_date');
        $timestamp = now()->toIso8601String();

        try {
            $userAddress = $this->getUserBlockchainAddress();
            $metadataArray = [];

            // Process Contract file
            if ($contractFile) {
                $fileKey = "$procurementId-$procurementTitle/ContractPO/$procurementId-$procurementTitle-Contract." . $contractFile->getClientOriginalExtension();
                Storage::disk('spaces')->put($fileKey, file_get_contents($contractFile), 'private');
                $hash = hash('sha256', file_get_contents($contractFile->getRealPath()));

                $metadataArray[] = [
                    'document_type' => 'Contract',
                    'hash' => $hash,
                    'file_key' => $fileKey,
                    'file_size' => $contractFile->getSize(),
                    'signing_date' => $signingDate,
                ];
            }

            // Process Purchase Order file
            if ($poFile) {
                $fileKey = "$procurementId-$procurementTitle/ContractPO/$procurementId-$procurementTitle-PurchaseOrder." . $poFile->getClientOriginalExtension();
                Storage::disk('spaces')->put($fileKey, file_get_contents($poFile), 'private');
                $hash = hash('sha256', file_get_contents($poFile->getRealPath()));

                $metadataArray[] = [
                    'document_type' => 'PO',
                    'hash' => $hash,
                    'file_key' => $fileKey,
                    'file_size' => $poFile->getSize(),
                    'signing_date' => $signingDate,
                ];
            }

            // Get stream key
            $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

            // Prepare document items for blockchain
            $documentItems = [];
            foreach ($metadataArray as $index => $metadata) {
                $docData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'phase_identifier' => 'Contract And PO',
                    'timestamp' => $timestamp,
                    'document_index' => $index + 1,
                    'document_type' => $metadata['document_type'],
                    'hash' => $metadata['hash'],
                    'file_key' => $metadata['file_key'],
                    'user_address' => $userAddress,
                    'file_size' => $metadata['file_size'],
                    'phase_metadata' => array_diff_key($metadata, array_flip(['document_type', 'hash', 'file_key', 'file_size'])),
                ];
                $documentItems[] = [
                    'key' => $streamKey,
                    'data' => $docData,
                ];
            }

            // 1. Publish documents with current phase
            $this->multiChain->publishMultiFrom($userAddress, self::STREAM_DOCUMENTS, $documentItems);

            // 2. Log document upload event
            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Contract And PO',
                'Uploaded ' . count($metadataArray) . " finalized contract and PO documents",
                count($metadataArray),
                $userAddress,
                'document_upload',
                'workflow',
                'info',
                $timestamp
            );

            // 3. Update state in current phase
            $currentStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Contract And PO Recorded',
                'phase_identifier' => 'Contract And PO',
                'timestamp' => $timestamp,
                'user_address' => $userAddress,
            ];
            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $currentStateData);

            // 4. Now perform the phase transition with slight delay to ensure proper ordering
            $newTimestamp = now()->addSecond()->toIso8601String();

            // 5. Transition to next phase with a new explicit state update
            $transitionStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Contract And PO Recorded',
                'phase_identifier' => 'Notice To Proceed', // Change to next phase
                'timestamp' => $newTimestamp, // Use newer timestamp to ensure this becomes the latest state
                'user_address' => $userAddress,
            ];

            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $transitionStateData);

            // 6. Log the phase transition event
            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Notice To Proceed', // Use the destination phase
                'Proceeding to Notice To Proceed phase after recording Contract and PO',
                0,
                $userAddress,
                'phase_transition',
                'workflow',
                'info',
                $newTimestamp
            );

            Log::info('Phase transition completed', [
                'procurement_id' => $procurementId,
                'from_phase' => 'Contract And PO',
                'to_phase' => 'Notice To Proceed',
                'current_state' => 'Contract And PO Recorded'
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')->with([
                'success' => true,
                'message' => 'Contract and PO documents uploaded successfully. Proceeding to Notice To Proceed phase.'
            ]);

        } catch (Exception $e) {
            Log::error('Error uploading Contract and PO documents', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to upload Contract and PO documents: ' . $e->getMessage()
            ]);
        }
    }



    /**
     * Upload Notice to Proceed document.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function uploadNTPDocument(Request $request)
    {
        $procurementId = $request->input('procurement_id');
        $procurementTitle = $request->input('procurement_title');
        $ntpFile = $request->file('ntp_file');
        $issuanceDate = $request->input('issuance_date');
        $timestamp = now()->toIso8601String();

        try {
            $userAddress = $this->getUserBlockchainAddress();
            $metadataArray = [];

            // Process NTP file
            if ($ntpFile) {
                $fileKey = "$procurementId-$procurementTitle/NTP/$procurementId-$procurementTitle-NTP." . $ntpFile->getClientOriginalExtension();
                Storage::disk('spaces')->put($fileKey, file_get_contents($ntpFile), 'private');
                $hash = hash('sha256', file_get_contents($ntpFile->getRealPath()));

                $metadataArray[] = [
                    'document_type' => 'Notice to Proceed',
                    'hash' => $hash,
                    'file_key' => $fileKey,
                    'file_size' => $ntpFile->getSize(),
                    'issuance_date' => $issuanceDate,
                ];
            }

            // Get stream key
            $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

            // Prepare document items for blockchain
            $documentItems = [];
            foreach ($metadataArray as $index => $metadata) {
                $docData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'phase_identifier' => 'Notice To Proceed',
                    'timestamp' => $timestamp,
                    'document_index' => $index + 1,
                    'document_type' => $metadata['document_type'],
                    'hash' => $metadata['hash'],
                    'file_key' => $metadata['file_key'],
                    'user_address' => $userAddress,
                    'file_size' => $metadata['file_size'],
                    'phase_metadata' => array_diff_key($metadata, array_flip(['document_type', 'hash', 'file_key', 'file_size'])),
                ];
                $documentItems[] = [
                    'key' => $streamKey,
                    'data' => $docData,
                ];
            }

            // 1. Publish documents with current phase
            $this->multiChain->publishMultiFrom($userAddress, self::STREAM_DOCUMENTS, $documentItems);

            // 2. Log document upload event
            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Notice To Proceed',
                'Uploaded ' . count($metadataArray) . " finalized NTP",
                count($metadataArray),
                $userAddress,
                'document_upload',
                'workflow',
                'info',
                $timestamp
            );

            // 3. Update state in current phase
            $currentStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'NTP Recorded',
                'phase_identifier' => 'Notice To Proceed',
                'timestamp' => $timestamp,
                'user_address' => $userAddress,
            ];
            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $currentStateData);

            // 4. Log publication event
            $publicationTimestamp = now()->addSecond()->toIso8601String();
            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Notice To Proceed',
                'Published NTP to PhilGEPS',
                1,
                $userAddress,
                'publication',
                'workflow',
                'info',
                $publicationTimestamp
            );

            // 5. Now perform the phase transition with slight delay to ensure proper ordering
            $newTimestamp = now()->addSeconds(2)->toIso8601String();

            // 6. Transition to next phase with a new explicit state update
            $transitionStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'NTP Recorded',
                'phase_identifier' => 'Monitoring', // Change to next phase
                'timestamp' => $newTimestamp, // Use newer timestamp to ensure this becomes the latest state
                'user_address' => $userAddress,
            ];

            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $transitionStateData);

            // 7. Log the phase transition event
            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Monitoring', // Use the destination phase
                'Proceeding to Monitoring phase after recording NTP',
                0,
                $userAddress,
                'phase_transition',
                'workflow',
                'info',
                $newTimestamp
            );

            Log::info('Phase transition completed', [
                'procurement_id' => $procurementId,
                'from_phase' => 'Notice To Proceed',
                'to_phase' => 'Monitoring',
                'current_state' => 'NTP Recorded'
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')->with([
                'success' => true,
                'message' => 'Notice to Proceed document uploaded and published successfully. Proceeding to Monitoring phase.'
            ]);

        } catch (Exception $e) {
            Log::error('Error uploading Notice to Proceed document', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to upload Notice to Proceed document: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Upload Monitoring document.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function uploadMonitoringDocument(Request $request)
    {
        $procurementId = $request->input('procurement_id');
        $procurementTitle = $request->input('procurement_title');
        $complianceFile = $request->file('compliance_file');
        $reportDate = $request->input('report_date');
        $reportNotes = $request->input('report_notes');
        $timestamp = now()->toIso8601String();

        try {
            $userAddress = $this->getUserBlockchainAddress();
            $metadataArray = [];

            // Process compliance report file
            if ($complianceFile) {
                $fileKey = "$procurementId-$procurementTitle/Monitoring/$procurementId-$procurementTitle-ComplianceReport." . $complianceFile->getClientOriginalExtension();
                Storage::disk('spaces')->put($fileKey, file_get_contents($complianceFile), 'private');
                $hash = hash('sha256', file_get_contents($complianceFile->getRealPath()));

                $metadataArray[] = [
                    'document_type' => 'Compliance Report',
                    'hash' => $hash,
                    'file_key' => $fileKey,
                    'file_size' => $complianceFile->getSize(),
                    'report_date' => $reportDate,
                    'report_notes' => $reportNotes,
                ];
            }

            // Get stream key
            $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

            // Prepare document items for blockchain
            $documentItems = [];
            foreach ($metadataArray as $index => $metadata) {
                $docData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'phase_identifier' => 'Monitoring',
                    'timestamp' => $timestamp,
                    'document_index' => $index + 1,
                    'document_type' => $metadata['document_type'],
                    'hash' => $metadata['hash'],
                    'file_key' => $metadata['file_key'],
                    'user_address' => $userAddress,
                    'file_size' => $metadata['file_size'],
                    'phase_metadata' => array_diff_key($metadata, array_flip(['document_type', 'hash', 'file_key', 'file_size'])),
                ];
                $documentItems[] = [
                    'key' => $streamKey,
                    'data' => $docData,
                ];
            }

            // 1. Publish documents with current phase
            $this->multiChain->publishMultiFrom($userAddress, self::STREAM_DOCUMENTS, $documentItems);

            // 2. Log document upload event
            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Monitoring',
                'Uploaded ' . count($metadataArray) . " finalized compliance report",
                count($metadataArray),
                $userAddress,
                'document_upload',
                'workflow',
                'info',
                $timestamp
            );

            // 3. Update state to confirm monitoring state
            $currentStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Monitoring',
                'phase_identifier' => 'Monitoring',
                'timestamp' => $timestamp,
                'user_address' => $userAddress,
            ];
            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $currentStateData);

            Log::info('Monitoring document uploaded', [
                'procurement_id' => $procurementId,
                'document_count' => count($metadataArray)
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')->with([
                'success' => true,
                'message' => 'Compliance report uploaded successfully. Notifications sent to BAC Chairman and HOPE.'
            ]);

        } catch (Exception $e) {
            Log::error('Error uploading compliance report', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to upload compliance report: ' . $e->getMessage()
            ]);
        }
    }
}
