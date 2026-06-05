<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\StreamEnums;
use App\Models\DocumentView;
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
     * Handle the DocumentView "created" event.
     */
    public function created(DocumentView $documentView): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if ($documentView->txid !== null) {
            return;
        }

        // Use file_key as stream key for grouping
        $key = $documentView->file_key.'-'.($documentView->user_id ?? 'anon').'-'.($documentView->viewed_at?->timestamp ?? time());

        BlockchainSyncService::publish($documentView, StreamEnums::DOCUMENT_ACCESS, $key);
    }
}
