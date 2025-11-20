<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Http\Controllers\Procurement\Concerns\HasProcurementSupport;
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
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles Post-Procurement Phase (Stages 10-15):
 * - Stage 10: Notice of Award
 * - Stage 11: Performance Bond/Contract/PO
 * - Stage 12: Notice to Proceed
 * - Stage 13: Monitoring
 * - Stage 14: Acceptance & Turnover
 * - Stage 15: Completed
 */
class PostProcurementController extends BaseController
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
     * Display the upload page for a specific post-procurement stage.
     */
    public function show(Request $request, string $pr_number, StageEnums $stage): Response
    {
        // Validate that stage is in post-procurement phase
        if (! $stage->isPostProcurement()) {
            abort(403, 'Invalid stage for Post-Procurement phase');
        }

        $procurement = $this->findProcurementById($pr_number);

        // Determine which Inertia component to render based on stage
        $component = match ($stage) {
            StageEnums::NOTICE_OF_AWARD => 'bac-secretariat/procurement-stage/noa-upload',
            // Frontend file is `performance-bond-contract-po-upload.tsx`
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO => 'bac-secretariat/procurement-stage/performance-bond-contract-po-upload',
            // Frontend file is `ntp-upload.tsx`
            StageEnums::NOTICE_TO_PROCEED => 'bac-secretariat/procurement-stage/ntp-upload',
            StageEnums::MONITORING => 'bac-secretariat/procurement-stage/monitoring-upload',
            StageEnums::COMPLETION => 'bac-secretariat/procurement-stage/completion-upload',
            // There is no dedicated "completed" page; render completion upload as a fallback
            StageEnums::COMPLETED => 'bac-secretariat/procurement-stage/completion-upload',
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
     * Upload documents for a specific post-procurement stage.
     */
    public function uploadDocuments(Request $request, string $pr_number, StageEnums $stage): RedirectResponse
    {
        // Validate that stage is in post-procurement phase
        if (! $stage->isPostProcurement()) {
            abort(403, 'Invalid stage for Post-Procurement phase');
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
                // Extract document type from field name
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

                // Publish document workflow to blockchain (document + status + event)
                $procurement = app(\App\Repositories\ProcurementRepository::class)->findByProcurement($pr_number);
                $procurementTitle = $procurement?->title ?? 'Unknown';

                $this->orchestrator->publishDocumentWorkflow(
                    procurementData: [
                        'pr_number' => $pr_number,
                        'procurement_title' => $procurementTitle,
                        'user_address' => $userAddress,
                    ],
                    file: $file,
                    documentData: [
                        'stage' => $stage,
                        'status' => $procurement?->currentStatus ?? 'in_progress',
                        'document_type' => $documentType,
                        'uploaded_by' => $user->name,
                        'description' => $metadata['description'] ?? null,
                        'stage_metadata' => $metadata,
                    ],
                    statusData: [
                        'stage' => $stage,
                        'current_status' => StatusEnums::tryFrom($procurement?->status ?? 'in_progress') ?? StatusEnums::PROCUREMENT_SUBMITTED,
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

            // Check if stage is ready for completion
            $completionCheck = $this->validationService->validateStageCompletion($stage, []);

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

                    $procurement = $this->findProcurementById($pr_number);
                    $this->eventPublisher->publishStageTransition(
                        $pr_number,
                        $procurement['title'] ?? 'Unknown',
                        $stage->value,
                        $nextStage->value,
                        $userAddress
                    );
                }

                // If completed stage, mark procurement as completed
                if ($stage === StageEnums::COMPLETION) {
                    $procurement = $this->findProcurementById($pr_number);
                    $this->statusPublisher->publish(
                        $pr_number,
                        $procurement['title'] ?? 'Unknown',
                        StageEnums::COMPLETED,
                        StatusEnums::PROCUREMENT_SUBMITTED,
                        $userAddress
                    );
                }

                return redirect()->route('bac-secretariat.blockchain.publishing-status', [
                    'id' => $pr_number,
                ])->with('success', 'Documents uploaded and stage completed successfully.');
            }

            return redirect()->back()->with('success', 'Documents uploaded successfully. Please upload remaining required documents.');
        } catch (\Exception $e) {
            \Log::error('Failed to upload post-procurement documents', [
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
        // Validate that stage is in post-procurement phase
        if (! $stage->isPostProcurement()) {
            return back()->withErrors(['message' => 'Invalid stage for Post-Procurement phase']);
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
        if (! $stage->isPostProcurement()) {
            abort(403, 'Invalid stage for Post-Procurement phase');
        }

        $guide = $this->validationService->getStageDocumentGuide($stage);

        return response()->json($guide);
    }

    /**
     * Mark a procurement stage as complete.
     */
    public function markStageComplete(Request $request, string $pr_number, StageEnums $stage): RedirectResponse
    {
        if (! $stage->isPostProcurement()) {
            abort(403, 'Invalid stage for Post-Procurement phase');
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
            if ($stage === StageEnums::NOTICE_OF_AWARD) {
                // Automatically transition to PERFORMANCE_BOND_CONTRACT_AND_PO stage
                $this->statusPublisher->publishTransition(
                    prNumber: $pr_number,
                    procurementTitle: $procurement->title,
                    fromStage: StageEnums::NOTICE_OF_AWARD,
                    toStage: StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
                    currentStatus: StatusEnums::AWARDED,
                    userAddress: $userAddress
                );

                $this->eventPublisher->publishStageTransition(
                    prNumber: $pr_number,
                    procurementTitle: $procurement->title,
                    fromStage: StageEnums::NOTICE_OF_AWARD->value,
                    toStage: StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO->value,
                    userAddress: $userAddress
                );

                return back()->with('success', [
                    'message' => 'Notice of Award marked as complete successfully! Proceeding to Performance Bond, Contract & PO stage.',
                    'blockchain' => [
                        'status_txid' => $statusResult['status_txid'] ?? null,
                        'event_txid' => $eventResult['event_txid'] ?? null,
                        'stage' => $stage->value,
                        'completion_status' => $completionStatus->value,
                    ],
                ]);
            }

            if ($stage === StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO) {
                // Automatically transition to NOTICE_TO_PROCEED stage
                $this->statusPublisher->publishTransition(
                    prNumber: $pr_number,
                    procurementTitle: $procurement->title,
                    fromStage: StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
                    toStage: StageEnums::NOTICE_TO_PROCEED,
                    currentStatus: StatusEnums::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED,
                    userAddress: $userAddress
                );

                $this->eventPublisher->publishStageTransition(
                    prNumber: $pr_number,
                    procurementTitle: $procurement->title,
                    fromStage: StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO->value,
                    toStage: StageEnums::NOTICE_TO_PROCEED->value,
                    userAddress: $userAddress
                );

                return back()->with('success', [
                    'message' => 'Performance Bond marked as complete successfully! Proceeding to Notice to Proceed stage.',
                    'blockchain' => [
                        'status_txid' => $statusResult['status_txid'] ?? null,
                        'event_txid' => $eventResult['event_txid'] ?? null,
                        'stage' => $stage->value,
                        'completion_status' => $completionStatus->value,
                    ],
                ]);
            }

            if ($stage === StageEnums::NOTICE_TO_PROCEED) {
                // Automatically transition to MONITORING stage
                $this->statusPublisher->publishTransition(
                    prNumber: $pr_number,
                    procurementTitle: $procurement->title,
                    fromStage: StageEnums::NOTICE_TO_PROCEED,
                    toStage: StageEnums::MONITORING,
                    currentStatus: StatusEnums::NTP_RECORDED,
                    userAddress: $userAddress
                );

                $this->eventPublisher->publishStageTransition(
                    prNumber: $pr_number,
                    procurementTitle: $procurement->title,
                    fromStage: StageEnums::NOTICE_TO_PROCEED->value,
                    toStage: StageEnums::MONITORING->value,
                    userAddress: $userAddress
                );

                return back()->with('success', [
                    'message' => 'Notice to Proceed marked as complete successfully! Proceeding to Monitoring stage.',
                    'blockchain' => [
                        'status_txid' => $statusResult['status_txid'] ?? null,
                        'event_txid' => $eventResult['event_txid'] ?? null,
                        'stage' => $stage->value,
                        'completion_status' => $completionStatus->value,
                    ],
                ]);
            }

            if ($stage === StageEnums::MONITORING) {
                // Automatically transition to COMPLETION stage
                $this->statusPublisher->publishTransition(
                    prNumber: $pr_number,
                    procurementTitle: $procurement->title,
                    fromStage: StageEnums::MONITORING,
                    toStage: StageEnums::COMPLETION,
                    currentStatus: StatusEnums::MONITORING_COMPLETED,
                    userAddress: $userAddress
                );

                $this->eventPublisher->publishStageTransition(
                    prNumber: $pr_number,
                    procurementTitle: $procurement->title,
                    fromStage: StageEnums::MONITORING->value,
                    toStage: StageEnums::COMPLETION->value,
                    userAddress: $userAddress
                );

                return back()->with('success', [
                    'message' => 'Monitoring marked as complete successfully! Proceeding to Completion stage.',
                    'blockchain' => [
                        'status_txid' => $statusResult['status_txid'] ?? null,
                        'event_txid' => $eventResult['event_txid'] ?? null,
                        'stage' => $stage->value,
                        'completion_status' => $completionStatus->value,
                    ],
                ]);
            }

            if ($stage === StageEnums::COMPLETION) {
                // Automatically transition to COMPLETED stage (final stage)
                $this->statusPublisher->publishTransition(
                    prNumber: $pr_number,
                    procurementTitle: $procurement->title,
                    fromStage: StageEnums::COMPLETION,
                    toStage: StageEnums::COMPLETED,
                    currentStatus: StatusEnums::COMPLETED,
                    userAddress: $userAddress
                );

                $this->eventPublisher->publishStageTransition(
                    prNumber: $pr_number,
                    procurementTitle: $procurement->title,
                    fromStage: StageEnums::COMPLETION->value,
                    toStage: StageEnums::COMPLETED->value,
                    userAddress: $userAddress
                );

                return back()->with('success', [
                    'message' => 'Completion marked as complete successfully! Procurement is now fully completed.',
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
            StageEnums::NOTICE_OF_AWARD => StatusEnums::AWARDED,
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO => StatusEnums::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED,
            StageEnums::NOTICE_TO_PROCEED => StatusEnums::NTP_RECORDED,
            StageEnums::MONITORING => StatusEnums::MONITORING_COMPLETED,
            StageEnums::COMPLETION => StatusEnums::COMPLETION_DOCUMENTS_UPLOADED,
            StageEnums::COMPLETED => StatusEnums::COMPLETED,
            default => StatusEnums::PROCUREMENT_SUBMITTED,
        };
    }

    /**
     * Check stage completion status (API endpoint for frontend).
     */
    public function checkCompletion(string $pr_number, StageEnums $stage): JsonResponse
    {
        if (! $stage->isPostProcurement()) {
            abort(403, 'Invalid stage for Post-Procurement phase');
        }

        $completionCheck = $this->validationService->validateStageCompletion($stage, []);

        return response()->json($completionCheck);
    }

    /**
     * Validate a specific document upload (API endpoint for frontend real-time validation).
     */
    public function validateUpload(Request $request, string $pr_number, StageEnums $stage): JsonResponse
    {
        if (! $stage->isPostProcurement()) {
            abort(403, 'Invalid stage for Post-Procurement phase');
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
     * Resolve document type enum from field name.
     */
    protected function resolveDocumentType(string $fieldName, StageEnums $stage): DocumentTypeEnums
    {
        // Map common field names to DocumentTypeEnums
        $mapping = [
            // Stage 10: Notice of Award
            'notice_of_award' => DocumentTypeEnums::NOTICE_OF_AWARD,
            'noa' => DocumentTypeEnums::NOTICE_OF_AWARD,
            'award_letter' => DocumentTypeEnums::NOTICE_OF_AWARD,
            'award_approval' => DocumentTypeEnums::HOPE_APPROVAL,

            // Stage 11: Performance Bond/Contract/PO
            'performance_bond' => DocumentTypeEnums::PERFORMANCE_BOND,
            'contract' => DocumentTypeEnums::CONTRACT,
            'purchase_order' => DocumentTypeEnums::PURCHASE_ORDER,
            'po' => DocumentTypeEnums::PURCHASE_ORDER,

            // Stage 12: Notice to Proceed
            'notice_to_proceed' => DocumentTypeEnums::NOTICE_TO_PROCEED,
            'ntp' => DocumentTypeEnums::NOTICE_TO_PROCEED,
            'commencement_order' => DocumentTypeEnums::NOTICE_TO_PROCEED,

            // Stage 13: Monitoring
            'progress_report' => DocumentTypeEnums::PROGRESS_REPORTS,
            'inspection_report' => DocumentTypeEnums::SITE_INSPECTION_REPORTS,
            'monitoring_report' => DocumentTypeEnums::MONITORING_REPORTS,

            // Stage 14: Completion
            'acceptance_certificate' => DocumentTypeEnums::CERTIFICATE_FINAL_ACCEPTANCE,
            'inspection_acceptance' => DocumentTypeEnums::INSPECTION_ACCEPTANCE_REPORT,
            'turnover_documents' => DocumentTypeEnums::TURNOVER_DOCUMENTS,
            'completion_certificate' => DocumentTypeEnums::CERTIFICATE_OF_COMPLETION,
            'certificate_of_completion' => DocumentTypeEnums::CERTIFICATE_OF_COMPLETION,

            // Stage 15: Completed
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

    /**
     * Get the next stage after the current one.
     */
    protected function getNextStage(StageEnums $stage): ?StageEnums
    {
        return match ($stage) {
            StageEnums::NOTICE_OF_AWARD => StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO => StageEnums::NOTICE_TO_PROCEED,
            StageEnums::NOTICE_TO_PROCEED => StageEnums::MONITORING,
            StageEnums::MONITORING => StageEnums::COMPLETION,
            StageEnums::COMPLETION => StageEnums::COMPLETED,
            default => null,
        };
    }
}
