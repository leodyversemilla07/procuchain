<?php

namespace App\Services;

use App\Jobs\UpdateProcurementStatusJob;

class StatusUpdaterService
{
    public function updateStatus(
        string $procurementId,
        string $procurementTitle,
        string $status,
        string $stage,
        string $userAddress,
        string $timestamp
    ): void {
        UpdateProcurementStatusJob::dispatch(
            $procurementId,
            $procurementTitle,
            $status,
            $stage,
            $userAddress,
            $timestamp
        );
    }
}
