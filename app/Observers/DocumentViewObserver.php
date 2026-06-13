<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\Stream;
use App\Models\DocumentViewLog;
use App\Services\BlockchainSyncService;

/**
 * Document View Observer — Auto-publish to blockchain on create.
 *
 * Every document view record is simultaneously written to MySQL and blockchain.
 * This ensures the document access audit trail survives MySQL destruction.
 */
class DocumentViewObserver
{
    /**
     * Handle the DocumentViewLog "created" event.
     */
    public function created(DocumentViewLog $DocumentViewLog): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if ($DocumentViewLog->txid !== null) {
            return;
        }

        // Use file_key as stream key for grouping
        $key = $DocumentViewLog->file_key.'-'.($DocumentViewLog->user_id ?? 'anon').'-'.($DocumentViewLog->viewed_at?->timestamp ?? time());

        app(BlockchainSyncService::class)->publish($DocumentViewLog, Stream::DOCUMENT_ACCESS, $key);
    }
}
