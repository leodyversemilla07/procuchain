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

        $procurement = $this->findProcurementById($pr_number);

        // Determine which Inertia component to render based on stage
        $component = match ($stage) {
            StageEnums::PRE_BID_CONFERENCE => 'bac-secretariat/procurement-stage/pre-bid-conference-upload',
            StageEnums::SUPPLEMENTAL_BID_BULLETIN => 'bac-secretariat/procurement-stage/supplemental-bid-bulletin-upload',
            StageEnums::BID_OPENING => 'bac-secretariat/procurement-stage/bid-opening-upload',
            StageEnums::BID_EVALUATION => 'bac-secretariat/procurement-stage/bid-evaluation-upload',
            StageEnums::POST_QUALIFICATION => 'bac-secretariat/procurement-stage/post-qualification-upload',
            StageEnums::BAC_RESOLUTION => 'bac-secretariat/procurement-stage/bac-resolution-upload',
            default => abort(404, 'Stage component not found'),
        };

        return Inertia::render($component, [
            'procurement' => [
                'pr_number' => $pr_number,
                'title' => $procurement['procurement_title'] ?? '',
                'status' => $procurement['current_status'] ?? '',
                'stage' => $stage->getDisplayName(),
                'stage_value' => $stage->value,
            ],
            'documentGuide' => $this->validationService->getStageDocumentGuide($stage),
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

                    $this->eventPublisher->publishStageTransition(
                        $pr_number,
                        $stage->value,
                        $nextStage->value,
                        $userAddress
                    );
                }

                return redirect()->route('bac-secretariat.blockchain.publishing-status', [
                    'pr_number' => $pr_number,
                ])->with('success', 'Documents uploaded and stage completed successfully.');
            }

            return redirect()->back()->with('success', 'Documents uploaded successfully. Please upload remaining required documents.');
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
        if (! $stage->isProcurement()) {
            abort(403, 'Invalid stage for Procurement phase');
        }

        $guide = $this->validationService->getStageDocumentGuide($stage);

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

        try {
            $uploadedDocuments = $this->getUploadedDocumentTypes($pr_number, $stage);
            $documentGuide = $this->validationService->getStageDocumentGuide($stage);

            if (count($uploadedDocuments) < $documentGuide['counts']['required_count']) {
                return back()->with('error', 'Cannot mark stage as complete. Please upload all required documents first.');
            }

            // TODO: Implement actual stage completion logic
            return back()->with('success', 'Stage marked as complete successfully!');
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
            StageEnums::PRE_BID_CONFERENCE => StatusEnums::PRE_BID_CONFERENCE_COMPLETED,
            StageEnums::SUPPLEMENTAL_BID_BULLETIN => StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED,
            StageEnums::BID_OPENING => StatusEnums::BIDS_OPENED,
            StageEnums::BID_EVALUATION => StatusEnums::BIDS_EVALUATED,
            StageEnums::POST_QUALIFICATION => StatusEnums::POST_QUALIFICATION_VERIFIED,
            StageEnums::BAC_RESOLUTION => StatusEnums::RESOLUTION_RECORDED,
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
     * Get the next stage after the current one.
     */
    protected function getNextStage(StageEnums $stage): ?StageEnums
    {
        return match ($stage) {
            StageEnums::PRE_BID_CONFERENCE => StageEnums::SUPPLEMENTAL_BID_BULLETIN,
            StageEnums::SUPPLEMENTAL_BID_BULLETIN => StageEnums::BID_OPENING,
            StageEnums::BID_OPENING => StageEnums::BID_EVALUATION,
            StageEnums::BID_EVALUATION => StageEnums::POST_QUALIFICATION,
            StageEnums::POST_QUALIFICATION => StageEnums::BAC_RESOLUTION,
            StageEnums::BAC_RESOLUTION => StageEnums::NOTICE_OF_AWARD,
            default => null,
        };
    }
}
