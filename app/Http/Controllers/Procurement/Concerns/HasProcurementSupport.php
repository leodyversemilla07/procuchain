<?php

namespace App\Http\Controllers\Procurement\Concerns;

use App\DataTransferObjects\ProcurementData;
use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Repositories\ProcurementRepository;
use App\Services\Manager;
use App\Services\ProcurementDataService;
use App\Services\Publishers\DocumentPublisher;
use App\Services\Publishers\EventPublisher;
use App\Services\Publishers\StatusPublisher;
use Illuminate\Http\RedirectResponse;

trait HasProcurementSupport
{
    protected Manager $multichain;

    protected DocumentPublisher $documentPublisher;

    protected StatusPublisher $statusPublisher;

    protected EventPublisher $eventPublisher;

    protected ProcurementDataService $procurementDataService;

    protected \App\Repositories\DocumentRepository $documentRepository;

    /**
     * Initialize procurement support dependencies
     */
    protected function initializeProcurementSupport(
        Manager $multichain,
        DocumentPublisher $documentPublisher,
        StatusPublisher $statusPublisher,
        EventPublisher $eventPublisher,
        ProcurementDataService $procurementDataService,
        \App\Repositories\DocumentRepository $documentRepository
    ): void {
        $this->multiChain = $multichain;
        $this->documentPublisher = $documentPublisher;
        $this->statusPublisher = $statusPublisher;
        $this->eventPublisher = $eventPublisher;
        $this->procurementDataService = $procurementDataService;
        $this->documentRepository = $documentRepository;
    }

