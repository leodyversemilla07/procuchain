<?php

declare(strict_types=1);

namespace App\Jobs\Handlers;

use App\Enums\ProcurementStatus;
use App\Enums\StageEnums;
use App\Repositories\ProcurementRepository;
use App\Services\Procurement\StageStatusMappingService;
use App\Services\Publishers\EventPublisher;
use App\Services\Publishers\StatusPublisher;
use Exception;

class StageTransitionHandler
{
    public function __construct(
        private readonly StatusPublisher $statusPublisher,
        private readonly EventPublisher $eventPublisher,
        private readonly ProcurementRepository $procurementRepository,
        private readonly ?StageStatusMappingService $StageStatusMappingService = null,
    ) {}

    public function executeSkip(array $data): array
    {
        $prNumber = $data['pr_number'];
        $stage = StageEnums::from($data['stage']);
        $reason = $data['reason'] ?? 'Stage marked as optional and skipped by user.';
        $userAddress = $data['user_address'];
        $procurement = $this->procurementRepository->findByProcurement($prNumber);

        if (! $procurement) {
            throw new Exception("Procurement not found: {$prNumber}");
        }

        $statusResult = $this->statusPublisher->publish(
            prNumber: $prNumber,
            procurementTitle: $procurement->title,
            stage: $stage,
            currentStatus: ProcurementStatus::STAGE_SKIPPED,
            userAddress: $userAddress,
            previousStatus: null,
            metadata: [
                'skipped_at' => now()->toIso8601String(),
                'skip_reason' => $reason,
                'procurement_mode' => $procurement->procurementMode->value,
            ],
        );

        $eventResult = $this->eventPublisher->publish(
            prNumber: $prNumber,
            procurementTitle: $procurement->title,
            stage: $stage->value,
            eventType: 'stage_skipped',
            category: 'stage_transition',
            severity: 'info',
            details: "Stage {$stage->getDisplayName()} skipped. Reason: {$reason}",
            documentCount: 0,
            userAddress: $userAddress,
            metadata: [
                'stage' => $stage->value,
                'skip_reason' => $reason,
                'procurement_mode' => $procurement->procurementMode->value,
            ],
        );

        return [
            'status_txid' => $statusResult['status_txid'] ?? null,
            'event_txid' => $eventResult['event_txid'] ?? null,
            'stage' => $stage->value,
        ];
    }

    public function executeRepeat(array $data): array
    {
        $prNumber = $data['pr_number'];
        $stage = StageEnums::from($data['stage']);
        $reason = $data['reason'] ?? 'Additional bulletin required';
        $userAddress = $data['user_address'];
        $procurement = $this->procurementRepository->findByProcurement($prNumber);

        if (! $procurement) {
            throw new Exception("Procurement not found: {$prNumber}");
        }

        $ongoingStatus = ($this->StageStatusMappingService ?? new StageStatusMappingService)->getOngoingStatus($stage);

        $eventResult = $this->eventPublisher->publish(
            prNumber: $prNumber,
            procurementTitle: $procurement->title,
            stage: $stage->value,
            eventType: 'stage_repeated',
            category: 'stage_transition',
            severity: 'info',
            details: "Another {$stage->getDisplayName()} is being issued per NGPA IRR provisions.",
            documentCount: 0,
            userAddress: $userAddress,
            metadata: [
                'stage' => $stage->value,
                'repeat_reason' => $reason,
                'procurement_mode' => $procurement->procurementMode->value,
            ],
        );

        $statusResult = $this->statusPublisher->publish(
            prNumber: $prNumber,
            procurementTitle: $procurement->title,
            stage: $stage,
            currentStatus: $ongoingStatus,
            userAddress: $userAddress,
            previousStatus: null,
            metadata: [
                'action' => 'repeat_stage',
                'repeated_at' => now()->toIso8601String(),
                'procurement_mode' => $procurement->procurementMode->value,
            ],
        );

        return [
            'status_txid' => $statusResult['status_txid'] ?? null,
            'event_txid' => $eventResult['event_txid'] ?? null,
            'stage' => $stage->value,
        ];
    }
}
