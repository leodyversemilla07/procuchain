<?php

namespace App\Services\Procurement;

use App\DataTransferObjects\ProcurementData;
use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Models\User;
use App\Repositories\DocumentRepository;
use App\Repositories\ProcurementRepository;
use App\Services\Manager;
use App\Services\ProcurementDataService;
use App\Services\Publishers\DocumentPublisher;
use App\Services\Publishers\EventPublisher;
use App\Services\Publishers\StatusPublisher;
use App\Services\WorkflowDefinitionService;
use Illuminate\Support\Facades\Log;

class ProcurementSupportService
{
    public function __construct(
        protected Manager $multichain,
        protected DocumentPublisher $documentPublisher,
        protected StatusPublisher $statusPublisher,
        protected EventPublisher $eventPublisher,
        protected ProcurementDataService $procurementDataService,
        protected DocumentRepository $documentRepository,
        protected WorkflowDefinitionService $workflowDefinitionService,
        protected StageStatusMapper $stageStatusMapper
    ) {}

    /**
     * Get the initial/default status when entering a new stage.
     * This is MODE-AWARE and considers the procurement mode to return the correct status.
     * This is used when transitioning FROM one stage TO another.
     *
     * @param  string  $prNumber  The procurement reference number (to determine mode)
     * @param  StageEnums  $stage  The stage being entered
     * @return StatusEnums The appropriate status for entering that stage
     */
    public function getInitialStatusForStage(string $prNumber, StageEnums $stage): StatusEnums
    {
        // Get procurement mode for mode-aware status determination
        $mode = $this->getProcurementMode($prNumber);

        return $this->stageStatusMapper->getInitialStatus($stage, $mode);
    }

