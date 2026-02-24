<?php

declare(strict_types=1);

namespace App\Http\Controllers\Procurement;

use App\DataTransferObjects\ProcurementData;
use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementCategoryEnums;
use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Http\Controllers\Procurement\Concerns\HasProcurementSupport;
use App\Http\Requests\Procurement\InitiateProcurementRequest;
use App\Http\Requests\Procurement\UploadSingleDocumentRequest;
use App\Jobs\BlockchainWriteJob;
use App\Repositories\ProcurementRepository;
use App\Services\DocumentValidationService;
use App\Services\Manager;
use App\Services\ModeAwareDocumentValidationService;
use App\Services\ProcurementDataService;
use App\Services\Publishers\EventPublisher;
use App\Services\Publishers\ProcurementOrchestrator;
use App\Services\Publishers\StatusPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
        protected DocumentValidationService $validationService,
        protected ModeAwareDocumentValidationService $modeAwareValidationService
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

            // Get procurement mode for mode-aware document requirements
            $mode = $this->getProcurementMode($id);

            return Inertia::render('bac-secretariat/stage-upload', [
                'procurement' => [
                    'pr_number' => $id,
                    'title' => $procurement->title,
                    'status' => $procurement->status,
                    'stage_value' => StageEnums::PROCUREMENT_INITIATION->value,
                    'current_stage' => $latestStatus?->stage ?? StageEnums::PROCUREMENT_INITIATION->value,
                ],
                'workflowInfo' => $this->getWorkflowInfo($id, StageEnums::PROCUREMENT_INITIATION),
                'documentGuide' => $this->modeAwareValidationService->getStageDocumentGuide(
                    StageEnums::PROCUREMENT_INITIATION,
                    $mode
                ),
                'uploadedDocuments' => $this->getUploadedDocumentTypes(
                    $id,
                    StageEnums::PROCUREMENT_INITIATION
                ),
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
            'negotiatedProcurementTypes' => collect(ProcurementModeEnums::negotiatedProcurementSubTypes())
                ->map(fn ($label, $value) => [
                    'value' => $value,
                    'label' => $label,
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

    public function initiate(InitiateProcurementRequest $request): JsonResponse
    {
        $prNumber = $request->input('pr_number');
        $user     = auth()->user();

        // Duplicate check stays synchronous
        $existing = $this->procurements->findByProcurement($prNumber);
        if ($existing) {
            return response()->json([
                'errors' => ['pr_number' => "PR Number {$prNumber} already exists. Please use a different PR number."],
            ], 422);
        }

        $procurementData = [
            'pr_number'               => $prNumber,
            'app_reference'           => $request->input('app_reference'),
            'title'                   => $request->input('title'),
            'description'             => $request->input('description'),
            'abc_amount'              => (float) $request->input('abc_amount'),
            'funding_source'          => $request->input('funding_source'),
            'category'                => $request->input('category'),
            'procurement_mode'        => $request->input('procurement_mode'),
            'negotiated_procurement_type' => $request->input('negotiated_procurement_type'),
            'office'                  => $request->input('office'),
            'end_user'                => $request->input('end_user'),
            'prepared_by'             => $request->input('prepared_by') ?? $user->name,
            'status'                  => 'draft',
            'user_id'                 => (string) $user->id,
            'user_address'            => $user->blockchain_address,
            'created_at'              => now()->toIso8601String(),
        ];

        $jobId = Str::uuid()->toString();

        BlockchainWriteJob::dispatch('initiate_procurement', [
            'procurement_data' => $procurementData,
            'user_name'        => $user->name,
            'pr_number'        => $prNumber,
        ], $jobId);

        return response()->json([
            'job_id'    => $jobId,
            'status'    => 'pending',
            'pr_number' => $prNumber,
        ], 202);
    }

    /**
     * Upload a single document progressively after procurement initiation
     */
    public function uploadSingleDocument(
        UploadSingleDocumentRequest $request,
        string $pr_number
    ): JsonResponse {
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

            // Get procurement mode for mode-aware validation
            $mode = $this->getProcurementMode($pr_number);

            // Validate the single document upload (prevents duplicates)
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
            $procurement = $this->procurements->findByProcurement($pr_number);

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

                    return response()->json(['message' => 'Procurement not found. Please ensure the procurement has been properly initiated.'], 422);
                }

                $procurement = new \App\DataTransferObjects\ProcurementData(
                    prNumber: $pr_number,
                    title: $statusData['procurement_title'] ?? 'N/A',
                    status: \App\Enums\StatusEnums::tryFrom($statusData['current_status'] ?? '') ?? \App\Enums\StatusEnums::PROCUREMENT_SUBMITTED,
                    stage: \App\Enums\StageEnums::tryFrom($statusData['stage'] ?? '') ?? $stage,
                    procurementMode: $this->getProcurementMode($pr_number) ?? \App\Enums\ProcurementModeEnums::PUBLIC_BIDDING,
                    timestamp: $statusData['timestamp'] ?? now()->toIso8601String(),
                    userAddress: $statusData['user_address'] ?? $userAddress,
                );
            }

            // Store file temporarily and dispatch async blockchain write
            $tempPath = $file->store('temp/blockchain-uploads');
            $jobId    = Str::uuid()->toString();

            BlockchainWriteJob::dispatch('upload_document', [
                'pr_number'         => $pr_number,
                'procurement_title' => $procurement->title,
                'user_address'      => $userAddress,
                'stage'             => $stage->value,
                'status'            => $procurement->status,
                'current_status'    => (\App\Enums\StatusEnums::tryFrom($procurement->status) ?? \App\Enums\StatusEnums::PROCUREMENT_SUBMITTED)->value,
                'document_type'     => $documentType->value,
                'uploaded_by'       => $user->name,
                'description'       => $request->input('description'),
                'stage_metadata'    => $request->input('metadata', []),
                'temp_file_path'    => $tempPath,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type'         => $file->getMimeType() ?? 'application/octet-stream',
            ], $jobId);

            return response()->json([
                'job_id'        => $jobId,
                'status'        => 'pending',
                'document_type' => $documentType->getDisplayName(),
            ], 202);
        } catch (\Exception $e) {
            \Log::error('Failed to upload single document', [
                'pr_number' => $pr_number,
                'stage'     => $stage->value,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'An error occurred while uploading the document'], 500);
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

        // Get procurement mode for mode-aware validation
        $mode = $this->getProcurementMode($pr_number);

        $validation = $this->modeAwareValidationService->validateUpload(
            $stage,
            $documentType,
            $existingDocumentEnums,
            $mode
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
        $mode = $this->getProcurementMode($pr_number);
        $guide = $this->modeAwareValidationService->getStageDocumentGuide($stage, $mode);

        return response()->json($guide);
    }

    /**
     * Mark the Procurement Initiation stage as complete
     */
    public function markStageComplete(Request $request, string $pr_number): JsonResponse
    {
        $stage = StageEnums::PROCUREMENT_INITIATION;

        try {
            $uploadedDocuments = $this->getUploadedDocumentTypes($pr_number, $stage);
            $mode              = $this->getProcurementMode($pr_number);
            $documentGuide     = $this->modeAwareValidationService->getStageDocumentGuide($stage, $mode);

            if (count($uploadedDocuments) < $documentGuide['counts']['required_count']) {
                return response()->json(['error' => 'Cannot mark stage as complete. Please upload all required documents first.'], 422);
            }

            $procurement = $this->procurements->findByProcurement($pr_number);
            if (! $procurement) {
                return response()->json(['error' => 'Procurement not found.'], 404);
            }

            $user         = auth()->user();
            $userAddress  = $user->blockchain_address ?? $user->email;
            $nextStage    = $this->getNextStageForProcurement($pr_number, $stage);

            if (! $nextStage) {
                return response()->json(['error' => 'Unable to determine next stage for this procurement mode.'], 422);
            }

            $nextStageStatus = $this->getInitialStatusForStage($pr_number, $nextStage);

            $jobId = Str::uuid()->toString();

            BlockchainWriteJob::dispatch('mark_stage_complete', [
                'operation_variant' => 'initiation_complete',
                'pr_number'         => $pr_number,
                'procurement_title' => $procurement->title,
                'user_address'      => $userAddress,
                'current_stage'     => $stage->value,
                'next_stage'        => $nextStage->value,
                'next_stage_status' => $nextStageStatus->value,
                'document_count'    => count($uploadedDocuments),
            ], $jobId);

            return response()->json([
                'job_id'          => $jobId,
                'status'          => 'pending',
                'next_stage'      => $nextStage->value,
                'next_stage_name' => $nextStage->getDisplayName(),
            ], 202);
        } catch (\Exception $e) {
            Log::error('Failed to mark Procurement Initiation stage as complete', [
                'pr_number' => $pr_number,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Failed to mark stage as complete: '.$e->getMessage()], 500);
        }
    }
}
