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

    public function created(DocumentViewLog $documentViewLog): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if ($documentViewLog->txid !== null) {
            return;
        }

        $key = $documentViewLog->file_key.'-'.($documentViewLog->user_id ?? 'anon').'-'.($documentViewLog->viewed_at?->timestamp ?? time());

        $this->sync->publish($documentViewLog, Stream::DOCUMENT_ACCESS, $key);
    }
}