    /**
     * Helper to find procurement by id from the STATUS stream.
     * Optimized to use ProcurementDataService instead of fetching all 1000 status items.
     *
     * @param  string|int  $id
     */
    public function findProcurementById($id): ?array
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
    public function getUploadedDocumentTypes(string $pr_number, StageEnums $stage): array
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
            Log::error('Failed to fetch uploaded documents', [
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
    public function getProcurementData(string $prNumber): ?ProcurementData
    {
        return app(ProcurementRepository::class)->findByProcurement($prNumber);
    }

    /**
     * Get the procurement mode for a specific procurement.
     */
    public function getProcurementMode(string $prNumber): ?ProcurementModeEnums
    {
        $procurement = $this->getProcurementData($prNumber);

        return $procurement?->procurementMode;
    }

    /**
     * Get the next stage for a procurement based on its mode.
     * Uses mode-aware stage navigation per NGPA IRR.
     * Now uses ProcurementWorkflowService to respect admin configuration.
     *
     * @param  string  $prNumber  The procurement reference number
     * @param  StageEnums  $currentStage  The current stage
     * @return StageEnums|null The next stage, or null if at end of workflow
     */
    public function getNextStageForProcurement(string $prNumber, StageEnums $currentStage): ?StageEnums
    {
        $mode = $this->getProcurementMode($prNumber);

        if (! $mode) {
            // Fall back to default linear navigation if mode not found
            return $currentStage->getNextStage();
        }

        // Use workflow service for database-backed configuration
        $workflowStages = $this->workflowDefinitionService->getStagesForMode($mode);
        $optionalStages = $this->workflowDefinitionService->getOptionalStagesForMode($mode);
        $currentIndex = array_search($currentStage, $workflowStages, true);

        if ($currentIndex === false || $currentIndex >= count($workflowStages) - 1) {
            return null;
        }

        $nextStages = [];
        $nextIndex = $currentIndex + 1;

        if (isset($workflowStages[$nextIndex])) {
            $nextStages[] = $workflowStages[$nextIndex];

            if (in_array($workflowStages[$nextIndex], $optionalStages, true) && isset($workflowStages[$nextIndex + 1])) {
                $nextStages[] = $workflowStages[$nextIndex + 1];
            }
        }

        if (empty($nextStages)) {
            return null;
        }

        // Return the first (primary) next stage
        // Optional stages can be skipped by UI if needed
        return $nextStages[0];
    }

    /**
     * Check if a stage exists in the procurement's mode workflow.
     * Now uses ProcurementWorkflowService to respect admin configuration.
     *
     * @param  string  $prNumber  The procurement reference number
     * @param  StageEnums  $stage  The stage to check
     */
    public function stageExistsInWorkflow(string $prNumber, StageEnums $stage): bool
    {
        $mode = $this->getProcurementMode($prNumber);

        if (! $mode) {
            // If mode not found, allow all stages
            return true;
        }

        return $this->workflowDefinitionService->isStageInWorkflow($stage, $mode);
    }

    /**
     * Validate that a stage exists in the procurement's mode workflow.
     * Aborts with 403 if the stage is not applicable for the procurement mode.
     *
     * @param  string  $prNumber  The procurement reference number
     * @param  StageEnums  $stage  The stage to validate
     */
    public function validateStageInWorkflow(string $prNumber, StageEnums $stage): void
    {
        if (! $this->stageExistsInWorkflow($prNumber, $stage)) {
            abort(403, 'This stage is not applicable for this procurement mode');
        }
    }

    /**
     * Check if a stage is optional for the procurement's mode.
     * Now uses ProcurementWorkflowService to respect admin configuration.
     *
     * @param  string  $prNumber  The procurement reference number
     * @param  StageEnums  $stage  The stage to check
     */
    public function isStageOptional(string $prNumber, StageEnums $stage): bool
    {
        $mode = $this->getProcurementMode($prNumber);

        if (! $mode) {
            return $stage->canSkip();
        }

        return $this->workflowDefinitionService->isStageOptional($stage, $mode);
    }

    /**
     * Get all stages in the workflow for a procurement based on its mode.
     * Now uses ProcurementWorkflowService to respect admin configuration.
     *
     * @param  string  $prNumber  The procurement reference number
     * @return array<StageEnums>
     */
    public function getWorkflowStages(string $prNumber): array
    {
        $mode = $this->getProcurementMode($prNumber);

        if (! $mode) {
            // Return all stages as default
            return StageEnums::cases();
        }

        return $this->workflowDefinitionService->getStagesForMode($mode);
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
    public function getWorkflowInfo(string $prNumber, StageEnums $currentStage): array
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

        // Get mode-specific stages from workflow service (database-backed)
        $workflowStages = $this->workflowDefinitionService->getStagesForMode($mode);
        $optionalStages = $this->workflowDefinitionService->getOptionalStagesForMode($mode);

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
            $isCompleted = $index < $currentIndex;
            $isCurrent = $stage->value === $currentProcurementStage;

            // Generate URL for the stage based on its phase
            $url = '#';
            if ($stage === StageEnums::PROCUREMENT_INITIATION) {
                $url = route('bac-secretariat.procurement.initiation.show', ['pr_number' => $prNumber]);
            } else {
                $phase = $stage->getPhase();
                $routeName = match ($phase) {
                    'pre_procurement' => 'bac-secretariat.procurement.pre-procurement.show',
                    'procurement' => 'bac-secretariat.procurement.bidding.show',
                    'post_procurement' => 'bac-secretariat.procurement.post-procurement.show',
                    default => null,
                };

                if ($routeName) {
                    $url = route($routeName, ['pr_number' => $prNumber, 'stage' => $stage->value]);
                }
            }

            $stagesInfo[] = [
                'value' => $stage->value,
                'display_name' => $stage->getDisplayName(),
                'is_optional' => in_array($stage, $optionalStages, true),
                'is_current' => $isCurrent,
                'is_completed' => $isCompleted,
                'url' => $url,
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
    public function performSkipStage(string $prNumber, StageEnums $stage, ?string $reason = null, ?User $authUser = null): array
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

        $user = $authUser;
        $userAddress = $user?->blockchain_address ?? $user?->email ?? 'unknown';

        // 1. Publish status update to blockchain with skipped metadata
        $statusResult = $this->statusPublisher->publish(
            prNumber: $prNumber,
            procurementTitle: $procurement->title,
            stage: $stage,
            currentStatus: StatusEnums::STAGE_SKIPPED,
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
            // Get the appropriate status for entering the next stage
            $nextStageStatus = $this->getInitialStatusForStage($prNumber, $nextStage);

            // Publish stage transition to blockchain
            $this->statusPublisher->publishTransition(
                prNumber: $prNumber,
                procurementTitle: $procurement->title,
                fromStage: $stage,
                toStage: $nextStage,
                currentStatus: $nextStageStatus,
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

    /**
     * Handle automatic stage transition when accessing a post-procurement stage.
     *
     * For example: accessing Notice of Award while still at BAC Resolution + resolution_recorded
     *
     * Uses mode-aware workflow to determine valid transitions.
     */
    public function handleAutoStageTransition(string $prNumber, array $procurement, StageEnums $targetStage): void
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
    public function publishStageTransition(
        string $prNumber,
        array $procurement,
        StageEnums $fromStage,
        StageEnums $toStage,
        StatusEnums $currentStatus,
        ?User $authUser = null
    ): void {
        try {
            $user = $authUser;
            $userAddress = $user?->blockchain_address ?? 'unknown';

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
    public function getCompletionStatusForStage(StageEnums $stage): StatusEnums
    {
        return $this->stageStatusMapper->getCompletionStatus($stage);
    }

    /**
     * Get the ongoing status for a stage (used during document uploads).
     */
    public function getOngoingStatusForStage(StageEnums $stage): StatusEnums
    {
        return $this->stageStatusMapper->getOngoingStatus($stage);
    }
}