    /**
     * Apply common middleware for procurement controllers
     */
    protected function applyProcurementMiddleware(): void
    {
        $this->middleware('auth');
        $this->middleware('role:bac_secretariat');

        $this->middleware(function ($request, $next) {
            $response = $next($request);
            if ($response instanceof RedirectResponse) {
                $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0, private, max-age=0');
                $response->headers->set('Pragma', 'no-cache');
                $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time()).' GMT');

                $response->headers->set('X-Frame-Options', 'DENY');
                $response->headers->set('X-Content-Type-Options', 'nosniff');

                $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s').' GMT');
            }

            return $response;
        });
    }

    /**
     * Helper to find procurement by id from the STATUS stream.
     * Optimized to use ProcurementDataService instead of fetching all 1000 status items.
     *
     * @param  string|int  $id
     */
    protected function findProcurementById($id): ?array
    {
        $statusItems = $this->procurementDataService->fetchStatusItems($id);

        // Return the most recent status item
        $latestStatus = $statusItems->first();

        return $latestStatus ?: null;
    }

    /**
     * Get uploaded document types for a specific procurement and stage from blockchain.
     *
     * @return string[] Array of document type enum values (e.g., ['purchase_request', 'ppmp'])
     */
    protected function getUploadedDocumentTypes(string $pr_number, \App\Enums\StageEnums $stage): array
    {
        try {
            // Fetch all documents for this procurement from blockchain
            $documents = $this->documentRepository->findByProcurement($pr_number);

            // Filter by current stage and extract document types
            $uploadedTypes = [];
            foreach ($documents as $doc) {
                if ($doc->stage === $stage->value) {
                    $uploadedTypes[] = $doc->documentType;
                }
            }

            return array_unique($uploadedTypes);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to fetch uploaded documents', [
                'pr_number' => $pr_number,
                'stage' => $stage->value,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get the procurement data DTO from blockchain.
     */
    protected function getProcurementData(string $prNumber): ?ProcurementData
    {
        return app(ProcurementRepository::class)->findByProcurement($prNumber);
    }

    /**
     * Get the procurement mode for a specific procurement.
     */
    protected function getProcurementMode(string $prNumber): ?ProcurementModeEnums
    {
        $procurement = $this->getProcurementData($prNumber);

        return $procurement?->procurementMode;
    }

    /**
     * Get the next stage for a procurement based on its mode.
     * Uses mode-aware stage navigation per NGPA IRR.
     *
     * @param  string  $prNumber  The procurement reference number
     * @param  StageEnums  $currentStage  The current stage
     * @return StageEnums|null The next stage, or null if at end of workflow
     */
    protected function getNextStageForProcurement(string $prNumber, StageEnums $currentStage): ?StageEnums
    {
        $mode = $this->getProcurementMode($prNumber);

        if (! $mode) {
            // Fall back to default linear navigation if mode not found
            return $currentStage->getNextStage();
        }

        // Get mode-specific next stages
        $nextStages = $currentStage->getNextStagesForMode($mode);

        if (empty($nextStages)) {
            return null;
        }

        // Return the first (primary) next stage
        // Optional stages can be skipped by UI if needed
        return $nextStages[0];
    }

    /**
     * Check if a stage exists in the procurement's mode workflow.
     *
     * @param  string  $prNumber  The procurement reference number
     * @param  StageEnums  $stage  The stage to check
     */
    protected function stageExistsInWorkflow(string $prNumber, StageEnums $stage): bool
    {
        $mode = $this->getProcurementMode($prNumber);

        if (! $mode) {
            // If mode not found, allow all stages
            return true;
        }

        return $stage->existsInModeWorkflow($mode);
    }

    /**
     * Validate that a stage exists in the procurement's mode workflow.
     * Aborts with 403 if the stage is not applicable for the procurement mode.
     *
     * @param  string  $prNumber  The procurement reference number
     * @param  StageEnums  $stage  The stage to validate
     */
    protected function validateStageInWorkflow(string $prNumber, StageEnums $stage): void
    {
        if (! $this->stageExistsInWorkflow($prNumber, $stage)) {
            abort(403, 'This stage is not applicable for this procurement mode');
        }
    }

    /**
     * Check if a stage is optional for the procurement's mode.
     *
     * @param  string  $prNumber  The procurement reference number
     * @param  StageEnums  $stage  The stage to check
     */
    protected function isStageOptional(string $prNumber, StageEnums $stage): bool
    {
        $mode = $this->getProcurementMode($prNumber);

        if (! $mode) {
            return $stage->canSkip();
        }

        $optionalStages = StageEnums::getOptionalStagesForMode($mode);

        return in_array($stage, $optionalStages, true);
    }

    /**
     * Get all stages in the workflow for a procurement based on its mode.
     *
     * @param  string  $prNumber  The procurement reference number
     * @return array<StageEnums>
     */
    protected function getWorkflowStages(string $prNumber): array
    {
        $mode = $this->getProcurementMode($prNumber);

        if (! $mode) {
            // Return all stages as default
            return StageEnums::cases();
        }

        return StageEnums::getStagesForMode($mode);
    }

    /**
     * Get workflow information for frontend display.
     * Provides mode details, workflow stages, and current stage position.
     *
     * @param  string  $prNumber  The procurement reference number
     * @param  StageEnums  $currentStage  The current stage being viewed
     * @return array{
     *     mode: array{value: string, display_name: string, description: string, irr_section: string}|null,
     *     workflow: array{
     *         stages: array<int, array{value: string, display_name: string, is_optional: bool, is_current: bool, is_completed: bool}>,
     *         total_stages: int,
     *         current_index: int,
     *         progress_percentage: int
     *     }
     * }
     */
    protected function getWorkflowInfo(string $prNumber, StageEnums $currentStage): array
    {
        $mode = $this->getProcurementMode($prNumber);
        $procurement = $this->findProcurementById($prNumber);
        $currentProcurementStage = $procurement['stage'] ?? null;

        // If no mode found, return null for mode info
        if (! $mode) {
            return [
                'mode' => null,
                'workflow' => [
                    'stages' => [],
                    'total_stages' => 0,
                    'current_index' => 0,
                    'progress_percentage' => 0,
                ],
            ];
        }

        // Get mode-specific stages
        $workflowStages = StageEnums::getStagesForMode($mode);
        $optionalStages = StageEnums::getOptionalStagesForMode($mode);

        // Determine current stage index based on actual procurement stage
        $currentIndex = 0;
        $completedStages = [];

        // Find the index of the current stage in the workflow
        foreach ($workflowStages as $index => $stage) {
            if ($stage->value === $currentProcurementStage) {
                $currentIndex = $index;
                break;
            }
            // Mark as completed if we haven't reached current stage yet
            $completedStages[] = $stage->value;
        }

        // Build stages array with metadata
        $stagesInfo = [];
        foreach ($workflowStages as $index => $stage) {
            $stagesInfo[] = [
                'value' => $stage->value,
                'display_name' => $stage->getDisplayName(),
                'is_optional' => in_array($stage, $optionalStages, true),
                'is_current' => $stage->value === $currentProcurementStage,
                'is_completed' => $index < $currentIndex,
            ];
        }

        // Calculate progress percentage
        $totalStages = count($workflowStages);
        $progressPercentage = $totalStages > 0
            ? (int) round(($currentIndex / ($totalStages - 1)) * 100)
            : 0;

        return [
            'mode' => [
                'value' => $mode->value,
                'display_name' => $mode->getDisplayName(),
                'description' => $mode->getDescription(),
                'irr_section' => $mode->getIrrSection(),
            ],
            'workflow' => [
                'stages' => $stagesInfo,
                'total_stages' => $totalStages,
                'current_index' => $currentIndex,
                'progress_percentage' => $progressPercentage,
            ],
        ];
    }

    /**
     * Skip an optional stage and transition to the next stage.
     *
     * @param  string  $prNumber  The procurement reference number
     * @param  StageEnums  $stage  The stage to skip
     * @param  string  $reason  Optional reason for skipping
     * @return array{success: bool, message: string, next_stage: StageEnums|null, blockchain: array}
     *
     * @throws \Exception If the stage cannot be skipped
     */
    protected function performSkipStage(string $prNumber, StageEnums $stage, ?string $reason = null): array
    {
        // Verify stage is optional for this procurement's mode
        if (! $this->isStageOptional($prNumber, $stage)) {
            throw new \Exception("Stage {$stage->getDisplayName()} is required and cannot be skipped.");
        }

        // Get procurement data
        $procurement = $this->getProcurementData($prNumber);
        if (! $procurement) {
            throw new \Exception('Procurement not found.');
        }

        $user = auth()->user();
        $userAddress = $user->blockchain_address ?? $user->email;

        // 1. Publish status update to blockchain with skipped metadata
        $statusResult = $this->statusPublisher->publish(
            prNumber: $prNumber,
            procurementTitle: $procurement->title,
            stage: $stage,
            currentStatus: \App\Enums\StatusEnums::STAGE_SKIPPED,
            userAddress: $userAddress,
            previousStatus: null,
            metadata: [
                'skipped_at' => now()->toIso8601String(),
                'skip_reason' => $reason ?? 'Stage marked as optional and skipped by user.',
                'procurement_mode' => $procurement->procurementMode->value,
            ]
        );

        // 2. Publish skip event to blockchain
        $eventResult = $this->eventPublisher->publish(
            prNumber: $prNumber,
            procurementTitle: $procurement->title,
            stage: $stage->value,
            eventType: 'stage_skipped',
            category: 'stage_transition',
            severity: 'info',
            details: "Optional stage {$stage->getDisplayName()} skipped.".($reason ? " Reason: {$reason}" : ''),
            documentCount: 0,
            userAddress: $userAddress,
            metadata: [
                'stage' => $stage->value,
                'skip_reason' => $reason,
                'procurement_mode' => $procurement->procurementMode->value,
            ]
        );

        // 3. Get the mode-aware next stage for automatic transition
        $nextStage = $this->getNextStageForProcurement($prNumber, $stage);

        if ($nextStage) {
            // Publish stage transition to blockchain
            $this->statusPublisher->publishTransition(
                prNumber: $prNumber,
                procurementTitle: $procurement->title,
                fromStage: $stage,
                toStage: $nextStage,
                currentStatus: \App\Enums\StatusEnums::STAGE_SKIPPED,
                userAddress: $userAddress
            );

            $this->eventPublisher->publishStageTransition(
                prNumber: $prNumber,
                procurementTitle: $procurement->title,
                fromStage: $stage->value,
                toStage: $nextStage->value,
                userAddress: $userAddress
            );
        }

        return [
            'success' => true,
            'message' => "{$stage->getDisplayName()} skipped successfully!".($nextStage ? " Proceeding to {$nextStage->getDisplayName()} stage." : ''),
            'next_stage' => $nextStage,
            'blockchain' => [
                'status_txid' => $statusResult['status_txid'] ?? null,
                'event_txid' => $eventResult['event_txid'] ?? null,
                'stage' => $stage->value,
                'next_stage' => $nextStage?->value,
            ],
        ];
    }
}
