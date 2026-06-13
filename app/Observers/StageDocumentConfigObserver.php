<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\Stream;
use App\Models\StageDocumentConfig;
use App\Services\BlockchainSyncService;

/**
 * Stage Document Config Observer — Auto-publish to blockchain on update.
 *
 * Document requirement changes are written to both MySQL and blockchain.
 * This ensures config tampering can be detected and recovered.
 */
class StageDocumentConfigObserver
{
    /**
     * Handle the StageDocumentConfig "created" event.
     */
    public function created(StageDocumentConfig $config): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if ($config->txid !== null) {
            return;
        }

        $key = $config->stage.'-'.$config->procurement_mode.'-v'.$config->getKey();

        app(BlockchainSyncService::class)->publish($config, Stream::CONFIG_STAGE_DOCS, $key);
    }

    /**
     * Handle the StageDocumentConfig "updated" event.
     */
    public function updated(StageDocumentConfig $config): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if ($config->wasChanged(['required_documents', 'optional_documents', 'is_active'])) {
            $key = $config->stage.'-'.$config->procurement_mode.'-v'.$config->getKey().'-'.time();

            app(BlockchainSyncService::class)->publish($config, Stream::CONFIG_STAGE_DOCS, $key);
        }
    }
}
