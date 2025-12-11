<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Repositories\ProcurementRepository;
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
    public function __construct(
        private readonly ProcurementRepository $procurementRepository
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
        string $userRole = 'bac_secretariat'
    ): array {
        try {
            $actions = [];

            // Get procurement data for mode-aware actions with timeout protection
            $procurement = $this->procurementRepository->findByProcurement($prNumber);
            $mode = $procurement?->procurementMode;

            $stageEnum = StageEnums::tryFrom($stage);
            $statusEnum = StatusEnums::tryFrom($status);

            if (! $stageEnum || ! $statusEnum) {
                return $actions;
            }

            // Only BAC Secretariat can perform workflow actions
            if ($userRole === 'bac_secretariat') {
                $actions = array_merge($actions, $this->getWorkflowActions($prNumber, $stageEnum, $statusEnum, $mode));
            }

            // Add skip action if current stage is optional AND there's no dialog action already
            // Dialog actions (like pre-procurement conference decision) include skip option in the dialog itself
            // Also don't show skip if the stage is already in progress (work has started)
            $hasDialogAction = collect($actions)->contains(fn ($action) => ($action['type'] ?? '') === 'dialog');
            $stageInProgress = $this->isStageInProgress($statusEnum);
            if ($userRole === 'bac_secretariat' && $mode && $this->isStageOptional($stageEnum, $mode) && ! $hasDialogAction && ! $stageInProgress) {
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
     * Get workflow actions based on stage and status.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getWorkflowActions(
        string $prNumber,
        StageEnums $stage,
        StatusEnums $status,
        ?ProcurementModeEnums $mode
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
    private function getActionRegistry(?ProcurementModeEnums $mode): array
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
        return [
            // ===== Universal Actions (All Modes) =====
            // Procurement Initiation - universal first step
            [
                'condition' => [
                    'stage' => StageEnums::PROCUREMENT_INITIATION,
                    'status' => StatusEnums::PROCUREMENT_INITIATED,
                ],
                'type' => 'upload',
                'label' => 'Upload Procurement Initiation Documents',
                'icon' => 'upload',
                'variant' => 'blue',
                'href_template' => '/bac-secretariat/procurement-initiation/{pr_number}',
            ],
            // Pre-Procurement Conference Decision (after Procurement Initiation is complete)
            // For Competitive Bidding mode - shows dialog to decide whether to hold conference
            [
                'condition' => [
                    'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE,
                    'status' => StatusEnums::PROCUREMENT_SUBMITTED,
                ],
                'type' => 'dialog',
                'label' => 'Record Pre-Procurement Conference Decision',
                'icon' => 'edit',
                'variant' => 'indigo',
                'action' => 'pre-procurement',
            ],

            // Pre-Procurement Conference - Upload documents after conference is held
            [
                'condition' => [
                    'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE,
                    'status' => StatusEnums::PRE_PROCUREMENT_CONFERENCE_HELD,
                ],
                'type' => 'upload',
                'label' => 'Upload Pre-Procurement Conference Documents',
                'icon' => 'upload',
                'variant' => 'green',
                'href_template' => '/bac-secretariat/pre-procurement/{pr_number}/pre_procurement_conference',
            ],

            // Bidding Documents
            [
                'condition' => [
                    'stage' => StageEnums::BIDDING_DOCUMENTS,
                    'status' => [
                        StatusEnums::PRE_PROCUREMENT_CONFERENCE_SKIPPED,
                        StatusEnums::PRE_PROCUREMENT_CONFERENCE_COMPLETED,
                    ],
                ],
                'type' => 'upload',
                'label' => 'Upload Bidding Documents',
                'icon' => 'upload',
                'variant' => 'amber',
                'href_template' => '/bac-secretariat/pre-procurement/{pr_number}/bidding_documents',
            ],

            // Pre-Bid Conference
            [
                'condition' => [
                    'stage' => StageEnums::PRE_BID_CONFERENCE,
                    'status' => StatusEnums::BIDDING_DOCUMENTS_PUBLISHED,
                ],
                'type' => 'dialog',
                'label' => 'Record Pre-Bid Conference Decision',
                'icon' => 'edit',
                'variant' => 'indigo',
                'action' => 'pre-bid',
            ],
            [
                'condition' => [
                    'stage' => StageEnums::PRE_BID_CONFERENCE,
                    'status' => StatusEnums::PRE_BID_CONFERENCE_HELD,
                ],
                'type' => 'upload',
                'label' => 'Upload Pre-Bid Conference Documents',
                'icon' => 'upload',
                'variant' => 'indigo',
                'href_template' => '/bac-secretariat/procurement/{pr_number}/pre_bid_conference',
            ],

            // Supplemental Bid Bulletin
            [
                'condition' => [
                    'stage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN,
                    'status' => [
                        StatusEnums::PRE_BID_CONFERENCE_COMPLETED,
                        StatusEnums::PRE_BID_CONFERENCE_SKIPPED,
                    ],
                ],
                'type' => 'dialog',
                'label' => 'Record Supplemental Bid Bulletin Decision',
                'icon' => 'edit',
                'variant' => 'indigo',
                'action' => 'supplemental-bid-bulletin',
            ],
            [
                'condition' => [
                    'stage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN,
                    'status' => StatusEnums::SUPPLEMENTAL_BULLETINS_ONGOING,
                ],
                'type' => 'upload',
                'label' => 'Upload Supplemental Bid Bulletin Documents',
                'icon' => 'upload',
                'variant' => 'blue',
                'href_template' => '/bac-secretariat/procurement/{pr_number}/supplemental_bid_bulletin',
            ],

            // Issue Another Bulletin - Per NGPA IRR, multiple bulletins can be issued
            [
                'condition' => [
                    'stage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN,
                    'status' => StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED,
                ],
                'type' => 'repeat',
                'label' => 'Issue Another Bulletin',
                'icon' => 'refresh',
                'variant' => 'outline',
                'href_template' => '/bac-secretariat/procurement/{pr_number}/supplemental_bid_bulletin/repeat',
                'is_repeatable' => true,
            ],

            // Bid Opening
            // Option to issue another bulletin before proceeding with Bid Opening
            [
                'condition' => [
                    'stage' => StageEnums::BID_OPENING,
                    'status' => StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED,
                ],
                'type' => 'repeat',
                'label' => 'Issue Another Bulletin (Before Bid Opening)',
                'icon' => 'refresh',
                'variant' => 'outline',
                'href_template' => '/bac-secretariat/procurement/{pr_number}/supplemental_bid_bulletin/repeat',
                'is_repeatable' => true,
            ],
            [
                'condition' => [
                    'stage' => StageEnums::BID_OPENING,
                    'status' => StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED,
                ],
                'type' => 'upload',
                'label' => 'Upload Bid Opening Documents',
                'icon' => 'upload',
                'variant' => 'blue',
                'href_template' => '/bac-secretariat/procurement/{pr_number}/bid_opening',
            ],

            // Bid Evaluation
            [
                'condition' => [
                    'stage' => StageEnums::BID_EVALUATION,
                    'status' => StatusEnums::BIDS_OPENED,
                ],
                'type' => 'upload',
                'label' => 'Upload Bid Evaluation Documents',
                'icon' => 'chart',
                'variant' => 'indigo',
                'href_template' => '/bac-secretariat/procurement/{pr_number}/bid_evaluation',
            ],

            // Post-Qualification
            [
                'condition' => [
                    'stage' => StageEnums::POST_QUALIFICATION,
                    'status' => StatusEnums::BIDS_EVALUATED,
                ],
                'type' => 'upload',
                'label' => 'Upload Post-Qualification Report',
                'icon' => 'upload',
                'variant' => 'green',
                'href_template' => '/bac-secretariat/procurement/{pr_number}/post_qualification',
            ],

            // BAC Resolution (supports both Competitive Bidding and SVP modes)
            [
                'condition' => [
                    'stage' => StageEnums::BAC_RESOLUTION,
                    'status' => [
                        StatusEnums::POST_QUALIFICATION_VERIFIED,  // Competitive Bidding
                        StatusEnums::ABSTRACT_PREPARED,            // SVP and alternative modes
                    ],
                ],
                'type' => 'upload',
                'label' => 'Upload BAC Resolution Documents',
                'icon' => 'upload',
                'variant' => 'purple',
                'href_template' => '/bac-secretariat/procurement/{pr_number}/bac_resolution',
            ],

            // Notice of Award
            [
                'condition' => [
                    'stage' => StageEnums::NOTICE_OF_AWARD,
                    'status' => StatusEnums::RESOLUTION_RECORDED,
                ],
                'type' => 'upload',
                'label' => 'Upload Notice of Award',
                'icon' => 'upload',
                'variant' => 'amber',
                'href_template' => '/bac-secretariat/post-procurement/{pr_number}/notice_of_award',
            ],

            // Performance Bond, Contract, and PO
            [
                'condition' => [
                    'stage' => StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
                    'status' => StatusEnums::AWARDED,
                ],
                'type' => 'upload',
                'label' => 'Upload Performance Bond, Contract, and PO',
                'icon' => 'upload',
                'variant' => 'cyan',
                'href_template' => '/bac-secretariat/post-procurement/{pr_number}/performance_bond_contract_and_po',
            ],

            // Notice to Proceed
            [
                'condition' => [
                    'stage' => StageEnums::NOTICE_TO_PROCEED,
                    'status' => StatusEnums::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED,
                ],
                'type' => 'upload',
                'label' => 'Upload Notice to Proceed',
                'icon' => 'upload',
                'variant' => 'green',
                'href_template' => '/bac-secretariat/post-procurement/{pr_number}/notice_to_proceed',
            ],

            // Monitoring
            [
                'condition' => [
                    'stage' => StageEnums::MONITORING,
                    'status' => StatusEnums::NTP_RECORDED,
                ],
                'type' => 'upload',
                'label' => 'Upload Monitoring Documents',
                'icon' => 'upload',
                'variant' => 'teal',
                'href_template' => '/bac-secretariat/post-procurement/{pr_number}/monitoring',
            ],

            // Completion
            [
                'condition' => [
                    'stage' => StageEnums::COMPLETION,
                    'status' => StatusEnums::MONITORING_COMPLETED,
                ],
                'type' => 'upload',
                'label' => 'Upload Certificate of Completion',
                'icon' => 'upload',
                'variant' => 'emerald',
                'href_template' => '/bac-secretariat/post-procurement/{pr_number}/completion',
            ],

            // ===== SVP/Alternative Mode Actions =====
            // Request for Quotation
            [
                'condition' => [
                    'stage' => StageEnums::REQUEST_FOR_QUOTATION,
                    'status' => StatusEnums::PROCUREMENT_SUBMITTED,
                ],
                'type' => 'upload',
                'label' => 'Upload Request for Quotation',
                'icon' => 'upload',
                'variant' => 'blue',
                'href_template' => '/bac-secretariat/pre-procurement/{pr_number}/request_for_quotation',
            ],

            // Abstract of Quotations
            [
                'condition' => [
                    'stage' => StageEnums::ABSTRACT_OF_QUOTATIONS,
                    'status' => StatusEnums::QUOTATIONS_RECEIVED,
                ],
                'type' => 'upload',
                'label' => 'Upload Abstract of Quotations',
                'icon' => 'upload',
                'variant' => 'indigo',
                'href_template' => '/bac-secretariat/procurement/{pr_number}/abstract_of_quotations',
            ],
        ];
    }

    /**
     * Check if stage/status matches a condition.
     *
     * @param  array<string, mixed>  $condition
     */
    private function matchesCondition(array $condition, StageEnums $stage, StatusEnums $status): bool
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
    private function isStageOptional(StageEnums $stage, ProcurementModeEnums $mode): bool
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

        // View Corrections - only for BAC Secretariat
        if ($userRole === 'bac_secretariat') {
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
            'bac_secretariat' => "/bac-secretariat/procurements-list/{$prNumber}",
            'bac_chairman' => "/bac-chairman/procurements-list/{$prNumber}",
            'hope' => "/hope/procurements-list/{$prNumber}",
            'admin' => "/admin/procurements-list/{$prNumber}",
            default => "/admin/procurements-list/{$prNumber}",
        };
    }

    /**
     * Check if the stage is already in progress (work has started).
     * When a stage is in progress, it should not be skipped anymore.
     */
    private function isStageInProgress(StatusEnums $status): bool
    {
        // Statuses that indicate work has started on a stage
        $inProgressStatuses = [
            // Pre-Procurement Conference - conference was held
            StatusEnums::PRE_PROCUREMENT_CONFERENCE_HELD,
            StatusEnums::PRE_PROCUREMENT_CONFERENCE_COMPLETED,

            // Pre-Bid Conference - conference was held
            StatusEnums::PRE_BID_CONFERENCE_HELD,
            StatusEnums::PRE_BID_CONFERENCE_COMPLETED,

            // Supplemental Bulletins - bulletins are ongoing or completed
            StatusEnums::SUPPLEMENTAL_BULLETINS_ONGOING,
            StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED,

            // Any completed status indicates the stage has been worked on
            StatusEnums::BIDDING_DOCUMENTS_PUBLISHED,
            StatusEnums::BIDDING_DOCUMENTS_SUBMITTED,
            StatusEnums::BIDS_OPENED,
            StatusEnums::BIDS_EVALUATED,
            StatusEnums::POST_QUALIFICATION_VERIFIED,
            StatusEnums::RESOLUTION_RECORDED,
            StatusEnums::AWARDED,
            StatusEnums::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED,
            StatusEnums::NTP_RECORDED,
            StatusEnums::MONITORING_COMPLETED,
            StatusEnums::COMPLETION_DOCUMENTS_UPLOADED,
            StatusEnums::COMPLETED,
        ];

        return in_array($status, $inProgressStatuses, true);
    }
}
