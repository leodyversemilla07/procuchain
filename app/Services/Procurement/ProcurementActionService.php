<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\ProcurementMode;
use App\Enums\ProcurementStatus;
use App\Enums\StageEnums;
use App\Enums\UserRole;
use App\Models\Procurement;
use Illuminate\Support\Facades\Log;

/**
 * Service for generating procurement action configurations.
 *
 * Centralizes the logic for determining which actions are available
 * for a procurement based on its current stage, status, and mode.
 * This moves business logic from frontend to backend for better
 * security and maintainability.
 */
final class ProcurementActionService
{
    /**
     * Cache for procurement modes to avoid repeated blockchain calls
     *
     * @var array<string, ProcurementMode|null>
     */
    private array $modeCache = [];

    public function __construct(
    ) {}

    /**
     * Get available actions for a procurement.
     *
     * @return array<int, array{
     *     type: string,
     *     label: string,
     *     icon: string,
     *     href?: string,
     *     action?: string,
     *     variant: string,
     *     is_optional?: bool
     * }>
     */
    public function getAvailableActions(
        string $prNumber,
        string $stage,
        string $status,
        string $userRole = UserRole::BAC_SECRETARIAT->value,
        ?ProcurementMode $mode = null
    ): array {
        try {
            $actions = [];

            // Use provided mode if available, otherwise fetch with caching
            if ($mode === null) {
                $mode = $this->getProcurementMode($prNumber);
            }

            $stageEnum = StageEnums::tryFrom($stage);
            $statusEnum = ProcurementStatus::tryFrom($status);

            if (! $stageEnum || ! $statusEnum) {
                return $actions;
            }

            // Only BAC Secretariat can perform workflow actions
            if ($userRole === UserRole::BAC_SECRETARIAT->value) {
                $actions = array_merge($actions, $this->getWorkflowActions($prNumber, $stageEnum, $statusEnum, $mode));
            }

            $hasDialogAction = collect($actions)->contains(fn ($action) => ($action['type'] ?? '') === 'dialog');
            $stageInProgress = $this->isStageInProgress($statusEnum);
            if ($userRole === UserRole::BAC_SECRETARIAT->value && $mode && $this->isStageOptional($stageEnum, $mode) && ! $hasDialogAction && ! $stageInProgress) {
                $actions[] = $this->buildSkipAction($prNumber, $stageEnum);
            }

            return $actions;
        } catch (\Exception $e) {
            // Log blockchain connection errors but don't crash the page
            Log::warning('Failed to get available actions for procurement', [
                'pr_number' => $prNumber,
                'stage' => $stage,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);

            return []; // Return empty actions array on error
        }
    }

    /**
     * Get procurement mode with caching to avoid repeated blockchain calls.
     * This is critical for performance when displaying action buttons for multiple procurements.
     */
    private function getProcurementMode(string $prNumber): ?ProcurementMode
    {
        // Check cache first
        if (array_key_exists($prNumber, $this->modeCache)) {
            return $this->modeCache[$prNumber];
        }

        // Fetch from database
        try {
            $procurement = Procurement::where('pr_number', $prNumber)->first();
            $mode = $procurement ? ProcurementMode::tryFrom($procurement->procurement_mode) : null;
        } catch (\Exception $e) {
            Log::debug('Failed to fetch procurement mode, using null', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);
            $mode = null;
        }

        // Store in cache
        $this->modeCache[$prNumber] = $mode;

        return $mode;
    }

    /**
     * Get workflow actions based on stage and status.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getWorkflowActions(
        string $prNumber,
        StageEnums $stage,
        ProcurementStatus $status,
        ?ProcurementMode $mode
    ): array {
        $actions = [];

        // Get mode-specific action registry
        $actionRegistry = $this->getActionRegistry($mode);

        foreach ($actionRegistry as $definition) {
            if ($this->matchesCondition($definition['condition'], $stage, $status)) {
                $action = [
                    'type' => $definition['type'],
                    'label' => $definition['label'],
                    'icon' => $definition['icon'],
                    'variant' => $definition['variant'],
                ];

                if (isset($definition['href_template'])) {
                    $action['href'] = $this->buildHref($definition['href_template'], $prNumber, $stage);
                }

                if (isset($definition['action'])) {
                    $action['action'] = $definition['action'];
                }

                $actions[] = $action;
            }
        }

        return $actions;
    }

    /**
     * Get the action registry - centralized action definitions.
     * Returns mode-specific actions based on the procurement mode.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getActionRegistry(?ProcurementMode $mode): array
    {
        // Get stages for the current mode (if known)
        $modeStages = $mode ? StageEnums::getStagesForMode($mode) : StageEnums::cases();

        // Build the full registry
        $allActions = $this->getAllActionDefinitions();

        // Filter actions to only include those for stages in the current mode
        return array_filter($allActions, function ($action) use ($modeStages) {
            $actionStage = $action['condition']['stage'] ?? null;
            if (! $actionStage) {
                return true; // Include actions without stage condition
            }

            return in_array($actionStage, $modeStages, true);
        });
    }

    /**
     * Get all action definitions - the complete registry.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getAllActionDefinitions(): array
    {
        return config('procurement-actions');
    }

    /**
     * Check if stage/status matches a condition.
     *
     * @param  array<string, mixed>  $condition
     */
    private function matchesCondition(array $condition, StageEnums $stage, ProcurementStatus $status): bool
    {
        // Check stage match
        if (isset($condition['stage'])) {
            if ($condition['stage'] !== $stage) {
                return false;
            }
        }

        // Check status match (can be single or array)
        if (isset($condition['status'])) {
            $allowedStatuses = is_array($condition['status'])
                ? $condition['status']
                : [$condition['status']];

            if (! in_array($status, $allowedStatuses, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Build href from template.
     */
    private function buildHref(string $template, string $prNumber, StageEnums $stage): string
    {
        return str_replace(
            ['{pr_number}', '{stage}'],
            [$prNumber, $stage->value],
            $template
        );
    }

    /**
     * Check if a stage is optional for the given mode.
     */
    private function isStageOptional(StageEnums $stage, ProcurementMode $mode): bool
    {
        $optionalStages = StageEnums::getOptionalStagesForMode($mode);

        return in_array($stage, $optionalStages, true);
    }

    /**
     * Build skip action for optional stages.
     *
     * @return array<string, mixed>
     */
    private function buildSkipAction(string $prNumber, StageEnums $stage): array
    {
        // Determine the correct route based on stage phase
        $phase = $stage->getPhase();
        $skipRoute = match ($phase) {
            'pre_procurement' => "/bac-secretariat/pre-procurement/{$prNumber}/{$stage->value}/skip",
            'procurement' => "/bac-secretariat/procurement/{$prNumber}/{$stage->value}/skip",
            'post_procurement' => "/bac-secretariat/post-procurement/{$prNumber}/{$stage->value}/skip",
            default => null,
        };

        return [
            'type' => 'skip',
            'label' => "Skip {$stage->getDisplayName()} (Optional)",
            'icon' => 'skip',
            'variant' => 'outline',
            'href' => $skipRoute,
            'is_optional' => true,
        ];
    }

    /**
     * Get static actions available for all procurements.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getStaticActions(string $prNumber, string $userRole): array
    {
        $actions = [];

        // View Details - available to all authenticated users
        $actions[] = [
            'type' => 'view',
            'label' => 'View Details',
            'icon' => 'eye',
            'variant' => 'default',
            'href' => $this->getViewDetailsHref($prNumber, $userRole),
        ];

        // Verification Report - available to all authenticated users
        $actions[] = [
            'type' => 'verify',
            'label' => 'Verification Report',
            'icon' => 'shield-check',
            'variant' => 'success',
            'href' => "/procurement/{$prNumber}/verification",
        ];

        if ($userRole === UserRole::BAC_SECRETARIAT->value) {
            $actions[] = [
                'type' => 'corrections',
                'label' => 'View Corrections',
                'icon' => 'alert-circle',
                'variant' => 'warning',
                'href' => "/procurements/{$prNumber}/corrections",
            ];
        }

        return $actions;
    }

    /**
     * Get the view details href based on user role.
     */
    private function getViewDetailsHref(string $prNumber, string $userRole): string
    {
        return match ($userRole) {
            UserRole::BAC_SECRETARIAT->value => "/bac-secretariat/procurements-list/{$prNumber}",
            UserRole::BAC_CHAIRMAN->value => "/bac-chairman/procurements-list/{$prNumber}",
            UserRole::HOPE->value => "/hope/procurements-list/{$prNumber}",
            UserRole::ADMIN->value => "/admin/procurements-list/{$prNumber}",
            default => "/admin/procurements-list/{$prNumber}",
        };
    }

    /**
     * Check if the stage is already in progress (work has started).
     * When a stage is in progress, it should not be skipped anymore.
     */
    private function isStageInProgress(ProcurementStatus $status): bool
    {
        // Statuses that indicate work has started on a stage
        $inProgressStatuses = [
            // Pre-Procurement Conference - conference was held
            ProcurementStatus::PRE_PROCUREMENT_CONFERENCE_HELD,
            ProcurementStatus::PRE_PROCUREMENT_CONFERENCE_COMPLETED,

            // Pre-Bid Conference - conference was held
            ProcurementStatus::PRE_BID_CONFERENCE_HELD,
            ProcurementStatus::PRE_BID_CONFERENCE_COMPLETED,

            // Supplemental Bulletins - bulletins are ongoing or completed
            ProcurementStatus::SUPPLEMENTAL_BULLETINS_ONGOING,
            ProcurementStatus::SUPPLEMENTAL_BULLETINS_COMPLETED,

            // Any completed status indicates the stage has been worked on
            ProcurementStatus::BIDDING_DOCUMENTS_PUBLISHED,
            ProcurementStatus::BIDDING_DOCUMENTS_SUBMITTED,
            ProcurementStatus::BIDS_OPENED,
            ProcurementStatus::BIDS_EVALUATED,
            ProcurementStatus::POST_QUALIFICATION_VERIFIED,
            ProcurementStatus::RESOLUTION_RECORDED,
            ProcurementStatus::AWARDED,
            ProcurementStatus::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED,
            ProcurementStatus::NTP_RECORDED,
            ProcurementStatus::MONITORING_COMPLETED,
            ProcurementStatus::COMPLETION_DOCUMENTS_UPLOADED,
            ProcurementStatus::COMPLETED,
        ];

        return in_array($status, $inProgressStatuses, true);
    }
}
