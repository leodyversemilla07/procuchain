<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\Stream;
use App\Models\AuditLog;
use App\Observers\Concerns\HandlesBlockchainSync;
use App\Services\BlockchainSyncService;

class AuditLogObserver
{
    use HandlesBlockchainSync;

    public function __construct(
        private readonly BlockchainSyncService $sync,
    ) {}

    public function created(AuditLog $auditLog): void
    {
        if (! $this->shouldSyncToBlockchain($auditLog)) {
            return;
        }

        $this->sync->publish($auditLog, Stream::AUDIT_TRAIL);
    }
}
