<?php

namespace App\Services;

use App\Jobs\PublishProcurementDocumentsJob;
use App\Jobs\HandleStageTransitionJob;

class BlockchainOrchestratorService
{
    // Orchestrate document publishing asynchronously via job
    public function publishDocuments(
        string $procurementId,
        string $procurementTitle,
        string $state,
        string $status,
        array $metadataArray,
        string $userAddress
    ): void {
        PublishProcurementDocumentsJob::dispatch(
            $procurementId,
            $procurementTitle,
            $state,
            $status,
            $metadataArray,
            $userAddress
        );
    }

    public function handleStageTransition(
        string $procurementId,
        string $procurementTitle,
        string $fromStatus,
        string $toStatus,
        string $fromStage,
        string $toStage,
        string $userAddress,
        string $details
    ): void {
        HandleStageTransitionJob::dispatch(
            $procurementId,
            $procurementTitle,
            $fromStatus,
            $toStatus,
            $fromStage,
            $toStage,
            $userAddress,
            $details
        );
    }
}
