<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Procurement\PreBidConferenceDecisionRequest;
use App\Http\Requests\Procurement\PreProcurementConferenceDecisionRequest;
use App\Http\Requests\Procurement\SupplementalBidBulletinDecisionRequest;
use App\Jobs\BlockchainWriteJob;
use App\Services\DocumentValidationService;
use App\Services\ModeAwareDocumentValidationService;
use App\Services\Procurement\ProcurementSupportService;
use App\Services\Publishers\DecisionPublisher;
use App\Services\Publishers\ProcurementOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Unified controller for all procurement stages across all phases:
 * - Pre-Procurement Phase (Stages 1-3)
 * - Procurement Phase (Stages 4-9)
 * - Post-Procurement Phase (Stages 10-15)
 *
 * Note: Stage 1 (Procurement Initiation) is handled by ProcurementInitiationController
 */
class ProcurementStageController extends BaseController
{
    public function __construct(
        protected ProcurementSupportService $procurementSupport,
        protected DocumentValidationService $validationService,
        protected ModeAwareDocumentValidationService $modeAwareValidationService,
        protected ProcurementOrchestrator $orchestrator,
        protected DecisionPublisher $decisionPublisher
    ) {}

    /**
     * Display the upload page for a specific procurement stage.
     */
    public function show(Request $request, string $pr_number, StageEnums $stage): Response
    {
        $this->authorize('view-procurement', $pr_number);

        // Validate that stage exists in the procurement's mode workflow
        if (! $this->procurementSupport->stageExistsInWorkflow($pr_number, $stage)) {
            abort(403, 'This stage is not applicable for this procurement mode');
        }

        $procurement = $this->procurementSupport->findProcurementById($pr_number);

        // Auto-transition for post-procurement stages
        if ($stage->isPostProcurement() && $procurement) {
            $this->procurementSupport->handleAutoStageTransition($pr_number, $procurement, $stage);
        }

        // All stages now use the unified stage-upload component
        $component = 'bac-secretariat/stage-upload';

        // Get full procurement data for NTP stage to include delivery details
        $procurementData = null;
        if ($stage === StageEnums::NOTICE_TO_PROCEED) {
            $procurementData = app(\App\Repositories\ProcurementRepository::class)->findByProcurement($pr_number);
        }

        // Get procurement mode for mode-aware document requirements
        $mode = $this->procurementSupport->getProcurementMode($pr_number);

        return Inertia::render($component, [
            'procurement' => [
                'pr_number' => $pr_number,
                'title' => $procurement['procurement_title'] ?? '',
                'status' => $procurement['current_status'] ?? '',
                'stage' => $stage->getDisplayName(),
                'stage_value' => $stage->value,
                'current_stage' => $procurement['stage'] ?? '',
                // Delivery details for NTP stage (per NGPA IRR Section 71)
                'delivery_location' => $procurementData?->deliveryLocation,
                'delivery_date' => $procurementData?->deliveryDate?->format('Y-m-d'),
                'delivery_date_formatted' => $procurementData?->getFormattedDeliveryDate(),
                'delivery_term_days' => $procurementData?->deliveryTermDays,
            ],
            'workflowInfo' => $this->procurementSupport->getWorkflowInfo($pr_number, $stage),
            'documentGuide' => $this->modeAwareValidationService->getStageDocumentGuide($stage, $mode),
            'uploadedDocuments' => fn () => $this->procurementSupport->getUploadedDocumentTypes($pr_number, $stage),
        ]);
    }

