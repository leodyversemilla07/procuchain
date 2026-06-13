<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Procurement\PreBidConferenceDecisionRequest;
use App\Http\Requests\Procurement\PreProcurementConferenceDecisionRequest;
use App\Http\Requests\Procurement\SupplementalBidBulletinDecisionRequest;
use App\Http\Requests\Procurement\UpdateDeliveryDetailsRequest;
use App\Http\Requests\Procurement\UploadSingleDocumentRequest;
use App\Services\AuditLogService;
use App\Services\Procurement\ProcurementStageCompletionService;
use App\Services\Procurement\ProcurementStageMutationService;
use App\Services\Procurement\ProcurementStagePageService;
use App\Services\Procurement\ProcurementStageUploadService;
use App\Services\Procurement\ProcurementSupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function __construct(
        protected ProcurementSupportService $procurementSupport,
        protected ProcurementStagePageService $stagePageService,
        protected ProcurementStageUploadService $stageUploadService,
        protected ProcurementStageCompletionService $stageCompletionService,
        protected ProcurementStageMutationService $stageMutationService,
        protected AuditLogService $AuditLogService,
    ) {}

    /**
     * Display the upload page for a specific procurement stage.
     */
    public function show(Request $request, string $pr_number, StageEnums $stage): Response
    {
        $this->authorize('view-procurement', $pr_number);

        return Inertia::render('bac-secretariat/stage-upload', $this->stagePageService->buildStagePageData($pr_number, $stage));
    }

    /**
     * Upload a single document for progressive upload workflow.
     */
    public function uploadSingleDocument(
        UploadSingleDocumentRequest $request,
        string $pr_number,
        StageEnums $stage
    ): JsonResponse {
        $this->authorize('upload-document', $pr_number);

        $user = $request->user();

        try {
            $documentTypeValue = $request->input('document_type');
            $documentType = DocumentTypeEnums::tryFrom($documentTypeValue);

            if (! $documentType) {
                return back()->withErrors(['document_type' => 'Invalid document type provided']);
            }

            $response = $this->stageUploadService->queueDocumentUpload(
                $pr_number,
                $stage,
                $request->File('document_File'),
                $documentType,
                $request->input('description'),
                $request->input('metadata', []),
                $user,
            );

            $this->AuditLogService->log(
                'procurement.document_uploaded',
                'procurement',
                $pr_number,
                [],
                ['stage' => $stage->value, 'document_type' => $documentTypeValue],
            );

            return response()->json($response['data'], $response['status']);
        } catch (\Exception $e) {
            report($e);
            Log::error('Failed to upload single document', [
                'pr_number' => $pr_number,
                'stage' => $stage->value,
                'error' => 'An error occurred processing the procurement stage.',
                'trace' => sprintf('%s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()),
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

        return response()->json($this->stagePageService->getDocumentGuide($pr_number, $stage));
    }

    /**
     * Mark a procurement stage as complete.
     */
    public function markStageComplete(Request $request, string $pr_number, StageEnums $stage): JsonResponse
    {
        $this->authorize('approve-procurement', $pr_number);

        $this->procurementSupport->validateStageInWorkflow($pr_number, $stage);

        try {
            $user = $request->user();
            $response = $this->stageCompletionService->queueStageCompletion($pr_number, $stage, $user);

            $this->AuditLogService->log(
                'procurement.stage_completed',
                'procurement',
                $pr_number,
                [],
                ['stage' => $stage->value],
            );

            return response()->json($response['data'], $response['status']);
        } catch (\Exception $e) {
            report($e);
            Log::error('Error marking stage as complete', [
                'pr_number' => $pr_number,
                'stage' => $stage->value,
                'error' => 'An error occurred processing the procurement stage.',
            ]);

            return response()->json(['error' => 'Failed to mark stage as complete. Please try again.'], 500);
        }
    }

    /**
     * Skip an optional stage and proceed to the next stage.
     */
    public function skipStage(Request $request, string $pr_number, StageEnums $stage): JsonResponse
    {
        $this->authorize('approve-procurement', $pr_number);

        $this->procurementSupport->validateStageInWorkflow($pr_number, $stage);

        try {
            $result = $this->stageMutationService->queueSkipStage(
                $pr_number,
                $stage,
                $request->input('reason', 'Stage marked as optional and skipped by user.'),
                $request->user(),
            );

            $this->AuditLogService->log(
                'procurement.stage_skipped',
                'procurement',
                $pr_number,
                [],
                ['stage' => $stage->value, 'reason' => $request->input('reason', 'Stage marked as optional and skipped by user.')],
            );

            return response()->json($result, 202);
        } catch (\Exception $e) {
            report($e);
            Log::error('Error skipping stage', [
                'pr_number' => $pr_number,
                'stage' => $stage->value,
                'error' => 'An error occurred processing the procurement stage.',
            ]);

            return response()->json(['error' => 'An error occurred processing the procurement stage.'], 500);
        }
    }

    /**
     * Check stage completion status (API endpoint for frontend).
     */
    public function checkCompletion(string $pr_number, StageEnums $stage): JsonResponse
    {
        $this->authorize('view-procurement', $pr_number);

        return response()->json($this->stagePageService->getCompletionCheck($pr_number, $stage));
    }

    /**
     * Validate a specific document upload (API endpoint for frontend real-time validation).
     */
    public function validateUpload(Request $request, string $pr_number, StageEnums $stage): JsonResponse
    {
        $this->authorize('upload-document', $pr_number);

        $this->procurementSupport->validateStageInWorkflow($pr_number, $stage);

        $documentTypeValue = $request->input('document_type');
        $File = $request->File('File');

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
            request: $request,
            decisionType: 'pre_procurement_conference',
            prNumber: $validated['pr_number'],
            procurementTitle: $validated['procurement_title'],
            wasHeld: $validated['conference_held'],
            auditAction: 'procurement.decision_published',
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
            request: $request,
            decisionType: 'pre_bid_conference',
            prNumber: $validated['pr_number'],
            procurementTitle: $validated['procurement_title'],
            wasHeld: $validated['conference_held'],
            auditAction: 'procurement.pre_bid_decision_published',
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
            request: $request,
            decisionType: 'supplemental_bid_bulletin',
            prNumber: $validated['pr_number'],
            procurementTitle: $validated['procurement_title'],
            wasHeld: $validated['supplemental_bid_needed'],
            auditAction: 'procurement.supplemental_bulletin_published',
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
        $this->authorize('approve-procurement', $pr_number);

        if (! $stage->isProcurement()) {
            abort(403, 'Invalid stage for Procurement phase');
        }

        if (! $stage->canRepeat()) {
            return response()->json(['error' => 'This stage cannot be repeated.'], 422);
        }

        $this->procurementSupport->validateStageInWorkflow($pr_number, $stage);

        try {
            $result = $this->stageMutationService->queueRepeatStage(
                $pr_number,
                $stage,
                $request->input('reason', 'Additional bulletin required'),
                $request->user(),
            );

            $this->AuditLogService->log(
                'procurement.stage_repeated',
                'procurement',
                $pr_number,
                [],
                ['stage' => $stage->value, 'reason' => $request->input('reason', 'Additional bulletin required')],
            );

            return response()->json($result, 202);
        } catch (\Exception $e) {
            report($e);
            Log::error('Error repeating stage', [
                'pr_number' => $pr_number,
                'stage' => $stage->value,
                'error' => 'An error occurred processing the procurement stage.',
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
        UpdateDeliveryDetailsRequest $request,
        string $pr_number
    ): JsonResponse {
        $this->authorize('approve-procurement', $pr_number);

        try {
            $result = $this->stageMutationService->queueDeliveryDetails(
                $pr_number,
                $request->input('delivery_location'),
                $request->input('delivery_date'),
                (int) $request->input('delivery_term_days'),
                $request->user(),
            );

            $this->AuditLogService->log(
                'procurement.delivery_updated',
                'procurement',
                $pr_number,
                [],
                ['delivery_location' => $request->input('delivery_location'), 'delivery_date' => $request->input('delivery_date')],
            );

            return response()->json($result, 202);
        } catch (\Exception $e) {
            report($e);
            Log::error('Failed to update delivery details', [
                'pr_number' => $pr_number,
                'error' => 'An error occurred processing the procurement stage.',
                'trace' => sprintf('%s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()),
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
        Request $request,
        string $decisionType,
        string $prNumber,
        string $procurementTitle,
        bool $wasHeld,
        string $auditAction = 'procurement.decision_published',
    ): JsonResponse {
        $this->authorize('publish-procurement', $prNumber);

        try {
            $result = $this->stageMutationService->queueDecisionPublishing(
                $decisionType,
                $prNumber,
                $procurementTitle,
                $wasHeld,
                $request->user(),
            );

            $this->AuditLogService->log(
                $auditAction,
                'procurement',
                $prNumber,
                [],
                ['decision_type' => $decisionType, 'conference_held' => $wasHeld],
            );

            return response()->json($result, 202);
        } catch (\Exception $e) {
            report($e);
            Log::error("Failed to dispatch {$decisionType} decision job", [
                'pr_number' => $prNumber,
                'error' => 'An error occurred processing the procurement stage.',
            ]);

            return response()->json(['error' => 'Failed to publish decision to blockchain. Please try again.'], 500);
        }
    }
}
