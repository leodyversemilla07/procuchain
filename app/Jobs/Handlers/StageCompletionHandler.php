<?php

declare(strict_types=1);

namespace App\Jobs\Handlers;

use App\Enums\ProcurementStatus;
use App\Enums\StageEnums;
use App\Enums\UserRole;
use App\Services\NotificationService;
use App\Services\Publishers\EventPublisher;
use App\Services\Publishers\StatusPublisher;
use Exception;
use Illuminate\Support\Facades\Log;

class StageCompletionHandler
{
    public function __construct(
        private readonly StatusPublisher $statusPublisher,
        private readonly EventPublisher $eventPublisher,
    ) {}

    public function execute(array $data): array
    {
        if (($data['operation_variant'] ?? '') === 'initiation_complete') {
            return $this->handleInitiationComplete($data);
        }

        $stage = StageEnums::from($data['current_stage']);
        $completionStatus = ProcurementStatus::from($data['completion_status']);
        $previousStatus = isset($data['previous_status'])
            ? ProcurementStatus::tryFrom($data['previous_status'])
            : null;

        $statusResult = $this->statusPublisher->publish(
            prNumber: $data['pr_number'],
            procurementTitle: $data['procurement_title'],
            stage: $stage,
            currentStatus: $completionStatus,
            userAddress: $data['user_address'],
            previousStatus: $previousStatus,
            metadata: [
                'documents_uploaded' => $data['document_count'],
                'marked_complete_at' => now()->toIso8601String(),
                'procurement_mode' => $data['procurement_mode'],
            ],
        );

        $eventResult = $this->eventPublisher->publish(
            prNumber: $data['pr_number'],
            procurementTitle: $data['procurement_title'],
            stage: $stage->value,
            eventType: 'stage_completed',
            category: 'stage_transition',
            severity: 'info',
            details: "Stage {$stage->getDisplayName()} marked as complete with all required documents uploaded.",
            documentCount: $data['document_count'],
            userAddress: $data['user_address'],
            metadata: [
                'stage' => $stage->value,
                'completion_status' => $completionStatus->value,
                'procurement_mode' => $data['procurement_mode'],
            ],
        );

        $nextStageName = null;
        $nextStageUrl = null;
        $transitionTxid = null;

        if (isset($data['next_stage'])) {
            $nextStage = StageEnums::from($data['next_stage']);
            $nextStageStatus = ProcurementStatus::from($data['next_stage_status']);
            $nextStageName = $nextStage->getDisplayName();
            $nextStageUrl = $this->buildNextStageUrl($data['pr_number'], $nextStage);

            $transitionResult = $this->statusPublisher->publishTransition(
                prNumber: $data['pr_number'],
                procurementTitle: $data['procurement_title'],
                fromStage: $stage,
                toStage: $nextStage,
                currentStatus: $nextStageStatus,
                userAddress: $data['user_address'],
                previousStatus: $completionStatus,
            );

            $transitionTxid = $transitionResult['status_txid'] ?? null;

            $this->eventPublisher->publishStageTransition(
                prNumber: $data['pr_number'],
                procurementTitle: $data['procurement_title'],
                fromStage: $stage->value,
                toStage: $nextStage->value,
                userAddress: $data['user_address'],
            );
        }

        if (! empty($data['is_pre_procurement'])) {
            $this->sendStageNotification($data, $stage, $completionStatus, $nextStageName);
        }

        return [
            'success' => true,
            'status_txid' => $statusResult['status_txid'] ?? null,
            'event_txid' => $eventResult['event_txid'] ?? null,
            'next_stage' => $data['next_stage'] ?? null,
            'next_stage_name' => $nextStageName,
            'next_stage_url' => $nextStageUrl,
            'transition_txid' => $transitionTxid,
        ];
    }

    private function handleInitiationComplete(array $data): array
    {
        $nextStage = StageEnums::from($data['next_stage']);
        $nextStageStatus = ProcurementStatus::from($data['next_stage_status']);
        $currentStageEnum = StageEnums::from($data['current_stage']);

        $statusResult = $this->statusPublisher->publish(
            prNumber: $data['pr_number'],
            procurementTitle: $data['procurement_title'],
            stage: $nextStage,
            currentStatus: $nextStageStatus,
            userAddress: $data['user_address'],
            previousStatus: null,
            metadata: [
                'documents_uploaded' => $data['document_count'],
                'marked_complete_at' => now()->toIso8601String(),
                'previous_stage' => $data['current_stage'],
                'stage_transition' => true,
            ],
        );

        $eventResult = $this->eventPublisher->publish(
            prNumber: $data['pr_number'],
            procurementTitle: $data['procurement_title'],
            stage: $nextStage->value,
            eventType: 'stage_completed',
            category: 'stage_transition',
            severity: 'info',
            details: "Stage {$currentStageEnum->getDisplayName()} completed. Transitioned to {$nextStage->getDisplayName()} with status {$nextStageStatus->getDisplayName()}.",
            documentCount: $data['document_count'],
            userAddress: $data['user_address'],
            metadata: [
                'previous_stage' => $data['current_stage'],
                'new_stage' => $nextStage->value,
                'completion_status' => $nextStageStatus->value,
            ],
        );

        return [
            'success' => true,
            'status_txid' => $statusResult['status_txid'] ?? null,
            'event_txid' => $eventResult['event_txid'] ?? null,
            'next_stage' => $nextStage->value,
            'next_stage_name' => $nextStage->getDisplayName(),
            'next_stage_url' => $this->buildNextStageUrl($data['pr_number'], $nextStage),
        ];
    }

    /**
     * Build the URL for the next stage based on its phase.
     */
    private function buildNextStageUrl(string $prNumber, StageEnums $nextStage): string
    {
        $phase = $nextStage->getPhase();

        return match ($phase) {
            'pre_procurement' => route('bac-secretariat.procurement.pre-procurement.show', [
                'pr_number' => $prNumber,
                'stage' => $nextStage->value,
            ]),
            'procurement' => route('bac-secretariat.procurement.bidding.show', [
                'pr_number' => $prNumber,
                'stage' => $nextStage->value,
            ]),
            'post_procurement' => route('bac-secretariat.procurement.post-procurement.show', [
                'pr_number' => $prNumber,
                'stage' => $nextStage->value,
            ]),
            default => '#',
        };
    }

    private function sendStageNotification(array $data, StageEnums $stage, ProcurementStatus $completionStatus, ?string $nextStageName): void
    {
        try {
            app(NotificationService::class)->notifyStageUpdate(
                pr_number: $data['pr_number'],
                procurementTitle: $data['procurement_title'],
                stageIdentifier: $stage->getDisplayName(),
                currentStatus: $completionStatus->getDisplayName(),
                timestamp: now()->toDateTimeString(),
                actionType: 'marked complete',
                documentCount: $data['document_count'],
                stageTransition: $nextStageName !== null,
                nextStage: $nextStageName ?? '',
                rolesToNotify: [UserRole::BAC_CHAIRMAN->value, UserRole::HOPE->value, UserRole::ADMIN->value],
            );
        } catch (Exception $e) {
            Log::warning('BlockchainWriteJob: Notification failed (non-critical)', [
                'pr_number' => $data['pr_number'],
                'error' => $e->getMessage(),
            ]);
        }
    }
}
