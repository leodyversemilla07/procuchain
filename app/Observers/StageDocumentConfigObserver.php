<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\Stream;
use App\Models\StageDocumentConfig;
use App\Services\BlockchainSyncService;

class StageDocumentConfigObserver
{
    public function __construct(
        private readonly BlockchainSyncService $sync,
    ) {}

    public function created(StageDocumentConfig $config): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if ($config->txid !== null) {
            return;
        }

        $key = $config->stage.'-'.$config->procurement_mode.'-v'.$config->getKey();

        $this->sync->publish($config, Stream::CONFIG_STAGE_DOCS, $key);
    }

    public function updated(StageDocumentConfig $config): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if ($config->wasChanged(['required_documents', 'optional_documents', 'is_active'])) {
            $key = $config->stage.'-'.$config->procurement_mode.'-v'.$config->getKey().'-'.time();

            $this->sync->publish($config, Stream::CONFIG_STAGE_DOCS, $key);
        }
    }
}
