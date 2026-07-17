<?php

namespace App\Services\Publishers;

use App\Enums\ProcurementStatus;
use App\Enums\StageEnums;
use App\Models\Procurement;
use Illuminate\Support\Facades\Log;

/**
 * Service for publishing conference/bulletin decisions to blockchain.
 *
 * Consolidates the duplicate decision handling methods from ProcurementStageController:
 * - publishDecision() - Pre-Procurement Conference
 * - publishPreBidDecision() - Pre-Bid Conference
 * - publishSupplementalBidBulletinDecision() - Supplemental Bid Bulletin
 *
 * All three methods follow the same pattern:
 * 1. If held/needed -> publish held/ongoing status + event, redirect to upload page
 * 2. If skipped -> publish skipped/completed status + event + stage transition, redirect to list
 */
class DecisionPublisher
{
    public function __construct(
        protected StatusPublisher $statusPublisher,
        protected EventPublisher $eventPublisher,
    ) {}

    /**
     * Configuration for each decision type.
     *
     * @var array<string, array{
     *     stage: StageEnums,
     *     held_status: ProcurementStatus,
     *     skipped_status: ProcurementStatus,
     *     next_stage: StageEnums,
     *     held_event_type: string,
     *     skipped_event_type: string,
     *     held_details: string,
     *     skipped_details: string,
     *     decision_field: string,
     *     category: string
     * }>
     */
    private const DECISION_CONFIG = [
        'pre_procurement_conference' => [
            'stage' => StageEnums::PRE_PROCUREMENT_CONFERENCE,
            'held_status' => ProcurementStatus::PRE_PROCUREMENT_CONFERENCE_HELD,
            'skipped_status' => ProcurementStatus::PRE_PROCUREMENT_CONFERENCE_SKIPPED,
            'next_stage' => StageEnums::BIDDING_DOCUMENTS,
            'held_event_type' => 'conference_decision',
            'skipped_event_type' => 'conference_skipped',
            'held_details' => 'Pre-Procurement Conference will be conducted. Awaiting documents upload.',
            'skipped_details' => 'Pre-Procurement Conference was not held. Proceeding to next stage.',
            'decision_field' => 'conference_held',
            'category' => 'Decision',
        ],
        'pre_bid_conference' => [
            'stage' => StageEnums::PRE_BID_CONFERENCE,
            'held_status' => ProcurementStatus::PRE_BID_CONFERENCE_HELD,
            'skipped_status' => ProcurementStatus::PRE_BID_CONFERENCE_SKIPPED,
            'next_stage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN,
            'held_event_type' => 'conference_decision',
            'skipped_event_type' => 'conference_skipped',
            'held_details' => 'Pre-Bid Conference will be conducted. Awaiting documents upload.',
            'skipped_details' => 'Pre-Bid Conference was not held. Proceeding to Supplemental Bid Bulletin stage.',
            'decision_field' => 'conference_held',
            'category' => 'Decision',
        ],
        'supplemental_bid_bulletin' => [
            'stage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN,
            'held_status' => ProcurementStatus::SUPPLEMENTAL_BULLETINS_ONGOING,
            'skipped_status' => ProcurementStatus::SUPPLEMENTAL_BULLETINS_COMPLETED,
            'next_stage' => StageEnums::BID_OPENING,
            'held_event_type' => 'bulletin_decision',
            'skipped_event_type' => 'bulletin_skipped',
            'held_details' => 'Supplemental Bid Bulletin will be issued. Awaiting documents upload.',
            'skipped_details' => 'Supplemental Bid Bulletin was not needed. Proceeding to Bid Opening.',
            'decision_field' => 'supplemental_bid_needed',
            'category' => 'Decision',
        ],
    ];

