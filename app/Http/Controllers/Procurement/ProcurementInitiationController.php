<?php

declare(strict_types=1);

namespace App\Http\Controllers\Procurement;

use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementCategoryEnums;
use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Procurement\InitiateProcurementRequest;
use App\Http\Requests\Procurement\UploadSingleDocumentRequest;
use App\Jobs\BlockchainWriteJob;
use App\Repositories\ProcurementRepository;
use App\Repositories\StatusRepository;
use App\Services\Procurement\ProcurementStageCompletionService;
use App\Services\Procurement\ProcurementStagePageService;
use App\Services\Procurement\ProcurementStageUploadService;
use App\Services\Procurement\ProcurementSupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementInitiationController extends BaseController
{
    public function __construct(
        private readonly ProcurementSupportService $procurementSupport,
        private readonly ProcurementRepository $procurements,
        private readonly ProcurementStagePageService $stagePageService,
        private readonly ProcurementStageUploadService $stageUploadService,
        private readonly ProcurementStageCompletionService $stageCompletionService,
    ) {}

    public function show(?string $id = null): Response
    {
        if ($id) {
            $this->authorize('view-procurement', $id);

            $procurement = $this->procurements->findByProcurement($id);

            if (! $procurement) {
                abort(404);
            }

            // Get the latest status from blockchain to check if stage is complete
            $statusRepo = app(StatusRepository::class);
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

            return Inertia::render('bac-secretariat/stage-upload', $this->stagePageService->buildStagePageData(
                $id,
                StageEnums::PROCUREMENT_INITIATION,
            ));
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
        $this->authorize('initiate-procurement');

        $prNumber = $request->input('pr_number');
        $user = $request->user();

        // Duplicate check stays synchronous
        $existing = $this->procurements->findByProcurement($prNumber);
        if ($existing) {
            return response()->json([
                'errors' => ['pr_number' => "PR Number {$prNumber} already exists. Please use a different PR number."],
            ], 422);
        }

        $procurementData = [
            'pr_number' => $prNumber,
            'app_reference' => $request->input('app_reference'),
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'abc_amount' => (float) $request->input('abc_amount'),
            'funding_source' => $request->input('funding_source'),
            'category' => $request->input('category'),
            'procurement_mode' => $request->input('procurement_mode'),
            'negotiated_procurement_type' => $request->input('negotiated_procurement_type'),
            'office' => $request->input('office'),
            'end_user' => $request->input('end_user'),
            'prepared_by' => $request->input('prepared_by') ?? $user->name,
            'status' => 'draft',
            'user_id' => (string) $user->id,
            'user_address' => $user->blockchain_address,
            'created_at' => now()->toIso8601String(),
        ];

        $jobId = Str::uuid()->toString();

        BlockchainWriteJob::dispatch('initiate_procurement', [
            'procurement_data' => $procurementData,
            'user_name' => $user->name,
            'pr_number' => $prNumber,
        ], $jobId, $user->id);

        return response()->json([
            'job_id' => $jobId,
            'status' => 'pending',
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
        $this->authorize('view-procurement', $pr_number);

        $stage = StageEnums::PROCUREMENT_INITIATION;
        $user = $request->user();

        try {
            $file = $request->file('document_file');
            $documentTypeValue = $request->input('document_type');
            $documentType = DocumentTypeEnums::tryFrom($documentTypeValue);

            if (! $documentType) {
                return back()->withErrors(['document_type' => 'Invalid document type provided']);
            }

            $response = $this->stageUploadService->queueDocumentUpload(
                $pr_number,
                $stage,
                $file,
                $documentType,
                $request->input('description'),
                $request->input('metadata', []),
                $user,
            );

            return response()->json($response['data'], $response['status']);
        } catch (\Exception $e) {
            \Log::error('Failed to upload single document', [
                'pr_number' => $pr_number,
                'stage' => $stage->value,
                'error' => 'An error occurred initiating the procurement.',
                'trace' => sprintf('%s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()),
            ]);

            return response()->json(['message' => 'An error occurred while uploading the document'], 500);
        }
    }

    /**
     * Validate document upload before submission (real-time validation)
     */
    public function validateUpload(Request $request, string $pr_number): JsonResponse
    {
        $this->authorize('view-procurement', $pr_number);

        $stage = StageEnums::PROCUREMENT_INITIATION;
        $documentTypeValue = $request->input('document_type');

        $documentType = DocumentTypeEnums::tryFrom($documentTypeValue);
        if (! $documentType) {
            return response()->json([
                'valid' => false,
                'errors' => ['Invalid document type'],
            ], 400);
        }

        $validation = $this->stagePageService->validateUpload($pr_number, $stage, $documentType);

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
        $this->authorize('view-procurement', $pr_number);

        $stage = StageEnums::PROCUREMENT_INITIATION;

        return response()->json($this->stagePageService->getDocumentGuide($pr_number, $stage));
    }

    /**
     * Mark the Procurement Initiation stage as complete
     */
    public function markStageComplete(Request $request, string $pr_number): JsonResponse
    {
        $this->authorize('view-procurement', $pr_number);

        $stage = StageEnums::PROCUREMENT_INITIATION;

        try {
            $response = $this->stageCompletionService->queueStageCompletion($pr_number, $stage, $request->user());

            return response()->json($response['data'], $response['status']);
        } catch (\Exception $e) {
            Log::error('Failed to mark Procurement Initiation stage as complete', [
                'pr_number' => $pr_number,
                'error' => 'An error occurred initiating the procurement.',
                'trace' => sprintf('%s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()),
            ]);

            return response()->json(['error' => 'Failed to mark stage as complete. Please try again.'], 500);
        }
    }
}
