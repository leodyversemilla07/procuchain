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
            $userAddress = "15rdrfaw6xydP81YhJkSXXuHR4T7vFviung6NW";

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

            $this->publishDocuments($procurementId, $procurementTitle, 'PRInitiation', 'PR Initiation', 'PR Submitted', $metadataArray, $userAddress);

            // Return JSON response for success case
            return response()->json([
                'success' => true,
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

    public function show(Request $request, $procurementId)
    {
        try {
            // Get all states for this procurement ID to find the latest one
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            // Filter to get only states for this procurement
            $procurementStates = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];
                    return [
                        'item' => $item,
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? ''
                    ];
                })
                ->filter(function ($mappedItem) use ($procurementId) {
                    return $mappedItem['procurementId'] === $procurementId;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['timestamp'] ?? '';
                });

            if ($procurementStates->isEmpty()) {
                return Inertia::render('bac-secretariat/show', ['message' => 'Procurement not found']);
            }

            // Get the latest state
            $latestState = $procurementStates->first();
            $procurementTitle = $latestState['data']['procurement_title'] ?? '';
            $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

            // Get documents for this procurement
            $documents = $this->multiChain->listStreamKeyItems(self::STREAM_DOCUMENTS, $streamKey);
            $parsedDocuments = collect($documents)->map(function ($doc) {
                $data = $doc['data'];

                // Generate spaces URL
                $spaces_url = '';
                if (isset($data['file_key'])) {
                    $spaces_url = Storage::disk('spaces')->temporaryUrl($data['file_key'], now()->addMinutes(30));
                }

                return [
                    'procurement_id' => $data['procurement_id'] ?? '',
                    'procurement_title' => $data['procurement_title'] ?? '',
                    'user_address' => $data['user_address'] ?? '',
                    'timestamp' => $data['timestamp'] ?? '',
                    'phase_identifier' => $data['phase_identifier'] ?? '',
                    'document_index' => $data['document_index'] ?? 0,
                    'document_type' => $data['document_type'] ?? '',
                    'hash' => $data['hash'] ?? '',
                    'file_key' => $data['file_key'] ?? '',
                    'file_size' => $data['file_size'] ?? 0,
                    'phase_metadata' => $data['phase_metadata'] ?? [],
                    'spaces_url' => $spaces_url
                ];
            })->toArray();

            // Format state according to BlockchainProcurementState interface
            $procurementState = [
                'procurement_id' => $latestState['data']['procurement_id'] ?? '',
                'procurement_title' => $latestState['data']['procurement_title'] ?? '',
                'user_address' => $latestState['data']['user_address'] ?? '',
                'timestamp' => $latestState['data']['timestamp'] ?? '',
                'phase_identifier' => $latestState['data']['phase_identifier'] ?? '',
                'current_state' => $latestState['data']['current_state'] ?? ''
            ];

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
                    'document_count' => $data['document_count'] ?? 0
                ];
            })->sortByDesc('timestamp')->values()->toArray();

            $procurement = [
                'id' => $procurementId,
                'title' => $procurementTitle,
                'documents' => $parsedDocuments,
                'state' => $procurementState,
                'events' => $parsedEvents,
                'raw_state' => $latestState,
                'raw_documents' => $documents,
                'raw_events' => $events
            ];

            return Inertia::render('bac-secretariat/show', ['procurement' => $procurement]);

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
}