    /**
     * Publish a conference/bulletin decision to blockchain.
     *
     * @param  string  $decisionType  One of: 'pre_procurement_conference', 'pre_bid_conference', 'supplemental_bid_bulletin'
     * @param  string  $prNumber  The procurement reference number
     * @param  string  $procurementTitle  The procurement title
     * @param  bool  $wasHeld  Whether the conference was held or bulletin was needed
     * @param  string  $userAddress  The user's blockchain address
     * @param  array|null  $procurement  Optional procurement data for transitions
     * @return array{success: bool, held: bool, stage: string, status: string, next_stage?: string, error?: string}
     *
     * @throws \InvalidArgumentException If decision type is not recognized
     */
    public function publishDecision(
        string $decisionType,
        string $prNumber,
        string $procurementTitle,
        bool $wasHeld,
        string $userAddress,
        Procurement|array|null $procurement = null
    ): array {
        $config = self::DECISION_CONFIG[$decisionType] ?? null;

        if (! $config) {
            throw new \InvalidArgumentException("Unknown decision type: {$decisionType}");
        }

        try {
            if ($wasHeld) {
                return $this->publishHeldDecision($prNumber, $procurementTitle, $userAddress, $config);
            }

            return $this->publishSkippedDecision($prNumber, $procurementTitle, $userAddress, $config, $procurement);
        } catch (\Exception $e) {
            Log::error("Failed to publish {$decisionType} decision", [
                'pr_number' => $prNumber,
                'was_held' => $wasHeld,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'held' => $wasHeld,
                'stage' => $config['stage']->value,
                'status' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Publish a "held" decision (conference will be conducted, bulletin will be issued).
     *
     * @param  array<string, mixed>  $config
     * @return array{success: bool, held: bool, stage: string, status: string}
     */
    private function publishHeldDecision(
        string $prNumber,
        string $procurementTitle,
        string $userAddress,
        array $config
    ): array {
        /** @var StageEnums $stage */
        $stage = $config['stage'];
        /** @var ProcurementStatus $heldStatus */
        $heldStatus = $config['held_status'];

        // Publish status update
        $statusResult = $this->statusPublisher->publish(
            $prNumber,
            $procurementTitle,
            $stage,
            $heldStatus,
            $userAddress
        );

        // Publish decision event
        $eventResult = $this->eventPublisher->publish(
            prNumber: $prNumber,
            procurementTitle: $procurementTitle,
            stage: $stage->value,
            eventType: $config['held_event_type'],
            category: $config['category'],
            severity: 'info',
            details: $config['held_details'],
            documentCount: 0,
            userAddress: $userAddress
        );

        return [
            'success' => true,
            'held' => true,
            'stage' => $stage->value,
            'status' => $heldStatus->value,
            'status_txid' => $statusResult['status_txid'] ?? null,
            'event_txid' => $eventResult['event_txid'] ?? null,
        ];
    }

    /**
     * Publish a "skipped" decision (conference not held, bulletin not needed).
     *
     * @param  array<string, mixed>  $config
     * @return array{success: bool, held: bool, stage: string, status: string, next_stage: string}
     */
    private function publishSkippedDecision(
        string $prNumber,
        string $procurementTitle,
        string $userAddress,
        array $config,
        Procurement|array|null $procurement = null
    ): array {
        /** @var StageEnums $stage */
        $stage = $config['stage'];
        /** @var ProcurementStatus $skippedStatus */
        $skippedStatus = $config['skipped_status'];
        /** @var StageEnums $nextStage */
        $nextStage = $config['next_stage'];

        // Publish skipped status
        $statusResult = $this->statusPublisher->publish(
            $prNumber,
            $procurementTitle,
            $stage,
            $skippedStatus,
            $userAddress
        );

        // Publish skipped event
        $eventResult = $this->eventPublisher->publish(
            prNumber: $prNumber,
            procurementTitle: $procurementTitle,
            stage: $stage->value,
            eventType: $config['skipped_event_type'],
            category: $config['category'],
            severity: 'info',
            details: $config['skipped_details'],
            documentCount: 0,
            userAddress: $userAddress
        );

        // Get title from procurement (supports both model and array)
        $title = $procurement instanceof Procurement
            ? $procurement->title
            : ($procurement['title'] ?? $procurementTitle);

        // Publish stage transition
        $transitionResult = $this->statusPublisher->publishTransition(
            $prNumber,
            $title,
            $stage,
            $nextStage,
            $this->getEnteredStatus($nextStage),
            $userAddress
        );

        $this->eventPublisher->publishStageTransition(
            $prNumber,
            $procurementTitle,
            $stage->value,
            $nextStage->value,
            $userAddress
        );

        return [
            'success' => true,
            'held' => false,
            'stage' => $stage->value,
            'status' => $skippedStatus->value,
            'next_stage' => $nextStage->value,
            'status_txid' => $statusResult['status_txid'] ?? null,
            'event_txid' => $eventResult['event_txid'] ?? null,
            'transition_txid' => $transitionResult['status_txid'] ?? null,
        ];
    }

    /**
     * Get the upload route for a decision type.
     *
     * @param  string  $decisionType  One of: 'pre_procurement_conference', 'pre_bid_conference', 'supplemental_bid_bulletin'
     * @param  string  $prNumber  The procurement reference number
     * @return array{route: string, params: array<string, string>}
     */
    public function getUploadRoute(string $decisionType, string $prNumber): array
    {
        $config = self::DECISION_CONFIG[$decisionType] ?? null;

        if (! $config) {
            throw new \InvalidArgumentException("Unknown decision type: {$decisionType}");
        }

        /** @var StageEnums $stage */
        $stage = $config['stage'];

        // Determine the route based on stage phase
        if ($stage->isPreProcurement()) {
            return [
                'route' => 'bac-secretariat.procurement.pre-procurement.show',
                'params' => [
                    'pr_number' => $prNumber,
                    'stage' => $stage->value,
                ],
            ];
        }

        return [
            'route' => 'bac-secretariat.procurement.bidding.show',
            'params' => [
                'pr_number' => $prNumber,
                'stage' => $stage->getSlug(),
            ],
        ];
    }

    /**
     * Get the decision field name for a decision type.
     */
    public function getDecisionField(string $decisionType): string
    {
        $config = self::DECISION_CONFIG[$decisionType] ?? null;

        if (! $config) {
            throw new \InvalidArgumentException("Unknown decision type: {$decisionType}");
        }

        return $config['decision_field'];
    }

    /**
     * Get the stage for a decision type.
     */
    public function getStage(string $decisionType): StageEnums
    {
        $config = self::DECISION_CONFIG[$decisionType] ?? null;

        if (! $config) {
            throw new \InvalidArgumentException("Unknown decision type: {$decisionType}");
        }

        return $config['stage'];
    }

    /**
     * Get the initial status when entering a stage.
     */
    private function getEnteredStatus(StageEnums $stage): ProcurementStatus
    {
        return match ($stage) {
            StageEnums::BIDDING_DOCUMENTS => ProcurementStatus::PRE_PROCUREMENT_CONFERENCE_COMPLETED,
            StageEnums::PRE_BID_CONFERENCE => ProcurementStatus::BIDDING_DOCUMENTS_PUBLISHED,
            StageEnums::SUPPLEMENTAL_BID_BULLETIN => ProcurementStatus::PRE_BID_CONFERENCE_COMPLETED,
            StageEnums::BID_OPENING => ProcurementStatus::SUPPLEMENTAL_BULLETINS_COMPLETED,
            default => ProcurementStatus::PROCUREMENT_SUBMITTED,
        };
    }
}