    /**
     * Upload a single document for progressive upload workflow.
     */
    public function uploadSingleDocument(
        \App\Http\Requests\Procurement\UploadSingleDocumentRequest $request,
        string $pr_number,
        StageEnums $stage
    ): JsonResponse {
        $this->authorize('view-procurement', $pr_number);

        // Validate that stage exists in the procurement's mode workflow
        $this->procurementSupport->validateStageInWorkflow($pr_number, $stage);

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
            $existingDocuments = $this->procurementSupport->getUploadedDocumentTypes($pr_number, $stage);
            $existingDocumentEnums = array_filter(
                array_map(fn ($docType) => DocumentTypeEnums::tryFrom($docType), $existingDocuments),
                fn ($enum) => $enum !== null
            );

            // Get procurement mode for mode-aware validation
            $mode = $this->procurementSupport->getProcurementMode($pr_number);

            // Validate the single document upload with mode awareness
            $validation = $this->modeAwareValidationService->validateUpload(
                $stage,
                $documentType,
                $existingDocumentEnums,
                $mode
            );

            if (! empty($validation['errors'])) {
                return response()->json(['message' => implode(' ', $validation['errors'])], 422);
            }

            // Get procurement details - Try METADATA stream first, fallback to STATUS stream
            $procurement = app(\App\Repositories\ProcurementRepository::class)->findByProcurement($pr_number);

            // Fallback to STATUS stream if METADATA stream fails (provides resilience)
            if (! $procurement) {
                Log::warning('Procurement not found in METADATA stream, attempting fallback to STATUS stream', [
                    'pr_number' => $pr_number,
                    'stage' => $stage->value,
                    'user' => $user->email,
                ]);

                $statusData = $this->procurementSupport->findProcurementById($pr_number);
                if (! $statusData) {
                    Log::error('Procurement not found in both METADATA and STATUS streams', [
                        'pr_number' => $pr_number,
                        'stage' => $stage->value,
                        'user' => $user->email,
                    ]);

                    return response()->json(['message' => 'Procurement not found. Please ensure the procurement has been properly initiated.'], 422);
                }

                // Create a temporary ProcurementData DTO from STATUS stream data
                $procurement = new \App\DataTransferObjects\ProcurementData(
                    prNumber: $pr_number,
                    title: $statusData['procurement_title'] ?? 'N/A',
                    status: StatusEnums::tryFrom($statusData['current_status'] ?? '') ?? $this->procurementSupport->getOngoingStatusForStage($stage),
                    stage: StageEnums::tryFrom($statusData['stage'] ?? '') ?? $stage,
                    procurementMode: $this->procurementSupport->getProcurementMode($pr_number) ?? \App\Enums\ProcurementModeEnums::PUBLIC_BIDDING,
                    timestamp: $statusData['timestamp'] ?? now()->toIso8601String(),
                    userAddress: $statusData['user_address'] ?? $userAddress,
                );

                Log::info('Using STATUS stream fallback for procurement data', [
                    'pr_number' => $pr_number,
                    'title' => $procurement->title,
                ]);
            }

            // Store file temporarily and dispatch async blockchain write
            $tempPath = $file->store('temp/blockchain-uploads');
            $jobId = Str::uuid()->toString();

            BlockchainWriteJob::dispatch('upload_document', [
                'pr_number' => $pr_number,
                'procurement_title' => $procurement->title,
                'user_address' => $userAddress,
                'stage' => $stage->value,
                'status' => $procurement->status,
                'current_status' => (StatusEnums::tryFrom($procurement->status) ?? $this->procurementSupport->getOngoingStatusForStage($stage))->value,
                'document_type' => $documentType->value,
                'uploaded_by' => $user->name,
                'description' => $request->input('description'),
                'stage_metadata' => $request->input('metadata', []),
                'temp_file_path' => $tempPath,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            ], $jobId, $user->id);

            return response()->json([
                'job_id' => $jobId,
                'status' => 'pending',
                'document_type' => $documentType->getDisplayName(),
            ], 202);
        } catch (\Exception $e) {
            Log::error('Failed to upload single document', [
                'pr_number' => $pr_number,
                'stage' => $stage->value,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'An error occurred while uploading the document'], 500);
        }
    }

    /**
     * Get document upload guide for a specific stage (API endpoint for frontend).
     */
    public function documentGuide(Request $request, string $pr_number, StageEnums $stage): JsonResponse
    {
        $this->authorize('view-procurement', $pr_number);

        $this->procurementSupport->validateStageInWorkflow($pr_number, $stage);

        $mode = $this->procurementSupport->getProcurementMode($pr_number);
        $guide = $this->modeAwareValidationService->getStageDocumentGuide($stage, $mode);

        return response()->json($guide);
    }

    /**
     * Mark a procurement stage as complete.
     */
    public function markStageComplete(Request $request, string $pr_number, StageEnums $stage): JsonResponse
    {
        $this->authorize('view-procurement', $pr_number);

        // Validate that stage exists in the procurement's mode workflow
        $this->procurementSupport->validateStageInWorkflow($pr_number, $stage);

        try {
            // Verify all required documents are uploaded
            $uploadedDocuments = $this->procurementSupport->getUploadedDocumentTypes($pr_number, $stage);
            $mode = $this->procurementSupport->getProcurementMode($pr_number);
            $documentGuide = $this->modeAwareValidationService->getStageDocumentGuide($stage, $mode);

            if (count($uploadedDocuments) < $documentGuide['counts']['required_count']) {
                return response()->json(['error' => 'Cannot mark stage as complete. Please upload all required documents first.'], 422);
            }

            // Get procurement data
            $procurement = app(\App\Repositories\ProcurementRepository::class)->findByProcurement($pr_number);
            if (! $procurement) {
                return response()->json(['error' => 'Procurement not found.'], 404);
            }

            $user = auth()->user();
            $userAddress = $user->blockchain_address ?? $user->email;
            $previousStatus = StatusEnums::tryFrom($procurement->status);
            $completionStatus = $this->procurementSupport->getCompletionStatusForStage($stage);

            // Resolve next stage synchronously (reads workflow config + cache)
            $nextStage = $this->procurementSupport->getNextStageForProcurement($pr_number, $stage);
            $nextStageStatus = $nextStage ? $this->procurementSupport->getInitialStatusForStage($pr_number, $nextStage) : null;

            // Dispatch all blockchain writes to the queue
            $jobId = Str::uuid()->toString();

            BlockchainWriteJob::dispatch('mark_stage_complete', [
                'pr_number' => $pr_number,
                'procurement_title' => $procurement->title,
                'user_address' => $userAddress,
                'current_stage' => $stage->value,
                'completion_status' => $completionStatus->value,
                'previous_status' => $previousStatus?->value,
                'next_stage' => $nextStage?->value,
                'next_stage_status' => $nextStageStatus?->value,
                'procurement_mode' => $procurement->procurementMode->value,
                'document_count' => count($uploadedDocuments),
                'is_pre_procurement' => $stage->isPreProcurement(),
            ], $jobId, $user->id);

            return response()->json([
                'job_id' => $jobId,
                'status' => 'pending',
                'next_stage' => $nextStage?->value,
                'next_stage_name' => $nextStage?->getDisplayName(),
            ], 202);
        } catch (\Exception $e) {
            Log::error('Error marking stage as complete', [
                'pr_number' => $pr_number,
                'stage' => $stage->value,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to mark stage as complete. Please try again.'], 500);
        }
    }

    /**
     * Skip an optional stage and proceed to the next stage.
     */
    public function skipStage(Request $request, string $pr_number, StageEnums $stage): JsonResponse
    {
        $this->authorize('view-procurement', $pr_number);

        $this->procurementSupport->validateStageInWorkflow($pr_number, $stage);

        try {
            $jobId = Str::uuid()->toString();
            BlockchainWriteJob::dispatch('skip_stage', [
                'pr_number' => $pr_number,
                'stage' => $stage->value,
                'reason' => $request->input('reason', 'Stage marked as optional and skipped by user.'),
                'user_address' => auth()->user()->blockchain_address ?? auth()->user()->email,
            ], $jobId, auth()->id());

            return response()->json(['job_id' => $jobId, 'status' => 'pending'], 202);
        } catch (\Exception $e) {
            Log::error('Error skipping stage', [
                'pr_number' => $pr_number,
                'stage' => $stage->value,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Check stage completion status (API endpoint for frontend).
     */
    public function checkCompletion(string $pr_number, StageEnums $stage): JsonResponse
    {
        $this->authorize('view-procurement', $pr_number);

        $this->procurementSupport->validateStageInWorkflow($pr_number, $stage);

        $completionCheck = $this->validationService->validateStageCompletion($stage, []);

        return response()->json($completionCheck);
    }

    /**
     * Validate a specific document upload (API endpoint for frontend real-time validation).
     */
    public function validateUpload(Request $request, string $pr_number, StageEnums $stage): JsonResponse
    {
        $this->authorize('view-procurement', $pr_number);

        $this->procurementSupport->validateStageInWorkflow($pr_number, $stage);

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
        $mode = $this->procurementSupport->getProcurementMode($pr_number);

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

    // ==========================================
    // Pre-Procurement Phase Specific Methods
    // ==========================================

    /**
     * Handle Pre-Procurement Conference decision (held or skipped).
     * This is a special method for Stage 2 only.
     */
    public function publishDecision(PreProcurementConferenceDecisionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return $this->handleDecisionPublishing(
            decisionType: 'pre_procurement_conference',
            prNumber: $validated['pr_number'],
            procurementTitle: $validated['procurement_title'],
            wasHeld: $validated['conference_held'],
        );
    }

    /**
     * Handle Pre-Bid Conference decision (held or skipped).
     * This is part of Stage 3 (Bidding Documents) workflow.
     */
    public function publishPreBidDecision(PreBidConferenceDecisionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return $this->handleDecisionPublishing(
            decisionType: 'pre_bid_conference',
            prNumber: $validated['pr_number'],
            procurementTitle: $validated['procurement_title'],
            wasHeld: $validated['conference_held'],
        );
    }

    /**
     * Handle Supplemental Bid Bulletin decision (needed or skipped).
     * This is part of Stage 3 (Bidding Documents) workflow.
     */
    public function publishSupplementalBidBulletinDecision(SupplementalBidBulletinDecisionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return $this->handleDecisionPublishing(
            decisionType: 'supplemental_bid_bulletin',
            prNumber: $validated['pr_number'],
            procurementTitle: $validated['procurement_title'],
            wasHeld: $validated['supplemental_bid_needed'],
        );
    }

    // ==========================================
    // Procurement Phase Specific Methods
    // ==========================================

    /**
     * Repeat a stage to issue another document (e.g., another Supplemental Bid Bulletin).
     * Per NGPA IRR, some stages like Supplemental Bid Bulletin can be issued multiple times.
     */
    public function repeatStage(Request $request, string $pr_number, StageEnums $stage): JsonResponse
    {
        $this->authorize('view-procurement', $pr_number);

        if (! $stage->isProcurement()) {
            abort(403, 'Invalid stage for Procurement phase');
        }

        if (! $stage->canRepeat()) {
            return response()->json(['error' => 'This stage cannot be repeated.'], 422);
        }

        $this->procurementSupport->validateStageInWorkflow($pr_number, $stage);

        try {
            $jobId = Str::uuid()->toString();
            BlockchainWriteJob::dispatch('repeat_stage', [
                'pr_number' => $pr_number,
                'stage' => $stage->value,
                'reason' => $request->input('reason', 'Additional bulletin required'),
                'user_address' => auth()->user()->blockchain_address ?? auth()->user()->email,
            ], $jobId, auth()->id());

            return response()->json(['job_id' => $jobId, 'status' => 'pending'], 202);
        } catch (\Exception $e) {
            Log::error('Error repeating stage', [
                'pr_number' => $pr_number,
                'stage' => $stage->value,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to repeat stage. Please try again.'], 500);
        }
    }

    // ==========================================
    // Post-Procurement Phase Specific Methods
    // ==========================================

    /**
     * Update delivery details for a procurement at the Notice to Proceed stage.
     *
     * Per NGPA IRR Section 71, delivery details should be specified at the
     * Contract Implementation stage (Notice to Proceed).
     */
    public function updateDeliveryDetails(
        \App\Http\Requests\Procurement\UpdateDeliveryDetailsRequest $request,
        string $pr_number
    ): JsonResponse {
        $this->authorize('view-procurement', $pr_number);

        try {
            $jobId = Str::uuid()->toString();
            BlockchainWriteJob::dispatch('update_delivery_details', [
                'pr_number' => $pr_number,
                'delivery_location' => $request->input('delivery_location'),
                'delivery_date' => $request->input('delivery_date'),
                'delivery_term_days' => (int) $request->input('delivery_term_days'),
                'user_address' => auth()->user()->blockchain_address ?? auth()->user()->email,
            ], $jobId, auth()->id());

            return response()->json(['job_id' => $jobId, 'status' => 'pending'], 202);
        } catch (\Exception $e) {
            Log::error('Failed to update delivery details', [
                'pr_number' => $pr_number,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Failed to update delivery details. Please try again.'], 500);
        }
    }

    // ==========================================
    // Private Helper Methods
    // ==========================================

    /**
     * Unified handler for publishing procurement decisions.
     */
    private function handleDecisionPublishing(
        string $decisionType,
        string $prNumber,
        string $procurementTitle,
        bool $wasHeld,
    ): JsonResponse {
        $this->authorize('view-procurement', $prNumber);

        try {
            $jobId = Str::uuid()->toString();
            BlockchainWriteJob::dispatch('publish_decision', [
                'decision_type' => $decisionType,
                'pr_number' => $prNumber,
                'procurement_title' => $procurementTitle,
                'was_held' => $wasHeld,
                'user_address' => auth()->user()->blockchain_address ?? auth()->user()->email,
            ], $jobId, auth()->id());

            return response()->json([
                'job_id' => $jobId,
                'status' => 'pending',
                'held' => $wasHeld,
            ], 202);
        } catch (\Exception $e) {
            Log::error("Failed to dispatch {$decisionType} decision job", [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to publish decision to blockchain. Please try again.'], 500);
        }
    }

    /**
     * Resolve document type enum from field name.
     */
    protected function resolveDocumentType(string $fieldName, StageEnums $stage): DocumentTypeEnums
    {
        // Unified mapping for all phases
        $mapping = [
            // Pre-Procurement Phase (Stage 2: Pre-Procurement Conference)
            'minutes' => $stage->isPreProcurement()
                ? DocumentTypeEnums::PRE_PROCUREMENT_MINUTES
                : DocumentTypeEnums::PRE_BID_MINUTES,
            'attendance' => $stage->isPreProcurement()
                ? DocumentTypeEnums::PRE_PROCUREMENT_ATTENDANCE
                : DocumentTypeEnums::PRE_BID_ATTENDANCE,
            'agenda' => $stage->isPreProcurement()
                ? DocumentTypeEnums::PRE_PROCUREMENT_AGENDA
                : DocumentTypeEnums::PRE_BID_AGENDA,

            // Pre-Procurement Phase (Stage 3: Bidding Documents)
            'bid_documents' => DocumentTypeEnums::INVITATION_TO_BID,
            'technical_specifications' => DocumentTypeEnums::BIDDING_TECHNICAL_SPECIFICATIONS,
            'instructions_to_bidders' => DocumentTypeEnums::INSTRUCTIONS_TO_BIDDERS,

            // Procurement Phase (Stage 4: Pre-Bid Conference)
            'clarifications' => DocumentTypeEnums::EVALUATION_CLARIFICATIONS,

            // Procurement Phase (Stage 5: Supplemental/Bid Bulletin)
            'bulletin' => DocumentTypeEnums::SUPPLEMENTAL_BID_BULLETIN,
            'addendum' => DocumentTypeEnums::SUPPLEMENTAL_BID_BULLETIN,
            'revised_specifications' => DocumentTypeEnums::BIDDING_TECHNICAL_SPECIFICATIONS,

            // Procurement Phase (Stage 6: Bid Submission/Opening)
            'abstract_of_bids' => DocumentTypeEnums::ABSTRACT_OF_BIDS,
            'bid_opening_minutes' => DocumentTypeEnums::BID_OPENING_MINUTES,
            'bidders_list' => DocumentTypeEnums::BID_SUBMISSION_REGISTER,

            // Procurement Phase (Stage 7: Bid Evaluation)
            'evaluation_report' => DocumentTypeEnums::BID_EVALUATION_REPORT,
            'technical_evaluation' => DocumentTypeEnums::TECHNICAL_EVALUATION_REPORT,
            'financial_evaluation' => DocumentTypeEnums::FINANCIAL_EVALUATION_REPORT,

            // Procurement Phase (Stage 8: Post-Qualification)
            'post_qual_report' => DocumentTypeEnums::POST_QUALIFICATION_REPORT,
            'site_inspection' => DocumentTypeEnums::SITE_VISIT_REPORT,
            'compliance_check' => DocumentTypeEnums::DOCUMENT_VERIFICATION_CHECKLIST,

            // Procurement Phase (Stage 9: BAC Resolution)
            'bac_resolution' => DocumentTypeEnums::BAC_RESOLUTION_AWARD,
            'recommendation' => DocumentTypeEnums::BAC_RESOLUTION_AWARD,
            'approval_document' => DocumentTypeEnums::TRANSMITTAL_TO_HOPE,

            // Post-Procurement Phase (Stage 10: Notice of Award)
            'notice_of_award' => DocumentTypeEnums::NOTICE_OF_AWARD,
            'noa' => DocumentTypeEnums::NOTICE_OF_AWARD,
            'award_letter' => DocumentTypeEnums::NOTICE_OF_AWARD,
            'award_approval' => DocumentTypeEnums::HOPE_APPROVAL,

            // Post-Procurement Phase (Stage 11: Performance Bond/Contract/PO)
            'performance_bond' => DocumentTypeEnums::PERFORMANCE_BOND,
            'contract' => DocumentTypeEnums::CONTRACT,
            'purchase_order' => DocumentTypeEnums::PURCHASE_ORDER,
            'po' => DocumentTypeEnums::PURCHASE_ORDER,

            // Post-Procurement Phase (Stage 12: Notice to Proceed)
            'notice_to_proceed' => DocumentTypeEnums::NOTICE_TO_PROCEED,
            'ntp' => DocumentTypeEnums::NOTICE_TO_PROCEED,
            'commencement_order' => DocumentTypeEnums::NOTICE_TO_PROCEED,

            // Post-Procurement Phase (Stage 13: Monitoring)
            'progress_report' => DocumentTypeEnums::PROGRESS_REPORTS,
            'inspection_report' => DocumentTypeEnums::SITE_INSPECTION_REPORTS,
            'monitoring_report' => DocumentTypeEnums::MONITORING_REPORTS,

            // Post-Procurement Phase (Stage 14: Completion)
            'acceptance_certificate' => DocumentTypeEnums::CERTIFICATE_FINAL_ACCEPTANCE,
            'inspection_acceptance' => DocumentTypeEnums::INSPECTION_ACCEPTANCE_REPORT,
            'turnover_documents' => DocumentTypeEnums::TURNOVER_DOCUMENTS,
            'completion_certificate' => DocumentTypeEnums::CERTIFICATE_OF_COMPLETION,
            'certificate_of_completion' => DocumentTypeEnums::CERTIFICATE_OF_COMPLETION,

            // Post-Procurement Phase (Stage 15: Completed)
            'final_report' => DocumentTypeEnums::PROJECT_COMPLETION_REPORT,
            'warranty_certificate' => DocumentTypeEnums::WARRANTY_DOCUMENTS,
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
}
