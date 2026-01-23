<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Http\Controllers\Procurement\Concerns\HasProcurementSupport;
use App\Http\Requests\Procurement\PreBidConferenceDecisionRequest;
use App\Http\Requests\Procurement\PreProcurementConferenceDecisionRequest;
use App\Http\Requests\Procurement\SupplementalBidBulletinDecisionRequest;
use App\Services\DocumentValidationService;
use App\Services\Manager;
use App\Services\ModeAwareDocumentValidationService;
use App\Services\Procurement\StageStatusMapper;
use App\Services\ProcurementDataService;
use App\Services\Publishers\DecisionPublisher;
use App\Services\Publishers\DocumentPublisher;
use App\Services\Publishers\EventPublisher;
use App\Services\Publishers\ProcurementOrchestrator;
use App\Services\Publishers\StatusPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
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
        protected ProcurementOrchestrator $orchestrator,
        protected StageStatusMapper $statusMapper,
        protected DecisionPublisher $decisionPublisher
    ) {
        $this->initializeProcurementSupport($multichain, $documentPublisher, $statusPublisher, $eventPublisher, $procurementDataService, $documentRepository);
    }

    /**
     * Display the upload page for a specific procurement stage.
     */
    public function show(Request $request, string $pr_number, StageEnums $stage): Response
    {
        // Validate that stage exists in the procurement's mode workflow
        if (! $this->stageExistsInWorkflow($pr_number, $stage)) {
            abort(403, 'This stage is not applicable for this procurement mode');
        }

        $procurement = $this->findProcurementById($pr_number);

        // Auto-transition for post-procurement stages
        if ($stage->isPostProcurement()) {
            $this->handleAutoStageTransition($pr_number, $procurement, $stage);
        }

        // All stages now use the unified stage-upload component
        $component = 'bac-secretariat/stage-upload';

        // Get full procurement data for NTP stage to include delivery details
        $procurementData = null;
        if ($stage === StageEnums::NOTICE_TO_PROCEED) {
            $procurementData = app(\App\Repositories\ProcurementRepository::class)->findByProcurement($pr_number);
        }

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
                // Delivery details for NTP stage (per NGPA IRR Section 71)
                'delivery_location' => $procurementData?->deliveryLocation,
                'delivery_date' => $procurementData?->deliveryDate?->format('Y-m-d'),
                'delivery_date_formatted' => $procurementData?->getFormattedDeliveryDate(),
                'delivery_term_days' => $procurementData?->deliveryTermDays,
            ],
            'workflowInfo' => $this->getWorkflowInfo($pr_number, $stage),
            'documentGuide' => $this->modeAwareValidationService->getStageDocumentGuide($stage, $mode),
            'uploadedDocuments' => fn () => $this->getUploadedDocumentTypes($pr_number, $stage),
        ]);
    }

    /**
     * Upload a single document for progressive upload workflow.
     */
    public function uploadSingleDocument(
        \App\Http\Requests\Procurement\UploadSingleDocumentRequest $request,
        string $pr_number,
        StageEnums $stage
    ): RedirectResponse {
        // Validate that stage exists in the procurement's mode workflow
        $this->validateStageInWorkflow($pr_number, $stage);

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

            // Validate the single document upload with mode awareness
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
                Log::warning('Procurement not found in METADATA stream, attempting fallback to STATUS stream', [
                    'pr_number' => $pr_number,
                    'stage' => $stage->value,
                    'user' => $user->email,
                ]);

                $statusData = $this->findProcurementById($pr_number);
                if (! $statusData) {
                    Log::error('Procurement not found in both METADATA and STATUS streams', [
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
                    status: StatusEnums::tryFrom($statusData['current_status'] ?? '') ?? $this->getOngoingStatusForStage($stage),
                    stage: StageEnums::tryFrom($statusData['stage'] ?? '') ?? $stage,
                    procurementMode: $this->getProcurementMode($pr_number) ?? \App\Enums\ProcurementModeEnums::PUBLIC_BIDDING,
                    timestamp: $statusData['timestamp'] ?? now()->toIso8601String(),
                    userAddress: $statusData['user_address'] ?? $userAddress,
                );

                Log::info('Using STATUS stream fallback for procurement data', [
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
            Log::error('Failed to upload single document', [
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
        $this->validateStageInWorkflow($pr_number, $stage);

        $mode = $this->getProcurementMode($pr_number);
        $guide = $this->modeAwareValidationService->getStageDocumentGuide($stage, $mode);

        return response()->json($guide);
    }

    /**
     * Mark a procurement stage as complete.
     */
    public function markStageComplete(Request $request, string $pr_number, StageEnums $stage): RedirectResponse
    {
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

                // 6. Notify relevant parties (pre-procurement phase only)
                if ($stage->isPreProcurement()) {
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
                            nextStage: $nextStage->getDisplayName(),
                            rolesToNotify: ['bac_chairman', 'hope', 'admin']
                        );
                    } catch (\Exception $e) {
                        // Non-critical: Continue even if notification fails
                        Log::warning('Failed to send stage completion notifications', [
                            'pr_number' => $pr_number,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Special message for final completion
                $message = $nextStage === StageEnums::COMPLETED
                    ? "{$stage->getDisplayName()} marked as complete successfully! Procurement is now fully completed."
                    : "{$stage->getDisplayName()} marked as complete successfully! Proceeding to {$nextStage->getDisplayName()} stage.";

                // Get the next stage action URL using ProcurementActionService
                $actionService = app(\App\Services\Procurement\ProcurementActionService::class);
                $actions = $actionService->getActions($pr_number);
                $nextStageAction = collect($actions['workflow_actions'] ?? [])->first();

                return back()->with('success', [
                    'message' => $message,
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

            // No next stage - end of workflow
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
     * Check stage completion status (API endpoint for frontend).
     */
    public function checkCompletion(string $pr_number, StageEnums $stage): JsonResponse
    {
        $this->validateStageInWorkflow($pr_number, $stage);

        $completionCheck = $this->validationService->validateStageCompletion($stage, []);

        return response()->json($completionCheck);
    }

    /**
     * Validate a specific document upload (API endpoint for frontend real-time validation).
     */
    public function validateUpload(Request $request, string $pr_number, StageEnums $stage): JsonResponse
    {
        $this->validateStageInWorkflow($pr_number, $stage);

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

    // ==========================================
    // Pre-Procurement Phase Specific Methods
    // ==========================================

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
            $procurement = $this->findProcurementById($pr_number);
            $result = $this->decisionPublisher->publishDecision(
                decisionType: 'pre_procurement_conference',
                prNumber: $pr_number,
                procurementTitle: $validated['procurement_title'],
                wasHeld: $validated['conference_held'],
                userAddress: $userAddress,
                procurement: $procurement
            );

            if (! $result['success']) {
                return redirect()->back()->withErrors([
                    'error' => $result['error'] ?? 'Failed to publish decision to blockchain.',
                ]);
            }

            if ($result['held']) {
                $route = $this->decisionPublisher->getUploadRoute('pre_procurement_conference', $pr_number);

                return redirect()->route($route['route'], $route['params'])
                    ->with('success', 'Decision recorded. Please upload conference documents.');
            }

            return redirect()->route('bac-secretariat.procurements.index')
                ->with('success', 'Pre-Procurement Conference decision recorded successfully. Publishing to blockchain in the background.');
        } catch (\Exception $e) {
            Log::error('Failed to publish Pre-Procurement Conference decision', [
                'pr_number' => $pr_number,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to publish decision to blockchain. Please try again.',
            ]);
        }
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
            $procurement = $this->findProcurementById($pr_number);
            $result = $this->decisionPublisher->publishDecision(
                decisionType: 'pre_bid_conference',
                prNumber: $pr_number,
                procurementTitle: $validated['procurement_title'],
                wasHeld: $validated['conference_held'],
                userAddress: $userAddress,
                procurement: $procurement
            );

            if (! $result['success']) {
                return redirect()->back()->withErrors([
                    'error' => $result['error'] ?? 'Failed to publish decision to blockchain.',
                ]);
            }

            if ($result['held']) {
                $route = $this->decisionPublisher->getUploadRoute('pre_bid_conference', $pr_number);

                return redirect()->route($route['route'], $route['params'])
                    ->with('success', 'Decision recorded. Please upload conference documents.');
            }

            return redirect()->route('bac-secretariat.procurements.index')
                ->with('success', 'Pre-Bid Conference skipped. Proceeding to Supplemental Bid Bulletin stage.');
        } catch (\Exception $e) {
            Log::error('Failed to publish Pre-Bid Conference decision', [
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
            $procurement = $this->findProcurementById($pr_number);
            $result = $this->decisionPublisher->publishDecision(
                decisionType: 'supplemental_bid_bulletin',
                prNumber: $pr_number,
                procurementTitle: $validated['procurement_title'],
                wasHeld: $validated['supplemental_bid_needed'],
                userAddress: $userAddress,
                procurement: $procurement
            );

            if (! $result['success']) {
                return redirect()->back()->withErrors([
                    'error' => $result['error'] ?? 'Failed to publish decision to blockchain.',
                ]);
            }

            if ($result['held']) {
                $route = $this->decisionPublisher->getUploadRoute('supplemental_bid_bulletin', $pr_number);

                return redirect()->route($route['route'], $route['params'])
                    ->with('success', 'Decision recorded. Please upload supplemental bid bulletin documents.');
            }

            return redirect()->route('bac-secretariat.procurements.index')
                ->with('success', 'Supplemental Bid Bulletin skipped. Proceeding to Bid Opening stage.');
        } catch (\Exception $e) {
            Log::error('Failed to publish Supplemental Bid Bulletin decision', [
                'pr_number' => $pr_number,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to publish decision to blockchain. Please try again.',
            ]);
        }
    }

    // ==========================================
    // Procurement Phase Specific Methods
    // ==========================================

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
            Log::error('Error repeating stage', [
                'pr_number' => $pr_number,
                'stage' => $stage->value,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to repeat stage. Please try again.');
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
    ): RedirectResponse {
        try {
            $user = auth()->user();
            $userAddress = $user->blockchain_address ?? $user->email;

            // Get the current procurement data
            $procurementRepository = app(\App\Repositories\ProcurementRepository::class);
            $procurement = $procurementRepository->findByProcurement($pr_number);

            if (! $procurement) {
                return back()->withErrors(['message' => 'Procurement not found']);
            }

            // Create updated procurement data with delivery details
            $updatedProcurement = new \App\DataTransferObjects\ProcurementData(
                prNumber: $procurement->prNumber,
                appReference: $procurement->appReference,
                title: $procurement->title,
                description: $procurement->description,
                abcAmount: $procurement->abcAmount,
                fundingSource: $procurement->fundingSource,
                category: $procurement->category,
                procurementMode: $procurement->procurementMode,
                office: $procurement->office,
                endUser: $procurement->endUser,
                deliveryLocation: $request->input('delivery_location'),
                deliveryDate: \Carbon\Carbon::parse($request->input('delivery_date')),
                deliveryTermDays: (int) $request->input('delivery_term_days'),
                preparedBy: $procurement->preparedBy,
                bacResolutionNumber: $procurement->bacResolutionNumber,
                bacResolutionDate: $procurement->bacResolutionDate,
                philgepsReference: $procurement->philgepsReference,
                philgepsPostingDate: $procurement->philgepsPostingDate,
                approvedBy: $procurement->approvedBy,
                approvalDate: $procurement->approvalDate,
                status: $procurement->status,
                userId: $procurement->userId,
                createdAt: $procurement->createdAt,
            );

            // Update the procurement on blockchain
            $procurementRepository->update($updatedProcurement);

            // Publish event for delivery details update
            $this->eventPublisher->publish(
                prNumber: $pr_number,
                procurementTitle: $procurement->title,
                stage: StageEnums::NOTICE_TO_PROCEED->value,
                eventType: 'delivery_details_updated',
                category: 'procurement',
                severity: 'info',
                details: sprintf(
                    'Delivery details updated: Location: %s, Date: %s, Term: %d days',
                    $request->input('delivery_location'),
                    $request->input('delivery_date'),
                    $request->input('delivery_term_days')
                ),
                documentCount: 0,
                userAddress: $userAddress,
                metadata: [
                    'delivery_location' => $request->input('delivery_location'),
                    'delivery_date' => $request->input('delivery_date'),
                    'delivery_term_days' => $request->input('delivery_term_days'),
                ]
            );

            Log::info('Delivery details updated for procurement', [
                'pr_number' => $pr_number,
                'delivery_location' => $request->input('delivery_location'),
                'delivery_date' => $request->input('delivery_date'),
                'delivery_term_days' => $request->input('delivery_term_days'),
            ]);

            return back()->with('success', 'Delivery details updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update delivery details', [
                'pr_number' => $pr_number,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['message' => 'Failed to update delivery details. Please try again.']);
        }
    }

    // ==========================================
    // Private Helper Methods
    // ==========================================

    /**
     * Handle automatic stage transition when accessing a post-procurement stage.
     *
     * For example: accessing Notice of Award while still at BAC Resolution + resolution_recorded
     *
     * Uses mode-aware workflow to determine valid transitions.
     */
    private function handleAutoStageTransition(string $prNumber, array $procurement, StageEnums $targetStage): void
    {
        $currentStageValue = $procurement['stage'] ?? null;
        $currentStatusValue = $procurement['current_status'] ?? null;

        if (! $currentStageValue || ! $currentStatusValue) {
            return;
        }

        $currentStage = StageEnums::tryFrom($currentStageValue);
        $currentStatus = StatusEnums::tryFrom($currentStatusValue);

        if (! $currentStage || ! $currentStatus) {
            return;
        }

        // If already at the target stage, no transition needed
        if ($currentStage === $targetStage) {
            return;
        }

        // Get the completion status for the current stage
        $completionStatus = $this->getCompletionStatusForStage($currentStage);

        // Check if current stage is completed (status matches completion status)
        if ($currentStatus !== $completionStatus) {
            return;
        }

        // Get the mode-aware next stage for this procurement
        $expectedNextStage = $this->getNextStageForProcurement($prNumber, $currentStage);

        // Verify that the target stage is the expected next stage in the workflow
        if ($expectedNextStage !== $targetStage) {
            return;
        }

        // Perform the auto-transition
        $this->publishStageTransition($prNumber, $procurement, $currentStage, $targetStage, $currentStatus);

        Log::info('Auto stage transition triggered', [
            'pr_number' => $prNumber,
            'from_stage' => $currentStage->value,
            'to_stage' => $targetStage->value,
            'status' => $currentStatus->value,
        ]);
    }

    /**
     * Publish stage transition to blockchain.
     */
    private function publishStageTransition(
        string $prNumber,
        array $procurement,
        StageEnums $fromStage,
        StageEnums $toStage,
        StatusEnums $currentStatus
    ): void {
        try {
            $user = auth()->user();
            $userAddress = $user->blockchain_address ?? 'unknown';

            // Publish the new stage status
            $this->statusPublisher->publish(
                prNumber: $prNumber,
                procurementTitle: $procurement['procurement_title'] ?? '',
                stage: $toStage,
                currentStatus: $currentStatus,
                userAddress: $userAddress,
                metadata: [
                    'auto_transition' => true,
                    'from_stage' => $fromStage->value,
                    'to_stage' => $toStage->value,
                    'description' => sprintf('Auto-transitioned from %s to %s', $fromStage->getDisplayName(), $toStage->getDisplayName()),
                ]
            );

            // Publish event for the transition
            $this->eventPublisher->publish(
                prNumber: $prNumber,
                procurementTitle: $procurement['procurement_title'] ?? '',
                stage: $toStage->value,
                eventType: 'stage_transition',
                category: 'workflow',
                severity: 'info',
                details: sprintf('Stage transitioned from %s to %s', $fromStage->getDisplayName(), $toStage->getDisplayName()),
                documentCount: 0,
                userAddress: $userAddress,
                metadata: [
                    'auto_transition' => true,
                    'from_stage' => $fromStage->value,
                    'to_stage' => $toStage->value,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to publish stage transition', [
                'pr_number' => $prNumber,
                'from_stage' => $fromStage->value,
                'to_stage' => $toStage->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the appropriate completion status for a given stage.
     */
    private function getCompletionStatusForStage(StageEnums $stage): StatusEnums
    {
        return $this->stageStatusMapper->getCompletionStatus($stage);
    }

    /**
     * Get the ongoing status for a stage (used during document uploads).
     */
    protected function getOngoingStatusForStage(StageEnums $stage): StatusEnums
    {
        return $this->stageStatusMapper->getOngoingStatus($stage);
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
