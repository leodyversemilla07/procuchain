<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Http\Controllers\Procurement\Concerns\HasProcurementSupport;
use App\Http\Requests\Procurement\PreBidConferenceDecisionRequest;
use App\Http\Requests\Procurement\PreProcurementConferenceDecisionRequest;
use App\Http\Requests\Procurement\SupplementalBidBulletinDecisionRequest;
use App\Libraries\MultiChain\Manager;
use App\Services\DocumentValidationService;
use App\Services\ProcurementDataService;
use App\Services\Publishers\DocumentPublisher;
use App\Services\Publishers\EventPublisher;
use App\Services\Publishers\ProcurementOrchestrator;
use App\Services\Publishers\StatusPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles Pre-Procurement Phase (Stages 2-3):
 * - Stage 2: Pre-Procurement Conference
 * - Stage 3: Bidding Documents
 *
 * Note: Stage 1 (Procurement Initiation) is handled by ProcurementInitiationController
 */
class PreProcurementController extends BaseController
{
    use HasProcurementSupport;

    public function __construct(
        Manager $multichain,
        DocumentPublisher $documentPublisher,
        StatusPublisher $statusPublisher,
        EventPublisher $eventPublisher,
        ProcurementDataService $procurementDataService,
        \App\Repositories\DocumentRepository $documentRepository,
        protected DocumentValidationService $validationService,
        protected ProcurementOrchestrator $orchestrator
    ) {
        $this->initializeProcurementSupport($multichain, $documentPublisher, $statusPublisher, $eventPublisher, $procurementDataService, $documentRepository);
        $this->applyProcurementMiddleware();
    }

    /**
     * Display the upload page for a specific pre-procurement stage.
     */
    public function show(Request $request, string $pr_number, StageEnums $stage): Response
    {
        // Validate that stage is in pre-procurement phase
        if (! $stage->isPreProcurement()) {
            abort(403, 'Invalid stage for Pre-Procurement phase');
        }

        $procurement = $this->findProcurementById($pr_number);

        // Determine which Inertia component to render based on stage
        $component = match ($stage) {
            StageEnums::PRE_PROCUREMENT_CONFERENCE => 'bac-secretariat/procurement-stage/pre-procurement-conference-upload',
            StageEnums::BIDDING_DOCUMENTS => 'bac-secretariat/procurement-stage/bidding-documents-upload',
            default => abort(404, 'Stage component not found'),
        };

        return Inertia::render($component, [
            'procurement' => [
                'pr_number' => $pr_number,
                'title' => $procurement['procurement_title'] ?? '',
                'status' => $procurement['current_status'] ?? '',
                'stage' => $stage->getDisplayName(),
                'stage_value' => $stage->value,
                'current_stage' => $procurement['stage'] ?? '',
            ],
            'documentGuide' => $this->validationService->getStageDocumentGuide($stage),
            'uploadedDocuments' => fn () => $this->getUploadedDocumentTypes($pr_number, $stage),
        ]);
    }

