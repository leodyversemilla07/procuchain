<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\Stream;
use App\Models\DocumentViewLog;
use App\Services\BlockchainSyncService;

class DocumentViewObserver
{
    public function __construct(
        private readonly BlockchainSyncService $sync,
    ) {}

    public function created(DocumentViewLog $DocumentViewLog): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if ($DocumentViewLog->txid !== null) {
            return;
        }

        $key = $DocumentViewLog->file_key.'-'.($DocumentViewLog->user_id ?? 'anon').'-'.($DocumentViewLog->viewed_at?->timestamp ?? time());

        $this->sync->publish($DocumentViewLog, Stream::DOCUMENT_ACCESS, $key);
    }
}
