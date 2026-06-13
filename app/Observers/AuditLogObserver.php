<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\Stream;
use App\Models\AuditLog;
use App\Services\BlockchainSyncService;

class AuditLogObserver
{
    public function __construct(
        private readonly BlockchainSyncService $sync,
    ) {}

    public function created(AuditLog $auditLog): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if ($auditLog->txid !== null) {
            return;
        }

        $this->sync->publish($auditLog, Stream::AUDIT_TRAIL);
    }
}