    /**
     * Upload documents for a specific pre-procurement stage.
     */
    public function uploadDocuments(Request $request, string $pr_number, StageEnums $stage): RedirectResponse
    {
        // Validate that stage is in pre-procurement phase
        if (! $stage->isPreProcurement()) {
            abort(403, 'Invalid stage for Pre-Procurement phase');
        }

        $user = auth()->user();
        $userAddress = $user->blockchain_address;

        $uploadedFiles = $request->allFiles();
        $metadata = $request->except(['_token', 'pr_number']);

        // Fetch already uploaded documents for this stage
        $existingDocuments = $this->getUploadedDocumentTypes($pr_number, $stage);
        $existingDocumentEnums = array_filter(
            array_map(fn ($docType) => DocumentTypeEnums::tryFrom($docType), $existingDocuments),
            fn ($enum) => $enum !== null
        );

        try {
            // Validate each uploaded document
            foreach ($uploadedFiles as $fieldName => $file) {
                // Extract document type from field name (e.g., 'ppmp_file' -> 'ppmp')
                $documentTypeKey = str_replace('_file', '', $fieldName);
                $documentType = $this->resolveDocumentType($documentTypeKey, $stage);

                $validation = $this->validationService->validateUpload(
                    $stage,
                    $documentType,
                    $existingDocumentEnums
                );

                if (! empty($validation['errors'])) {
                    return redirect()->back()->withErrors([
                        $fieldName => implode(' ', $validation['errors']),
                    ]);
                }

                // Publish document to blockchain
                $procurement = app(\App\Repositories\ProcurementRepository::class)->findByProcurement($pr_number);
                $procurementTitle = $procurement?->title ?? 'Unknown';

                // Publish document workflow to blockchain (document + status + event)
                $this->orchestrator->publishDocumentWorkflow(
                    procurementData: [
                        'pr_number' => $pr_number,
                        'procurement_title' => $procurement->title,
                        'user_address' => $userAddress,
                    ],
                    file: $file,
                    documentData: [
                        'stage' => $stage,
                        'status' => $procurement->status,
                        'document_type' => $documentType,
                        'uploaded_by' => Auth::user()->name,
                        'description' => $request->input('description'),
                        'stage_metadata' => $metadata,
                    ],
                    statusData: [
                        'stage' => $stage,
                        'current_status' => StatusEnums::tryFrom($procurement->status) ?? StatusEnums::PROCUREMENT_SUBMITTED,
                        'metadata' => [
                            'documents_uploaded' => 1,
                            'uploaded_at' => now()->toIso8601String(),
                        ],
                    ],
                    eventData: [
                        'stage' => $stage->value,
                        'event_type' => 'document_uploaded',
                        'category' => 'procurement',
                        'severity' => 'info',
                        'details' => sprintf(
                            'Document "%s" uploaded to stage "%s"',
                            $documentType->getDisplayName(),
                            $stage->getDisplayName()
                        ),
                        'document_count' => 1,
                    ]
                );
            }

            // Refresh uploaded documents after new uploads
            $uploadedDocuments = $this->getUploadedDocumentTypes($pr_number, $stage);
            $uploadedDocumentEnums = array_filter(
                array_map(fn ($docType) => DocumentTypeEnums::tryFrom($docType), $uploadedDocuments),
                fn ($enum) => $enum !== null
            );

            // Check if stage is ready for completion
            $completionCheck = $this->validationService->validateStageCompletion($stage, $uploadedDocumentEnums);

            if ($completionCheck['can_complete']) {
                // Determine next stage
                $nextStage = $this->getNextStage($stage);

                // Publish stage transition
                if ($nextStage) {
                    $procurement = $this->findProcurementById($pr_number);
                    $this->statusPublisher->publishTransition(
                        $pr_number,
                        $procurement['title'] ?? 'Unknown',
                        $stage,
                        $nextStage,
                        \App\Enums\StatusEnums::PROCUREMENT_SUBMITTED,
                        $userAddress
                    );

                    $this->eventPublisher->publishStageTransition(
                        $pr_number,
                        $procurementTitle,
                        $stage->value,
                        $nextStage->value,
                        $userAddress
                    );
                }

                return redirect()->route('bac-secretariat.procurements.index')
                    ->with('success', 'Documents uploaded and stage completed successfully. Publishing to blockchain in the background.');
            }

            return redirect()->back()->with('success', 'Documents uploaded successfully. Please upload remaining required documents.');
        } catch (\Exception $e) {
            \Log::error('Failed to upload pre-procurement documents', [
                'pr_number' => $pr_number,
                'stage' => $stage->value,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to upload documents to blockchain. Please try again.',
            ]);
        }
    }

