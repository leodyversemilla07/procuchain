<?php

namespace App\Services;

use App\Jobs\LogBlockchainEventJob;

class BlockchainEventLoggerService
{
    public function logEvent(
        string $procurementId,
        string $procurementTitle,
        string $stage,
        string $details,
        int $documentCount,
        string $userAddress,
        string $eventType,
        string $category,
        string $severity,
        string $timestamp
    ): void {
        LogBlockchainEventJob::dispatch(
            $procurementId,
            $procurementTitle,
            $stage,
            $details,
            $documentCount,
            $userAddress,
            $eventType,
            $category,
            $severity,
            $timestamp
        );
    }
}
