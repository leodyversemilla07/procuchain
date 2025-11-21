<?php

declare(strict_types=1);

namespace App\Http\Controllers\Procurement;

use App\DataTransferObjects\ProcurementData;
use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementCategoryEnums;
use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Http\Controllers\Procurement\Concerns\HasProcurementSupport;
use App\Http\Requests\Procurement\InitiateProcurementRequest;
use App\Http\Requests\Procurement\UploadSingleDocumentRequest;
use App\Libraries\MultiChain\Manager;
use App\Repositories\ProcurementRepository;
use App\Services\DocumentValidationService;
use App\Services\ProcurementDataService;
use App\Services\Publishers\EventPublisher;
use App\Services\Publishers\ProcurementOrchestrator;
use App\Services\Publishers\StatusPublisher;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementInitiationController extends BaseController
{
    use HasProcurementSupport;

    // Issue #3 & #13 Fix: Inject orchestrator for atomic workflow operations
    public function __construct(
        Manager $multichain,
        \App\Services\Publishers\DocumentPublisher $documentPublisher,
        StatusPublisher $statusPublisher,
        EventPublisher $eventPublisher,
        ProcurementDataService $procurementDataService,
        \App\Repositories\DocumentRepository $documentRepository,
        private readonly ProcurementRepository $procurements,
        private readonly ProcurementOrchestrator $orchestrator,
        protected DocumentValidationService $validationService
    ) {
        // Initialize trait dependencies (includes statusPublisher and eventPublisher)
        $this->initializeProcurementSupport(
            $multichain,
            $documentPublisher,
            $statusPublisher,
            $eventPublisher,
            $procurementDataService,
            $documentRepository
        );

        $this->applyProcurementMiddleware();
    }

    public function index(): Response
    {
        return Inertia::render('bac-secretariat/procurement-initiation');
    }

    public function show(?string $id = null): Response
    {
        if ($id) {
            $procurement = $this->procurements->findByProcurement($id);

            if (! $procurement) {
                abort(404);
            }

            // Get the latest status from blockchain to check if stage is complete
            $statusRepo = app(\App\Repositories\StatusRepository::class);
            $statuses = $statusRepo->findByProcurement($id);
            // findByProcurement returns statuses sorted by timestamp descending (newest first)
            $latestStatus = ! empty($statuses) ? $statuses[0] : null;

            // Check if this stage has been marked complete
            // A stage is complete when there's a status record with marked_complete_at metadata
            $isStageComplete = $latestStatus
                && $latestStatus->stage === StageEnums::PROCUREMENT_INITIATION->value
                && isset($latestStatus->metadata['marked_complete_at']);

            // Debug logging
            Log::info('Stage completion check', [
                'pr_number' => $id,
                'has_status' => $latestStatus !== null,
                'stage' => $latestStatus?->stage,
                'expected_stage' => StageEnums::PROCUREMENT_INITIATION->value,
                'metadata' => $latestStatus?->metadata ?? [],
                'has_complete_marker' => isset($latestStatus?->metadata['marked_complete_at']),
                'isStageComplete' => $isStageComplete,
            ]);

            return Inertia::render('bac-secretariat/procurement-stage/procurement-initiation-show', [
                'procurement' => [
                    'pr_number' => $id,
                    'title' => $procurement->title,
                    'status' => $procurement->status,
                    'stage' => $latestStatus?->stage ?? StageEnums::PROCUREMENT_INITIATION->value,
                ],
                'documentGuide' => $this->validationService->getStageDocumentGuide(
                    StageEnums::PROCUREMENT_INITIATION
                ),
                'uploadedDocuments' => tap($this->getUploadedDocumentTypes(
                    $id,
                    StageEnums::PROCUREMENT_INITIATION
                ), function ($docs) use ($id) {
                    Log::info('Uploaded documents for frontend', [
                        'pr_number' => $id,
                        'uploaded_documents' => $docs,
                        'count' => count($docs),
                    ]);
                }),
                'currentStage' => $latestStatus?->stage ?? StageEnums::PROCUREMENT_INITIATION->value,
                'currentStatus' => $latestStatus?->currentStatus ?? $procurement->status,
                'isStageComplete' => $isStageComplete,
            ]);
        }

        return Inertia::render('bac-secretariat/procurement-initiation', [
            'categories' => collect(ProcurementCategoryEnums::cases())
                ->map(fn ($category) => [
                    'value' => $category->value,
                    'label' => $category->getDisplayName(),
                    'description' => $category->getDescription(),
                ])
                ->toArray(),
            'procurementModes' => collect(ProcurementModeEnums::cases())
                ->map(fn ($case) => [
                    'value' => $case->value,
                    'label' => $case->getDisplayName(),
                    'description' => $case->getDescription(),
                    'threshold' => $case->thresholdAmount(),
                    'requires_philgeps' => $case->requiresPhilGEPS(),
                    'requires_bac_resolution' => $case->requiresBACResolution(),
                ])
                ->values(),
            'documentTypes' => collect(DocumentTypeEnums::getInitiationDocuments())
                ->map(fn ($docType) => [
                    'value' => $docType->value,
                    'label' => $docType->getDisplayName(),
                    'description' => $docType->getDescription(),
                    'is_mandatory' => $docType->isMandatory(),
                    'requirement_summary' => $docType->getRequirementSummary(),
                ])
                ->values(),
        ]);
    }

    /**
     * Initiate procurement with complete metadata and publish to blockchain
     */
    public function initiate(InitiateProcurementRequest $request): RedirectResponse
    {
        $prNumber = $request->input('pr_number');
        $user = auth()->user();
        $userAddress = $user->blockchain_address;

        // Check if PR number already exists (Issue #5: Idempotency)
        $existing = $this->procurements->findByProcurement($prNumber);
        if ($existing) {
            return back()->withErrors([
                'pr_number' => "PR Number {$prNumber} already exists. Please use a different PR number.",
            ])->withInput();
        }

        $procurement = new ProcurementData(
            prNumber: $prNumber,
            ppmpReference: $request->input('ppmp_reference'),
            title: $request->input('title'),
            description: $request->input('description'),
            abcAmount: (float) $request->input('abc_amount'),
            fundingSource: $request->input('funding_source'),
            category: ProcurementCategoryEnums::from($request->input('category')),
            procurementMode: ProcurementModeEnums::from($request->input('procurement_mode')),
            office: $request->input('office'),
            endUser: $request->input('end_user'),
            purpose: $request->input('purpose'),
            deliveryLocation: $request->input('delivery_location'),
            deliveryDate: Carbon::parse($request->input('delivery_date')),
            deliveryTermDays: $request->input('delivery_term_days') ? (int) $request->input('delivery_term_days') : null,
            preparedBy: $request->input('prepared_by') ?? $user->name,
            bacResolutionNumber: null,
            bacResolutionDate: null,
            philgepsReference: null,
            philgepsPostingDate: null,
            approvedBy: null,
            approvalDate: null,
            status: 'draft',
            userId: (string) $user->id,
            createdAt: now(),
        );

        // Prepare files array for orchestrator
        $filesData = [];
        $requestFiles = $request->file('files', []);
        $documentTypes = $request->input('document_types', []);
        $documentDescriptions = $request->input('document_descriptions', []);

        foreach ($requestFiles as $index => $file) {
            $docTypeValue = $documentTypes[$index] ?? null;
            $docType = $docTypeValue ? DocumentTypeEnums::tryFrom($docTypeValue) : null;

            // Skip invalid document types
            if (! $docType) {
                continue;
            }

            // Check file size (skip files larger than 2MB to avoid blockchain transaction limits)
            if ($file->getSize() > 2 * 1024 * 1024) {
                Log::warning('File too large for blockchain', [
                    'pr_number' => $prNumber,
                    'filename' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                ]);

                continue;
            }

            $filesData[] = [
                'file' => $file,
                'document_type' => DocumentTypeEnums::from($docType->value),
                'description' => $documentDescriptions[$index] ?? $docType->getDescription(),
                'metadata' => [
                    'is_mandatory' => $docType->isMandatory(),
                    'requirement_summary' => $docType->getRequirementSummary(),
                ],
            ];
        }

        try {
            // Issue #3 Fix: Use orchestrator for atomic workflow
            // Blockchain is single source of truth - all operations coordinated
            $result = $this->orchestrator->initiateProcurement(
                procurementData: [
                    'pr_number' => $prNumber,
                    'ppmp_reference' => $procurement->ppmpReference,
                    'title' => $procurement->title,
                    'description' => $procurement->description,
                    'abc_amount' => $procurement->abcAmount,
                    'funding_source' => $procurement->fundingSource,
                    'category' => $procurement->category->value,
                    'procurement_mode' => $procurement->procurementMode->value,
                    'office' => $procurement->office,
                    'end_user' => $procurement->endUser,
                    'purpose' => $procurement->purpose,
                    'delivery_location' => $procurement->deliveryLocation,
                    'delivery_date' => $procurement->deliveryDate->toDateString(),
                    'delivery_term_days' => $procurement->deliveryTermDays,
                    'prepared_by' => $procurement->preparedBy,
                    'status' => $procurement->status,
                    'user_id' => $procurement->userId,
                    'user_address' => $userAddress,
                    'created_at' => $procurement->createdAt->toIso8601String(),
                ],
                files: $filesData,
                userName: $user->name
            );

            // Check result and handle accordingly
            if (! $result['success']) {
                Log::error('Orchestrator returned failure', [
                    'pr_number' => $prNumber,
                    'result' => $result,
                ]);

                return redirect()->back()->withErrors([
                    'error' => $result['message'] ?? 'Failed to initiate procurement. Please try again.',
                ])->withInput();
            }

            // Success - redirect to publishing status page
            return redirect()->route('bac-secretariat.blockchain.publishing-status', [
                'id' => $prNumber,
                'stage' => StageEnums::PROCUREMENT_INITIATION->value,
                'return_url' => route('bac-secretariat.procurements.show', $prNumber),
            ])->with('success', $result['message']);
        } catch (\Exception $e) {
            \Log::error('Failed to initiate procurement', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to initiate procurement. Please try again.',
            ]);
        }
    }

    /**
     * Upload a single document progressively after procurement initiation
     */
    public function uploadSingleDocument(
        UploadSingleDocumentRequest $request,
        string $pr_number
    ): RedirectResponse {
        $stage = StageEnums::PROCUREMENT_INITIATION;
        $user = auth()->user();
        $userAddress = $user->blockchain_address;

        try {
            // Extract file and document type from request
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

            // Validate the single document upload (prevents duplicates)
            $validation = $this->validationService->validateUpload(
                $stage,
                $documentType,
                $existingDocumentEnums
            );

            if (! empty($validation['errors'])) {
                return back()->withErrors(['message' => implode(' ', $validation['errors'])]);
            }

            // Get procurement details
            $procurement = $this->procurements->findByProcurement($pr_number);
            if (! $procurement) {
                return back()->withErrors(['message' => 'Procurement not found']);
            }

            // Publish document workflow to blockchain via orchestrator
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
                    'current_status' => \App\Enums\StatusEnums::tryFrom($procurement->status) ?? \App\Enums\StatusEnums::PROCUREMENT_SUBMITTED,
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

            // Refresh uploaded documents and check completion (for internal tracking)
            $uploadedDocuments = $this->getUploadedDocumentTypes($pr_number, $stage);
            $uploadedDocumentEnums = array_filter(
                array_map(fn ($docType) => DocumentTypeEnums::tryFrom($docType), $uploadedDocuments),
                fn ($enum) => $enum !== null
            );

            // Check stage completion status (for internal tracking)
            $completionCheck = $this->validationService->validateStageCompletion($stage, $uploadedDocumentEnums);

            // Return success with message
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
     * Validate document upload before submission (real-time validation)
     */
    public function validateUpload(Request $request, string $pr_number): JsonResponse
    {
        $stage = StageEnums::PROCUREMENT_INITIATION;
        $documentTypeValue = $request->input('document_type');

        $documentType = DocumentTypeEnums::tryFrom($documentTypeValue);
        if (! $documentType) {
            return response()->json([
                'valid' => false,
                'errors' => ['Invalid document type'],
            ], 400);
        }

        // Get already uploaded documents
        $existingDocuments = $this->getUploadedDocumentTypes($pr_number, $stage);
        $existingDocumentEnums = array_filter(
            array_map(fn ($docType) => DocumentTypeEnums::tryFrom($docType), $existingDocuments),
            fn ($enum) => $enum !== null
        );

        $validation = $this->validationService->validateUpload(
            $stage,
            $documentType,
            $existingDocumentEnums
        );

        return response()->json([
            'valid' => empty($validation['errors']),
            'errors' => $validation['errors'] ?? [],
            'warnings' => $validation['warnings'] ?? [],
        ]);
    }

    /**
     * Get document guide for Procurement Initiation stage
     */
    public function documentGuide(Request $request, string $pr_number): JsonResponse
    {
        $stage = StageEnums::PROCUREMENT_INITIATION;
        $guide = $this->validationService->getStageDocumentGuide($stage);

        return response()->json($guide);
    }

    /**
     * Mark the Procurement Initiation stage as complete
     */
    public function markStageComplete(Request $request, string $pr_number): RedirectResponse
    {
        $stage = StageEnums::PROCUREMENT_INITIATION;

        try {
            // Verify all required documents are uploaded
            $uploadedDocuments = $this->getUploadedDocumentTypes($pr_number, $stage);
            $documentGuide = $this->validationService->getStageDocumentGuide($stage);

            if (count($uploadedDocuments) < $documentGuide['counts']['required_count']) {
                return back()->with('error', 'Cannot mark stage as complete. Please upload all required documents first.');
            }

            // Get procurement data
            $procurement = $this->procurements->findByProcurement($pr_number);
            if (! $procurement) {
                return back()->with('error', 'Procurement not found.');
            }

            // Get user blockchain address or use system default
            $user = auth()->user();
            $userAddress = $user->blockchain_address ?? $user->email;

            // Transition to the next stage: PRE_PROCUREMENT_CONFERENCE
            $nextStage = StageEnums::PROCUREMENT_INITIATION;
            $completionStatus = StatusEnums::PROCUREMENT_SUBMITTED;

            // 1. Publish status update to blockchain with stage transition
            $this->statusPublisher->publish(
                prNumber: $pr_number,
                procurementTitle: $procurement->title,
                stage: $nextStage,
                currentStatus: $completionStatus,
                userAddress: $userAddress,
                previousStatus: null,
                metadata: [
                    'documents_uploaded' => count($uploadedDocuments),
                    'marked_complete_at' => now()->toIso8601String(),
                    'previous_stage' => StageEnums::PROCUREMENT_INITIATION->value,
                    'stage_transition' => true,
                ]
            );

            // 2. Publish completion event to blockchain
            $this->eventPublisher->publish(
                prNumber: $pr_number,
                procurementTitle: $procurement->title,
                stage: $nextStage->value,
                eventType: 'stage_completed',
                category: 'stage_transition',
                severity: 'info',
                details: "Stage {$stage->getDisplayName()} completed. Transitioned to {$nextStage->getDisplayName()} with status {$completionStatus->getDisplayName()}.",
                documentCount: count($uploadedDocuments),
                userAddress: $userAddress,
                metadata: [
                    'previous_stage' => $stage->value,
                    'new_stage' => $nextStage->value,
                    'completion_status' => $completionStatus->value,
                ]
            );

            return back()->with('success', "Procurement Initiation completed! Moved to {$nextStage->getDisplayName()} stage.");
        } catch (\Exception $e) {
            Log::error('Failed to mark Procurement Initiation stage as complete', [
                'pr_number' => $pr_number,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to mark stage as complete: '.$e->getMessage());
        }
    }
}
