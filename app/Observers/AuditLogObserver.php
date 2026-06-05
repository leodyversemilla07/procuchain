<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\StreamEnums;
use App\Models\AuditLog;
use App\Services\BlockchainSyncService;

/**
 * Audit Log Observer — Auto-publish to blockchain on create.
 *
 * Every audit log entry is simultaneously written to MySQL and blockchain.
 * This ensures the audit trail survives MySQL destruction.
 */
class AuditLogObserver
{
    /**
     * Handle the AuditLog "created" event.
     */
    public function created(AuditLog $auditLog): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        // Skip if already synced (e.g., during recovery)
        if ($auditLog->txid !== null) {
            return;
        }

        BlockchainSyncService::publish($auditLog, StreamEnums::AUDIT_TRAIL);
    }
}
