<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Services\MultiChainService;

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

    private function handlePhaseTransition($procurementId, $procurementTitle, $fromState, $toState, $fromPhase, $toPhase, $userAddress, $details)
    {
        Log::info('Phase transition beginning', [
            'procurement_id' => $procurementId,
            'from_phase' => $fromPhase,
            'to_phase' => $toPhase,
            'from_state' => $fromState,
            'to_state' => $toState
        ]);

        $timestamp = now()->toIso8601String();
        $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

        $stateData = [
            'procurement_id' => $procurementId,
            'procurement_title' => $procurementTitle,
            'current_state' => $toState,
            'phase_identifier' => $toPhase,
            'timestamp' => $timestamp,
            'user_address' => $userAddress,
        ];

        $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $stateData);

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

            return redirect()->route('bac-secretariat.procurements-list.index')->with([
                'success' => true,
                'message' => 'Documents published successfully',
                'procurementId' => $procurementId,
                'procurementTitle' => $procurementTitle,
                'documentCount' => count($metadataArray),
                'timestamp' => $timestamp
            ]);
        } catch (Exception $e) {
            return redirect()->back()->withErrors([
                'error' => 'Failed to publish documents: ' . $e->getMessage()
            ]);
        }
    }

    public function publishPreProcurementDecision(Request $request)
    {
        $procurementId = $request->input('procurement_id');
        $procurementTitle = $request->input('procurement_title');
        $conferenceHeld = $request->boolean('conference_held');
        $timestamp = now()->toIso8601String();

        try {
            $userAddress = $this->getUserBlockchainAddress();
            $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

            if ($conferenceHeld) {
                // Update state for conference held
                $stateData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'current_state' => 'Pre-Procurement Conference Held',
                    'phase_identifier' => 'Pre-Procurement',
                    'timestamp' => $timestamp,
                    'user_address' => $userAddress,
                ];
                $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $stateData);

                // Log the event
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

                $message = 'Pre-procurement conference recorded as held. Please upload the conference documents.';
                $nextPhase = 'Pre-Procurement';
                $documentsRequired = true;
            } else {
                // Handle transition to next phase if conference was skipped
                $this->handlePhaseTransition(
                    $procurementId,
                    $procurementTitle,
                    'PR Submitted',
                    'Pre-Procurement Skipped',
                    'PR Initiation',
                    'Bid Invitation',
                    $userAddress,
                    'Pre-procurement conference skipped - proceeding to Bid Invitation'
                );

                $message = 'Pre-procurement conference skipped. Proceeding to Bid Invitation phase.';
                $nextPhase = 'Bid Invitation';
                $documentsRequired = false;
            }

            // Return with appropriate flash messages
            return redirect()->route('bac-secretariat.procurements-list.index')->with([
                'success' => true,
                'message' => $message,
                'nextPhase' => $nextPhase,
                'procurementId' => $procurementId,
                'procurementTitle' => $procurementTitle,
                'documentsRequired' => $documentsRequired
            ]);
        } catch (Exception $e) {
            Log::error('Error processing pre-procurement decision', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to process pre-procurement decision: ' . $e->getMessage()
            ]);
        }
    }

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

            $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

            $documentItems = [];
            foreach ($metadataArray as $index => $metadata) {
                $docData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'phase_identifier' => 'Pre-Procurement',
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

            $intermediateStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Pre-Procurement Completed',
                'phase_identifier' => 'Pre-Procurement',
                'timestamp' => $timestamp,
                'user_address' => $userAddress,
            ];
            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $intermediateStateData);

            $newTimestamp = now()->addSecond()->toIso8601String();

            $transitionStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Pre-Procurement Completed',
                'phase_identifier' => 'Bid Invitation',
                'timestamp' => $newTimestamp,
                'user_address' => $userAddress,
            ];

            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $transitionStateData);

            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Bid Invitation',
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

            $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

            $documentItems = [];
            foreach ($metadataArray as $index => $metadata) {
                $docData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'phase_identifier' => 'Bid Invitation',
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

            $currentStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Bid Invitation Published',
                'phase_identifier' => 'Bid Invitation',
                'timestamp' => $timestamp,
                'user_address' => $userAddress,
            ];
            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $currentStateData);

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

            $newTimestamp = now()->addSecond()->toIso8601String();

            $transitionStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Bid Invitation Published',
                'phase_identifier' => 'Bid Opening',
                'timestamp' => $newTimestamp,
                'user_address' => $userAddress,
            ];

            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $transitionStateData);

            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Bid Opening',
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

            if (count($metadataArray) > 0) {
                $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

                $documentItems = [];
                foreach ($metadataArray as $index => $metadata) {
                    $docData = [
                        'procurement_id' => $procurementId,
                        'procurement_title' => $procurementTitle,
                        'phase_identifier' => 'Bid Opening',
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

                $currentStateData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'current_state' => 'Bids Opened',
                    'phase_identifier' => 'Bid Opening',
                    'timestamp' => $timestamp,
                    'user_address' => $userAddress,
                ];
                $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $currentStateData);

                $newTimestamp = now()->addSecond()->toIso8601String();

                $transitionStateData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'current_state' => 'Bids Opened',
                    'phase_identifier' => 'Bid Evaluation',
                    'timestamp' => $newTimestamp,
                    'user_address' => $userAddress,
                ];

                $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $transitionStateData);

                $this->logEvent(
                    $procurementId,
                    $procurementTitle,
                    'Bid Evaluation',
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

            $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

            $documentItems = [];
            foreach ($metadataArray as $index => $metadata) {
                $docData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'phase_identifier' => 'Bid Evaluation',
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

            $currentStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Bids Evaluated',
                'phase_identifier' => 'Bid Evaluation',
                'timestamp' => $timestamp,
                'user_address' => $userAddress,
            ];
            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $currentStateData);

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

            usleep(500000);

            $newTimestamp = now()->toIso8601String();

            $transitionStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Bids Evaluated',
                'phase_identifier' => 'Post-Qualification',
                'timestamp' => $newTimestamp,
                'user_address' => $userAddress,
            ];

            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $transitionStateData);

            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Post-Qualification',
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

            $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

            $documentItems = [];
            foreach ($metadataArray as $index => $metadata) {
                $docData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'phase_identifier' => 'Post-Qualification',
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

            $newTimestamp = now()->addSecond()->toIso8601String();

            if ($outcome === 'Verified') {
                $transitionStateData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'current_state' => $verifiedState,
                    'phase_identifier' => 'BAC Resolution',
                    'timestamp' => $newTimestamp,
                    'user_address' => $userAddress,
                ];

                $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $transitionStateData);

                $this->logEvent(
                    $procurementId,
                    $procurementTitle,
                    'BAC Resolution',
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

            $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

            $documentItems = [];
            foreach ($metadataArray as $index => $metadata) {
                $docData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'phase_identifier' => 'BAC Resolution',
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

            $currentStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Resolution Recorded',
                'phase_identifier' => 'BAC Resolution',
                'timestamp' => $timestamp,
                'user_address' => $userAddress,
            ];
            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $currentStateData);

            $newTimestamp = now()->addSecond()->toIso8601String();

            $transitionStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Resolution Recorded',
                'phase_identifier' => 'Notice Of Award',
                'timestamp' => $newTimestamp,
                'user_address' => $userAddress,
            ];

            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $transitionStateData);

            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Notice Of Award',
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

            $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

            $documentItems = [];
            foreach ($metadataArray as $index => $metadata) {
                $docData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'phase_identifier' => 'Notice Of Award',
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

            $currentStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Awarded',
                'phase_identifier' => 'Notice Of Award',
                'timestamp' => $timestamp,
                'user_address' => $userAddress,
            ];
            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $currentStateData);

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

            $newTimestamp = now()->addSeconds(2)->toIso8601String();

            $transitionStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Awarded',
                'phase_identifier' => 'Performance Bond',
                'timestamp' => $newTimestamp,
                'user_address' => $userAddress,
            ];

            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $transitionStateData);

            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Performance Bond',
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

            $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

            $documentItems = [];
            foreach ($metadataArray as $index => $metadata) {
                $docData = [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'phase_identifier' => 'Performance Bond',
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

            $currentStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Performance Bond Recorded',
                'phase_identifier' => 'Performance Bond',
                'timestamp' => $timestamp,
                'user_address' => $userAddress,
            ];
            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $currentStateData);

            $newTimestamp = now()->addSecond()->toIso8601String();

            $transitionStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Performance Bond Recorded',
                'phase_identifier' => 'Contract And PO',
                'timestamp' => $newTimestamp,
                'user_address' => $userAddress,
            ];

            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $transitionStateData);

            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Contract And PO',
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

            $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

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

            $this->multiChain->publishMultiFrom($userAddress, self::STREAM_DOCUMENTS, $documentItems);

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

            $currentStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Contract And PO Recorded',
                'phase_identifier' => 'Contract And PO',
                'timestamp' => $timestamp,
                'user_address' => $userAddress,
            ];
            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $currentStateData);

            $newTimestamp = now()->addSecond()->toIso8601String();

            $transitionStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'Contract And PO Recorded',
                'phase_identifier' => 'Notice To Proceed',
                'timestamp' => $newTimestamp,
                'user_address' => $userAddress,
            ];

            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $transitionStateData);

            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Notice To Proceed',
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

            $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

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

            $this->multiChain->publishMultiFrom($userAddress, self::STREAM_DOCUMENTS, $documentItems);

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

            $currentStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'NTP Recorded',
                'phase_identifier' => 'Notice To Proceed',
                'timestamp' => $timestamp,
                'user_address' => $userAddress,
            ];
            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $currentStateData);

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

            $newTimestamp = now()->addSeconds(2)->toIso8601String();

            $transitionStateData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'current_state' => 'NTP Recorded',
                'phase_identifier' => 'Monitoring',
                'timestamp' => $newTimestamp,
                'user_address' => $userAddress,
            ];

            $this->multiChain->publishFrom($userAddress, self::STREAM_STATE, $streamKey, $transitionStateData);

            $this->logEvent(
                $procurementId,
                $procurementTitle,
                'Monitoring',
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

            $streamKey = $this->getStreamKey($procurementId, $procurementTitle);

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

            $this->multiChain->publishMultiFrom($userAddress, self::STREAM_DOCUMENTS, $documentItems);

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