    /**
     * Upload a single document for progressive upload workflow.
     */
    public function uploadSingleDocument(
        \App\Http\Requests\Procurement\UploadSingleDocumentRequest $request,
        string $pr_number,
        StageEnums $stage
    ): \Illuminate\Http\RedirectResponse {
        // Validate that stage is in pre-procurement phase
        if (! $stage->isPreProcurement()) {
            return back()->withErrors(['message' => 'Invalid stage for Pre-Procurement phase']);
        }

        $user = auth()->user();
        $userAddress = $user->blockchain_address;

        try {
            $file = $request->file('document_file');
            $documentTypeValue = $request->input('document_type');
            $documentType = DocumentTypeEnums::tryFrom($documentTypeValue);

            if (! $documentType) {
                return back()->withErrors(['document_type' => 'Invalid document type provided']);
            }

            // Fetch already uploaded documents for this stage
            $existingDocuments = $this->getUploadedDocumentTypes($pr_number, $stage);
            $existingDocumentEnums = array_filter(
                array_map(fn ($docType) => DocumentTypeEnums::tryFrom($docType), $existingDocuments),
                fn ($enum) => $enum !== null
            );

            // Validate the single document upload
            $validation = $this->validationService->validateUpload(
                $stage,
                $documentType,
                $existingDocumentEnums
            );

            if (! empty($validation['errors'])) {
                return back()->withErrors(['message' => implode(' ', $validation['errors'])]);
            }

            // Get procurement details
            $procurement = app(\App\Repositories\ProcurementRepository::class)->findByProcurement($pr_number);
            if (! $procurement) {
                return back()->withErrors(['message' => 'Procurement not found']);
            }

            // Publish document workflow to blockchain
            $result = $this->orchestrator->publishDocumentWorkflow(
                procurementData: [
                    'pr_number' => $pr_number,
                    'procurement_title' => $procurement->title,
                    'user_address' => $userAddress,
                ],
                file: $file,
                documentData: [
                    'stage' => $stage,
                    'status' => $procurement->status,
                    'document_type' => $documentType,
                    'uploaded_by' => $user->name,
                    'description' => $request->input('description'),
                    'stage_metadata' => $request->input('metadata', []),
                ],
                statusData: [
                    'stage' => $stage,
                    'current_status' => StatusEnums::tryFrom($procurement->status) ?? StatusEnums::PROCUREMENT_SUBMITTED,
                    'metadata' => [
                        'documents_uploaded' => 1,
                        'uploaded_at' => now()->toIso8601String(),
                        'progressive_upload' => true,
                    ],
                ],
                eventData: [
                    'stage' => $stage->value,
                    'event_type' => 'document_uploaded',
                    'category' => 'procurement',
                    'severity' => 'info',
                    'details' => sprintf(
                        'Document "%s" uploaded to stage "%s" (progressive upload)',
                        $documentType->getDisplayName(),
                        $stage->getDisplayName()
                    ),
                    'document_count' => 1,
                ]
            );

            if (! $result['success']) {
                return back()->withErrors(['message' => 'Failed to upload document to blockchain']);
            }

            // Refresh uploaded documents after new upload
            $uploadedDocuments = $this->getUploadedDocumentTypes($pr_number, $stage);
            $uploadedDocumentEnums = array_filter(
                array_map(fn ($docType) => DocumentTypeEnums::tryFrom($docType), $uploadedDocuments),
                fn ($enum) => $enum !== null
            );

            // Check stage completion status
            $completionCheck = $this->validationService->validateStageCompletion($stage, $uploadedDocumentEnums);

            return back()->with('success', sprintf(
                'Document "%s" uploaded successfully',
                $documentType->getDisplayName()
            ));
        } catch (\Exception $e) {
            \Log::error('Failed to upload single document', [
                'pr_number' => $pr_number,
                'stage' => $stage->value,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['message' => 'An error occurred while uploading the document']);
        }
    }

    /**
     * Get document upload guide for a specific stage (API endpoint for frontend).
     */
    public function documentGuide(Request $request, string $pr_number, StageEnums $stage): JsonResponse
    {
        if (! $stage->isPreProcurement()) {
            abort(403, 'Invalid stage for Pre-Procurement phase');
        }

        $guide = $this->validationService->getStageDocumentGuide($stage);

        return response()->json($guide);
    }

    /**
     * Mark a procurement stage as complete.
     */
    public function markStageComplete(Request $request, string $pr_number, StageEnums $stage): RedirectResponse
    {
        if (! $stage->isPreProcurement()) {
            abort(403, 'Invalid stage for Pre-Procurement phase');
        }

        try {
            // Verify all required documents are uploaded
            $uploadedDocuments = $this->getUploadedDocumentTypes($pr_number, $stage);
            $documentGuide = $this->validationService->getStageDocumentGuide($stage);

            if (count($uploadedDocuments) < $documentGuide['counts']['required_count']) {
                return back()->with('error', 'Cannot mark stage as complete. Please upload all required documents first.');
            }

            // Get procurement data
            $procurement = app(\App\Repositories\ProcurementRepository::class)->findByProcurement($pr_number);
            if (! $procurement) {
                return back()->with('error', 'Procurement not found.');
            }

            $user = auth()->user();
            $userAddress = $user->blockchain_address ?? $user->email;

            // 1. Determine completion status based on stage
            $completionStatus = $this->getCompletionStatusForStage($stage);

            // 2. Publish status update to blockchain
            $statusResult = $this->statusPublisher->publish(
                prNumber: $pr_number,
                procurementTitle: $procurement->title,
                stage: $stage,
                currentStatus: $completionStatus,
                userAddress: $userAddress,
                previousStatus: null,
                metadata: [
                    'documents_uploaded' => count($uploadedDocuments),
                    'marked_complete_at' => now()->toIso8601String(),
                ]
            );

            // 3. Publish completion event to blockchain
            $eventResult = $this->eventPublisher->publish(
                prNumber: $pr_number,
                procurementTitle: $procurement->title,
                stage: $stage->value,
                eventType: 'stage_completed',
                category: 'stage_transition',
                severity: 'info',
                details: "Stage {$stage->getDisplayName()} marked as complete with all required documents uploaded.",
                documentCount: count($uploadedDocuments),
                userAddress: $userAddress,
                metadata: [
                    'stage' => $stage->value,
                    'completion_status' => $completionStatus->value,
                ]
            );

            // 4. Handle automatic stage transitions for specific stages
            if ($stage === StageEnums::BIDDING_DOCUMENTS) {
                // Automatically transition to PRE_BID_CONFERENCE stage
                $this->statusPublisher->publishTransition(
                    prNumber: $pr_number,
                    procurementTitle: $procurement->title,
                    fromStage: StageEnums::BIDDING_DOCUMENTS,
                    toStage: StageEnums::PRE_BID_CONFERENCE,
                    currentStatus: StatusEnums::BIDDING_DOCUMENTS_PUBLISHED,
                    userAddress: $userAddress
                );

                $this->eventPublisher->publishStageTransition(
                    prNumber: $pr_number,
                    procurementTitle: $procurement->title,
                    fromStage: StageEnums::BIDDING_DOCUMENTS->value,
                    toStage: StageEnums::PRE_BID_CONFERENCE->value,
                    userAddress: $userAddress
                );

                return back()->with('success', [
                    'message' => 'Bidding Documents marked as complete successfully! Proceeding to Pre-Bid Conference stage.',
                    'blockchain' => [
                        'status_txid' => $statusResult['status_txid'] ?? null,
                        'event_txid' => $eventResult['event_txid'] ?? null,
                        'stage' => $stage->value,
                        'completion_status' => $completionStatus->value,
                    ],
                ]);
            }

            // 5. Determine next possible stages for other stages
            $nextStages = $stage->getNextStages();
            $nextStageMessage = '';
            if (! empty($nextStages)) {
                $nextStageNames = array_map(fn ($s) => $s->getDisplayName(), $nextStages);
                if (count($nextStageNames) === 1) {
                    $nextStageMessage = " Next stage: {$nextStageNames[0]}.";
                } else {
                    $nextStageMessage = ' Next possible stages: '.implode(', ', $nextStageNames).'.';
                }
            }

            // 6. Notify relevant parties
            try {
                $this->notificationService->notifyStageUpdate(
                    pr_number: $pr_number,
                    procurementTitle: $procurement->title,
                    stageIdentifier: $stage->getDisplayName(),
                    currentStatus: $completionStatus->getDisplayName(),
                    timestamp: now()->toDateTimeString(),
                    actionType: 'marked complete',
                    documentCount: count($uploadedDocuments),
                    stageTransition: true,
                    nextStage: ! empty($nextStages) ? $nextStages[0]->getDisplayName() : '',
                    rolesToNotify: ['bac_chairman', 'hope', 'admin']
                );
            } catch (\Exception $e) {
                // Non-critical: Continue even if notification fails
                Log::warning('Failed to send stage completion notifications', [
                    'pr_number' => $pr_number,
                    'error' => $e->getMessage(),
                ]);
            }

            return back()->with('success', [
                'message' => "Stage marked as complete successfully!{$nextStageMessage}",
                'blockchain' => [
                    'status_txid' => $statusResult['status_txid'] ?? null,
                    'event_txid' => $eventResult['event_txid'] ?? null,
                    'stage' => $stage->value,
                    'completion_status' => $completionStatus->value,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking stage as complete', [
                'pr_number' => $pr_number,
                'stage' => $stage->value,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to mark stage as complete. Please try again.');
        }
    }

    /**
     * Get the appropriate completion status for a given stage.
     */
    private function getCompletionStatusForStage(StageEnums $stage): StatusEnums
    {
        return match ($stage) {
            StageEnums::PRE_PROCUREMENT_CONFERENCE => StatusEnums::PRE_PROCUREMENT_CONFERENCE_COMPLETED,
            StageEnums::BIDDING_DOCUMENTS => StatusEnums::BIDDING_DOCUMENTS_PUBLISHED,
            default => StatusEnums::PROCUREMENT_SUBMITTED,
        };
    }

    /**
     * Check stage completion status (API endpoint for frontend).
     */
    public function checkCompletion(string $pr_number, StageEnums $stage): JsonResponse
    {
        if (! $stage->isPreProcurement()) {
            abort(403, 'Invalid stage for Pre-Procurement phase');
        }

        $completionCheck = $this->validationService->validateStageCompletion($stage, []);

        return response()->json($completionCheck);
    }

    /**
     * Validate a specific document upload (API endpoint for frontend real-time validation).
     */
    public function validateUpload(Request $request, string $pr_number, StageEnums $stage): JsonResponse
    {
        if (! $stage->isPreProcurement()) {
            abort(403, 'Invalid stage for Pre-Procurement phase');
        }

        $documentTypeValue = $request->input('document_type');
        $file = $request->file('file');

        $documentType = DocumentTypeEnums::tryFrom($documentTypeValue);
        if (! $documentType) {
            return response()->json([
                'valid' => false,
                'errors' => ['Invalid document type'],
            ], 400);
        }

        $validation = $this->validationService->validateUpload(
            $stage,
            $documentType,
            []
        );

        return response()->json([
            'valid' => empty($validation['errors']),
            'errors' => $validation['errors'] ?? [],
            'warnings' => $validation['warnings'] ?? [],
        ]);
    }

    /**
     * Handle Pre-Procurement Conference decision (held or skipped).
     * This is a special method for Stage 2 only.
     */
    public function publishDecision(PreProcurementConferenceDecisionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $pr_number = $validated['pr_number'];
        $user = auth()->user();
        $userAddress = $user->blockchain_address;

        try {
            if ($validated['conference_held']) {
                // Conference held - just publish the decision event, wait for documents
                $this->eventPublisher->publish(
                    prNumber: $pr_number,
                    procurementTitle: $validated['procurement_title'],
                    stage: StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
                    eventType: 'conference_decision',
                    category: 'Decision',
                    severity: 'info',
                    details: 'Pre-Procurement Conference will be conducted. Awaiting documents upload.',
                    documentCount: 0,
                    userAddress: $userAddress
                );

                return redirect()->route('bac-secretariat.procurement.pre-procurement.show', [
                    'pr_number' => $pr_number,
                    'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
                ])->with('success', 'Decision recorded. Please upload conference documents.');
            }

            // Conference skipped - transition directly to BIDDING_DOCUMENTS
            $this->statusPublisher->publish(
                $pr_number,
                $validated['procurement_title'],
                StageEnums::PRE_PROCUREMENT_CONFERENCE,
                StatusEnums::PRE_PROCUREMENT_CONFERENCE_SKIPPED,
                $userAddress
            );

            $this->eventPublisher->publish(
                prNumber: $pr_number,
                procurementTitle: $validated['procurement_title'],
                stage: StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
                eventType: 'conference_skipped',
                category: 'Decision',
                severity: 'info',
                details: 'Pre-Procurement Conference was not held. Proceeding to next stage.',
                documentCount: 0,
                userAddress: $userAddress
            );

            $fromStage = StageEnums::PRE_PROCUREMENT_CONFERENCE;
            $toStage = StageEnums::BIDDING_DOCUMENTS;

            $procurement = $this->findProcurementById($pr_number);
            $this->statusPublisher->publishTransition(
                $pr_number,
                $procurement['title'] ?? $validated['procurement_title'],
                $fromStage,
                $toStage,
                \App\Enums\StatusEnums::PRE_PROCUREMENT_CONFERENCE_SKIPPED,
                $userAddress
            );
            $this->eventPublisher->publishStageTransition(
                $pr_number,
                $validated['procurement_title'],
                StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
                StageEnums::BIDDING_DOCUMENTS->value,
                $userAddress
            );

            return redirect()->route('bac-secretariat.procurements.index')
                ->with('success', 'Pre-Procurement Conference decision recorded successfully. Publishing to blockchain in the background.');
        } catch (\Exception $e) {
            \Log::error('Failed to publish Pre-Procurement Conference decision', [
                'pr_number' => $pr_number,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to publish decision to blockchain. Please try again.',
            ]);
        }
    }

    /**
     * Resolve document type enum from field name.
     */
    protected function resolveDocumentType(string $fieldName, StageEnums $stage): DocumentTypeEnums
    {
        // Map common field names to DocumentTypeEnums
        // Note: Stage 1 (Procurement Initiation) is handled by ProcurementInitiationController
        $mapping = [
            // Stage 2: Pre-Procurement Conference
            'minutes' => DocumentTypeEnums::PRE_PROCUREMENT_MINUTES,
            'attendance' => DocumentTypeEnums::PRE_PROCUREMENT_ATTENDANCE,
            'agenda' => DocumentTypeEnums::PRE_PROCUREMENT_AGENDA,

            // Stage 3: Bidding Documents
            'bid_documents' => DocumentTypeEnums::INVITATION_TO_BID,
            'technical_specifications' => DocumentTypeEnums::BIDDING_TECHNICAL_SPECIFICATIONS,
            'instructions_to_bidders' => DocumentTypeEnums::INSTRUCTIONS_TO_BIDDERS,
        ];

        $documentType = $mapping[$fieldName] ?? null;

        if (! $documentType) {
            // Try to match by uppercase conversion
            $documentType = DocumentTypeEnums::tryFrom(strtoupper($fieldName));
        }

        if (! $documentType) {
            throw new \InvalidArgumentException("Unknown document type: {$fieldName} for stage {$stage->value}");
        }

        return $documentType;
    }

    /**
     * Handle Pre-Bid Conference decision (held or skipped).
     * This is part of Stage 3 (Bidding Documents) workflow.
     */
    public function publishPreBidDecision(PreBidConferenceDecisionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $pr_number = $validated['pr_number'];
        $user = auth()->user();
        $userAddress = $user->blockchain_address;

        try {
            if ($validated['conference_held']) {
                // Conference held - publish status update and decision event, wait for documents
                $this->statusPublisher->publish(
                    $pr_number,
                    $validated['procurement_title'],
                    StageEnums::PRE_BID_CONFERENCE,
                    StatusEnums::PRE_BID_CONFERENCE_HELD,
                    $userAddress
                );

                $this->eventPublisher->publish(
                    prNumber: $pr_number,
                    procurementTitle: $validated['procurement_title'],
                    stage: StageEnums::PRE_BID_CONFERENCE->value,
                    eventType: 'conference_decision',
                    category: 'Decision',
                    severity: 'info',
                    details: 'Pre-Bid Conference will be conducted. Awaiting documents upload.',
                    documentCount: 0,
                    userAddress: $userAddress
                );

                return redirect()->route('bac-secretariat.procurement.phase.show', [
                    'pr_number' => $pr_number,
                    'stage' => StageEnums::PRE_BID_CONFERENCE->getSlug(),
                ])->with('success', 'Decision recorded. Please upload conference documents.');
            }

            // Conference skipped - transition directly to SUPPLEMENTAL_BID_BULLETIN
            $this->statusPublisher->publish(
                $pr_number,
                $validated['procurement_title'],
                StageEnums::PRE_BID_CONFERENCE,
                StatusEnums::PRE_BID_CONFERENCE_SKIPPED,
                $userAddress
            );

            $this->eventPublisher->publish(
                prNumber: $pr_number,
                procurementTitle: $validated['procurement_title'],
                stage: StageEnums::PRE_BID_CONFERENCE->value,
                eventType: 'conference_skipped',
                category: 'Decision',
                severity: 'info',
                details: 'Pre-Bid Conference was not held. Proceeding to Supplemental Bid Bulletin stage.',
                documentCount: 0,
                userAddress: $userAddress
            );

            $fromStage = StageEnums::PRE_BID_CONFERENCE;
            $toStage = StageEnums::SUPPLEMENTAL_BID_BULLETIN;

            $procurement = $this->findProcurementById($pr_number);
            $this->statusPublisher->publishTransition(
                $pr_number,
                $procurement['title'] ?? $validated['procurement_title'],
                $fromStage,
                $toStage,
                StatusEnums::PRE_BID_CONFERENCE_SKIPPED,
                $userAddress
            );
            $this->eventPublisher->publishStageTransition(
                $pr_number,
                $validated['procurement_title'],
                StageEnums::PRE_BID_CONFERENCE->value,
                StageEnums::SUPPLEMENTAL_BID_BULLETIN->value,
                $userAddress
            );

            return redirect()->route('bac-secretariat.procurements.index')
                ->with('success', 'Pre-Bid Conference skipped. Proceeding to Supplemental Bid Bulletin stage.');
        } catch (\Exception $e) {
            \Log::error('Failed to publish Pre-Bid Conference decision', [
                'pr_number' => $pr_number,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to publish decision to blockchain. Please try again.',
            ]);
        }
    }

    /**
     * Handle Supplemental Bid Bulletin decision (needed or skipped).
     * This is part of Stage 3 (Bidding Documents) workflow.
     */
    public function publishSupplementalBidBulletinDecision(SupplementalBidBulletinDecisionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $pr_number = $validated['pr_number'];
        $user = auth()->user();
        $userAddress = $user->blockchain_address;

        try {
            if ($validated['supplemental_bid_needed']) {
                // Supplemental bid bulletin needed - publish status update and decision event, wait for documents
                $this->statusPublisher->publish(
                    $pr_number,
                    $validated['procurement_title'],
                    StageEnums::SUPPLEMENTAL_BID_BULLETIN,
                    StatusEnums::SUPPLEMENTAL_BULLETINS_ONGOING,
                    $userAddress
                );

                $this->eventPublisher->publish(
                    prNumber: $pr_number,
                    procurementTitle: $validated['procurement_title'],
                    stage: StageEnums::SUPPLEMENTAL_BID_BULLETIN->value,
                    eventType: 'bulletin_decision',
                    category: 'Decision',
                    severity: 'info',
                    details: 'Supplemental Bid Bulletin will be issued. Awaiting documents upload.',
                    documentCount: 0,
                    userAddress: $userAddress
                );

                return redirect()->route('bac-secretariat.procurement.phase.show', [
                    'pr_number' => $pr_number,
                    'stage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN->getSlug(),
                ])->with('success', 'Decision recorded. Please upload supplemental bid bulletin documents.');
            }

            // Supplemental bid bulletin skipped - transition directly to BID_OPENING (Procurement Phase)
            $this->statusPublisher->publish(
                $pr_number,
                $validated['procurement_title'],
                StageEnums::SUPPLEMENTAL_BID_BULLETIN,
                StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED,
                $userAddress
            );

            $this->eventPublisher->publish(
                prNumber: $pr_number,
                procurementTitle: $validated['procurement_title'],
                stage: StageEnums::SUPPLEMENTAL_BID_BULLETIN->value,
                eventType: 'bulletin_skipped',
                category: 'Decision',
                severity: 'info',
                details: 'Supplemental Bid Bulletin was not needed. Proceeding to Bid Opening.',
                documentCount: 0,
                userAddress: $userAddress
            );

            $fromStage = StageEnums::SUPPLEMENTAL_BID_BULLETIN;
            $toStage = StageEnums::BID_OPENING;

            $procurement = $this->findProcurementById($pr_number);
            $this->statusPublisher->publishTransition(
                $pr_number,
                $procurement['title'] ?? $validated['procurement_title'],
                $fromStage,
                $toStage,
                StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED,
                $userAddress
            );
            $this->eventPublisher->publishStageTransition(
                $pr_number,
                $validated['procurement_title'],
                StageEnums::SUPPLEMENTAL_BID_BULLETIN->value,
                StageEnums::BID_OPENING->value,
                $userAddress
            );

            return redirect()->route('bac-secretariat.procurements.index')
                ->with('success', 'Supplemental Bid Bulletin skipped. Proceeding to Bid Opening stage.');
        } catch (\Exception $e) {
            \Log::error('Failed to publish Supplemental Bid Bulletin decision', [
                'pr_number' => $pr_number,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to publish decision to blockchain. Please try again.',
            ]);
        }
    }

    /**
     * Get the next stage after the current one.
     */
    protected function getNextStage(StageEnums $stage): ?StageEnums
    {
        return match ($stage) {
            StageEnums::PRE_PROCUREMENT_CONFERENCE => StageEnums::BIDDING_DOCUMENTS,
            StageEnums::BIDDING_DOCUMENTS => StageEnums::PRE_BID_CONFERENCE,
            default => null,
        };
    }
}
