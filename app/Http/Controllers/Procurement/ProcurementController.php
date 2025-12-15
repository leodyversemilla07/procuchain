<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Http\Controllers\Procurement\Concerns\HasProcurementSupport;
use App\Services\DocumentValidationService;
use App\Services\Manager;
use App\Services\ModeAwareDocumentValidationService;
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
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles Procurement Phase (Stages 4-9):
 * - Stage 4: Pre-Bid Conference
 * - Stage 5: Supplemental/Bid Bulletin
 * - Stage 6: Bid Submission/Opening
 * - Stage 7: Bid Evaluation
 * - Stage 8: Post-Qualification
 * - Stage 9: BAC Resolution
 */
class ProcurementController extends BaseController
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
        protected ModeAwareDocumentValidationService $modeAwareValidationService,
        protected ProcurementOrchestrator $orchestrator
    ) {
        $this->initializeProcurementSupport($multichain, $documentPublisher, $statusPublisher, $eventPublisher, $procurementDataService, $documentRepository);
        $this->applyProcurementMiddleware();
    }

    /**
     * Display the upload page for a specific procurement stage.
     */
    public function show(Request $request, string $pr_number, StageEnums $stage): Response
    {
        // Validate that stage is in procurement phase
        if (! $stage->isProcurement()) {
            abort(403, 'Invalid stage for Procurement phase');
        }

        // Validate that stage exists in the procurement's mode workflow
        if (! $this->stageExistsInWorkflow($pr_number, $stage)) {
            abort(403, 'This stage is not applicable for this procurement mode');
        }

        $procurement = $this->findProcurementById($pr_number);

        // Determine which Inertia component to render based on stage
        $component = match ($stage) {
            StageEnums::PRE_BID_CONFERENCE => 'bac-secretariat/procurement-stage/pre-bid-conference-upload',
            StageEnums::SUPPLEMENTAL_BID_BULLETIN => 'bac-secretariat/procurement-stage/supplemental-bid-bulletin-upload',
            StageEnums::BID_OPENING => 'bac-secretariat/procurement-stage/bid-opening-upload',
            StageEnums::BID_EVALUATION => 'bac-secretariat/procurement-stage/bid-evaluation-upload',
            StageEnums::POST_QUALIFICATION => 'bac-secretariat/procurement-stage/post-qualification-upload',
            StageEnums::BAC_RESOLUTION => 'bac-secretariat/procurement-stage/bac-resolution-upload',
            StageEnums::REQUEST_FOR_QUOTATION => 'bac-secretariat/procurement-stage/rfq-upload',
            StageEnums::ABSTRACT_OF_QUOTATIONS => 'bac-secretariat/procurement-stage/abstract-of-quotations-upload',
            default => abort(404, 'Stage component not found'),
        };

        // Get procurement mode for mode-aware document requirements
        $mode = $this->getProcurementMode($pr_number);

        return Inertia::render($component, [
            'procurement' => [
                'pr_number' => $pr_number,
                'title' => $procurement['procurement_title'] ?? '',
                'status' => $procurement['current_status'] ?? '',
                'stage' => $stage->getDisplayName(),
                'stage_value' => $stage->value,
                'current_stage' => $procurement['stage'] ?? '',
            ],
            'workflowInfo' => $this->getWorkflowInfo($pr_number, $stage),
            'documentGuide' => $this->modeAwareValidationService->getStageDocumentGuide($stage, $mode),
            'uploadedDocuments' => fn () => $this->getUploadedDocumentTypes($pr_number, $stage),
        ]);
    }

    /**
     * Upload documents for a specific procurement stage.
     */
    public function uploadDocuments(Request $request, string $pr_number, StageEnums $stage): RedirectResponse
    {
        // Validate that stage is in procurement phase
        if (! $stage->isProcurement()) {
            abort(403, 'Invalid stage for Procurement phase');
        }

        // Validate that stage exists in the procurement's mode workflow
        $this->validateStageInWorkflow($pr_number, $stage);

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

        // Get procurement mode for mode-aware validation
        $mode = $this->getProcurementMode($pr_number);

        try {
            // Validate each uploaded document
            foreach ($uploadedFiles as $fieldName => $file) {
                // Extract document type from field name
                $documentTypeKey = str_replace('_file', '', $fieldName);
                $documentType = $this->resolveDocumentType($documentTypeKey, $stage);

                $validation = $this->modeAwareValidationService->validateUpload(
                    $stage,
                    $documentType,
                    $existingDocumentEnums,
                    $mode
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

            return redirect()->back()->with('success', 'Documents uploaded successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to upload procurement documents', [
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
        // Validate that stage is in procurement phase
        if (! $stage->isProcurement()) {
            return back()->withErrors(['message' => 'Invalid stage for Procurement phase']);
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

            // Get procurement mode for mode-aware validation
            $mode = $this->getProcurementMode($pr_number);

            // Validate the single document upload
            $validation = $this->modeAwareValidationService->validateUpload(
                $stage,
                $documentType,
                $existingDocumentEnums,
                $mode
            );

            if (! empty($validation['errors'])) {
                return back()->withErrors(['message' => implode(' ', $validation['errors'])]);
            }

            // Get procurement details - Try METADATA stream first, fallback to STATUS stream
            $procurement = app(\App\Repositories\ProcurementRepository::class)->findByProcurement($pr_number);

            // Fallback to STATUS stream if METADATA stream fails (provides resilience)
            if (! $procurement) {
                \Log::warning('Procurement not found in METADATA stream, attempting fallback to STATUS stream', [
                    'pr_number' => $pr_number,
                    'stage' => $stage->value,
                    'user' => $user->email,
                ]);

                $statusData = $this->findProcurementById($pr_number);
                if (! $statusData) {
                    \Log::error('Procurement not found in both METADATA and STATUS streams', [
                        'pr_number' => $pr_number,
                        'stage' => $stage->value,
                        'user' => $user->email,
                    ]);

                    return back()->withErrors(['message' => 'Procurement not found. Please ensure the procurement has been properly initiated.']);
                }

                // Create a temporary ProcurementData DTO from STATUS stream data
                $procurement = new \App\DataTransferObjects\ProcurementData(
                    prNumber: $pr_number,
                    title: $statusData['procurement_title'] ?? 'N/A',
                    status: \App\Enums\StatusEnums::tryFrom($statusData['current_status'] ?? '') ?? $this->getOngoingStatusForStage($stage),
                    stage: \App\Enums\StageEnums::tryFrom($statusData['stage'] ?? '') ?? $stage,
                    procurementMode: $this->getProcurementMode($pr_number) ?? \App\Enums\ProcurementModeEnums::PUBLIC_BIDDING,
                    timestamp: $statusData['timestamp'] ?? now()->toIso8601String(),
                    userAddress: $statusData['user_address'] ?? $userAddress,
                );

                \Log::info('Using STATUS stream fallback for procurement data', [
                    'pr_number' => $pr_number,
                    'title' => $procurement->title,
                ]);
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
                    'current_status' => StatusEnums::tryFrom($procurement->status) ?? $this->getOngoingStatusForStage($stage),
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
        if (! $stage->isProcurement()) {
            abort(403, 'Invalid stage for Procurement phase');
        }

        $mode = $this->getProcurementMode($pr_number);
        $guide = $this->modeAwareValidationService->getStageDocumentGuide($stage, $mode);

        return response()->json($guide);
    }

    /**
     * Mark a procurement stage as complete.
     */
    public function markStageComplete(Request $request, string $pr_number, StageEnums $stage): RedirectResponse
    {
        if (! $stage->isProcurement()) {
            abort(403, 'Invalid stage for Procurement phase');
        }

        // Validate that stage exists in the procurement's mode workflow
        $this->validateStageInWorkflow($pr_number, $stage);

        try {
            // Verify all required documents are uploaded
            $uploadedDocuments = $this->getUploadedDocumentTypes($pr_number, $stage);
            $mode = $this->getProcurementMode($pr_number);
            $documentGuide = $this->modeAwareValidationService->getStageDocumentGuide($stage, $mode);

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

            // 1. Get current status before completion
            $previousStatus = StatusEnums::tryFrom($procurement->status);

            // 2. Determine completion status based on stage
            $completionStatus = $this->getCompletionStatusForStage($stage);

            // 3. Publish status update to blockchain
            $statusResult = $this->statusPublisher->publish(
                prNumber: $pr_number,
                procurementTitle: $procurement->title,
                stage: $stage,
                currentStatus: $completionStatus,
                userAddress: $userAddress,
                previousStatus: $previousStatus,
                metadata: [
                    'documents_uploaded' => count($uploadedDocuments),
                    'marked_complete_at' => now()->toIso8601String(),
                    'procurement_mode' => $procurement->procurementMode->value,
                ]
            );

            // 4. Publish completion event to blockchain
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
                    'procurement_mode' => $procurement->procurementMode->value,
                ]
            );

            // 5. Get the mode-aware next stage for automatic transition
            $nextStage = $this->getNextStageForProcurement($pr_number, $stage);

            if ($nextStage) {
                // Get the appropriate status for entering the next stage
                $nextStageStatus = $this->getInitialStatusForStage($pr_number, $nextStage);

                // Publish stage transition to blockchain
                $this->statusPublisher->publishTransition(
                    prNumber: $pr_number,
                    procurementTitle: $procurement->title,
                    fromStage: $stage,
                    toStage: $nextStage,
                    currentStatus: $nextStageStatus,
                    userAddress: $userAddress,
                    previousStatus: $completionStatus
                );

                $this->eventPublisher->publishStageTransition(
                    prNumber: $pr_number,
                    procurementTitle: $procurement->title,
                    fromStage: $stage->value,
                    toStage: $nextStage->value,
                    userAddress: $userAddress
                );

                // Get the next stage action URL using ProcurementActionService
                $actionService = app(\App\Services\Procurement\ProcurementActionService::class);
                $actions = $actionService->getActions($pr_number);
                $nextStageAction = collect($actions['workflow_actions'])->first();

                return back()->with('success', [
                    'message' => "{$stage->getDisplayName()} marked as complete successfully! Proceeding to {$nextStage->getDisplayName()} stage.",
                    'blockchain' => [
                        'status_txid' => $statusResult['status_txid'] ?? null,
                        'event_txid' => $eventResult['event_txid'] ?? null,
                        'stage' => $stage->value,
                        'next_stage' => $nextStage->value,
                        'next_stage_name' => $nextStage->getDisplayName(),
                        'next_stage_url' => $nextStageAction['href'] ?? null,
                        'completion_status' => $completionStatus->value,
                    ],
                ]);
            }

            // No next stage - end of workflow for this phase
            return back()->with('success', [
                'message' => "{$stage->getDisplayName()} marked as complete successfully!",
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
     * Skip an optional stage and proceed to the next stage.
     */
    public function skipStage(Request $request, string $pr_number, StageEnums $stage): RedirectResponse
    {
        if (! $stage->isProcurement()) {
            abort(403, 'Invalid stage for Procurement phase');
        }

        // Validate that stage exists in the procurement's mode workflow
        $this->validateStageInWorkflow($pr_number, $stage);

        try {
            $reason = $request->input('reason');
            $result = $this->performSkipStage($pr_number, $stage, $reason);

            return back()->with('success', [
                'message' => $result['message'],
                'blockchain' => $result['blockchain'],
            ]);
        } catch (\Exception $e) {
            Log::error('Error skipping stage', [
                'pr_number' => $pr_number,
                'stage' => $stage->value,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Repeat a stage to issue another document (e.g., another Supplemental Bid Bulletin).
     * Per NGPA IRR, some stages like Supplemental Bid Bulletin can be issued multiple times.
     */
    public function repeatStage(Request $request, string $pr_number, StageEnums $stage): RedirectResponse
    {
        if (! $stage->isProcurement()) {
            abort(403, 'Invalid stage for Procurement phase');
        }

        // Validate that this stage can be repeated
        if (! $stage->canRepeat()) {
            return back()->with('error', 'This stage cannot be repeated.');
        }

        // Validate that stage exists in the procurement's mode workflow
        $this->validateStageInWorkflow($pr_number, $stage);

        try {
            $procurement = app(\App\Repositories\ProcurementRepository::class)->findByProcurement($pr_number);
            if (! $procurement) {
                return back()->with('error', 'Procurement not found.');
            }

            $user = auth()->user();
            $userAddress = $user->blockchain_address ?? $user->email;

            // Get the "ongoing" status for this stage
            $ongoingStatus = $this->getOngoingStatusForStage($stage);

            // Publish event for issuing another bulletin
            $eventResult = $this->eventPublisher->publish(
                prNumber: $pr_number,
                procurementTitle: $procurement->title,
                stage: $stage->value,
                eventType: 'stage_repeated',
                category: 'stage_transition',
                severity: 'info',
                details: "Another {$stage->getDisplayName()} is being issued per NGPA IRR provisions.",
                documentCount: 0,
                userAddress: $userAddress,
                metadata: [
                    'stage' => $stage->value,
                    'repeat_reason' => $request->input('reason', 'Additional bulletin required'),
                    'procurement_mode' => $procurement->procurementMode->value,
                ]
            );

            // Publish status update to reset stage to ongoing
            $statusResult = $this->statusPublisher->publish(
                prNumber: $pr_number,
                procurementTitle: $procurement->title,
                stage: $stage,
                currentStatus: $ongoingStatus,
                userAddress: $userAddress,
                previousStatus: null,
                metadata: [
                    'action' => 'repeat_stage',
                    'repeated_at' => now()->toIso8601String(),
                    'procurement_mode' => $procurement->procurementMode->value,
                ]
            );

            return back()->with('success', [
                'message' => "Another {$stage->getDisplayName()} can now be issued. Please upload the new documents.",
                'blockchain' => [
                    'status_txid' => $statusResult['status_txid'] ?? null,
                    'event_txid' => $eventResult['event_txid'] ?? null,
                    'stage' => $stage->value,
                ],
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error repeating stage', [
                'pr_number' => $pr_number,
                'stage' => $stage->value,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to repeat stage. Please try again.');
        }
    }

    /**
     * Get the appropriate completion status for a given stage.
     */
    private function getCompletionStatusForStage(StageEnums $stage): StatusEnums
    {
        return match ($stage) {
            StageEnums::PRE_BID_CONFERENCE => StatusEnums::PRE_BID_CONFERENCE_COMPLETED,
            StageEnums::SUPPLEMENTAL_BID_BULLETIN => StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED,
            StageEnums::BID_OPENING => StatusEnums::BIDS_OPENED,
            StageEnums::BID_EVALUATION => StatusEnums::BIDS_EVALUATED,
            StageEnums::POST_QUALIFICATION => StatusEnums::POST_QUALIFICATION_VERIFIED,
            StageEnums::BAC_RESOLUTION => StatusEnums::RESOLUTION_RECORDED,
            StageEnums::REQUEST_FOR_QUOTATION => StatusEnums::QUOTATIONS_RECEIVED,
            StageEnums::ABSTRACT_OF_QUOTATIONS => StatusEnums::ABSTRACT_PREPARED,
            default => StatusEnums::PROCUREMENT_SUBMITTED,
        };
    }

    /**
     * Check stage completion status (API endpoint for frontend).
     */
    public function checkCompletion(string $pr_number, StageEnums $stage): JsonResponse
    {
        if (! $stage->isProcurement()) {
            abort(403, 'Invalid stage for Procurement phase');
        }

        $completionCheck = $this->validationService->validateStageCompletion($stage, []);

        return response()->json($completionCheck);
    }

    /**
     * Validate a specific document upload (API endpoint for frontend real-time validation).
     */
    public function validateUpload(Request $request, string $pr_number, StageEnums $stage): JsonResponse
    {
        if (! $stage->isProcurement()) {
            abort(403, 'Invalid stage for Procurement phase');
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

        // Get procurement mode for mode-aware validation
        $mode = $this->getProcurementMode($pr_number);

        $validation = $this->modeAwareValidationService->validateUpload(
            $stage,
            $documentType,
            [],
            $mode
        );

        return response()->json([
            'valid' => empty($validation['errors']),
            'errors' => $validation['errors'] ?? [],
            'warnings' => $validation['warnings'] ?? [],
        ]);
    }

    /**
     * Resolve document type enum from field name.
     */
    protected function resolveDocumentType(string $fieldName, StageEnums $stage): DocumentTypeEnums
    {
        // Map common field names to DocumentTypeEnums
        $mapping = [
            // Stage 4: Pre-Bid Conference
            'minutes' => DocumentTypeEnums::PRE_BID_MINUTES,
            'attendance' => DocumentTypeEnums::PRE_BID_ATTENDANCE,
            'clarifications' => DocumentTypeEnums::EVALUATION_CLARIFICATIONS,
            'agenda' => DocumentTypeEnums::PRE_BID_AGENDA,

            // Stage 5: Supplemental/Bid Bulletin
            'bulletin' => DocumentTypeEnums::SUPPLEMENTAL_BID_BULLETIN,
            'addendum' => DocumentTypeEnums::SUPPLEMENTAL_BID_BULLETIN,
            'revised_specifications' => DocumentTypeEnums::BIDDING_TECHNICAL_SPECIFICATIONS,

            // Stage 6: Bid Submission/Opening
            'abstract_of_bids' => DocumentTypeEnums::ABSTRACT_OF_BIDS,
            'bid_opening_minutes' => DocumentTypeEnums::BID_OPENING_MINUTES,
            'bidders_list' => DocumentTypeEnums::BID_SUBMISSION_REGISTER,

            // Stage 7: Bid Evaluation
            'evaluation_report' => DocumentTypeEnums::BID_EVALUATION_REPORT,
            'technical_evaluation' => DocumentTypeEnums::TECHNICAL_EVALUATION_REPORT,
            'financial_evaluation' => DocumentTypeEnums::FINANCIAL_EVALUATION_REPORT,

            // Stage 8: Post-Qualification
            'post_qual_report' => DocumentTypeEnums::POST_QUALIFICATION_REPORT,
            'site_inspection' => DocumentTypeEnums::SITE_VISIT_REPORT,
            'compliance_check' => DocumentTypeEnums::DOCUMENT_VERIFICATION_CHECKLIST,

            // Stage 9: BAC Resolution
            'bac_resolution' => DocumentTypeEnums::BAC_RESOLUTION_AWARD,
            'recommendation' => DocumentTypeEnums::BAC_RESOLUTION_AWARD,
            'approval_document' => DocumentTypeEnums::TRANSMITTAL_TO_HOPE,
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
     * Get the ongoing status for a stage (used during document uploads).
     */
    protected function getOngoingStatusForStage(StageEnums $stage): StatusEnums
    {
        return match ($stage) {
            StageEnums::PRE_BID_CONFERENCE => StatusEnums::PRE_BID_CONFERENCE_HELD,
            StageEnums::SUPPLEMENTAL_BID_BULLETIN => StatusEnums::SUPPLEMENTAL_BULLETINS_ONGOING,
            // For stages without dedicated "ongoing" statuses, use the previous stage's completion status
            // Documents are uploaded during the stage, status changes only when stage is marked complete
            StageEnums::BID_OPENING => StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED,
            StageEnums::BID_EVALUATION => StatusEnums::BIDS_OPENED,
            StageEnums::POST_QUALIFICATION => StatusEnums::BIDS_EVALUATED,
            StageEnums::BAC_RESOLUTION => StatusEnums::POST_QUALIFICATION_VERIFIED,
            // Alternative modes (RFQ-based)
            StageEnums::REQUEST_FOR_QUOTATION => StatusEnums::PROCUREMENT_SUBMITTED,
            StageEnums::ABSTRACT_OF_QUOTATIONS => StatusEnums::QUOTATIONS_RECEIVED,
            default => StatusEnums::PROCUREMENT_SUBMITTED,
        };
    }
}
