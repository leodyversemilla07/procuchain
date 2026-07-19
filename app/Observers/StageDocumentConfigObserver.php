<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\Stream;
use App\Models\StageDocumentConfig;
use App\Observers\Concerns\HandlesBlockchainSync;
use App\Services\BlockchainSyncService;

class StageDocumentConfigObserver
{
    use HandlesBlockchainSync;

    public function __construct(
        private readonly BlockchainSyncService $sync,
    ) {}

    public function created(StageDocumentConfig $config): void
    {
        if (! $this->shouldSyncToBlockchain($config)) {
            return;
        }

        $key = $config->stage.'-'.$config->procurement_mode.'-v'.$config->getKey();

        $this->sync->publish($config, Stream::CONFIG_STAGE_DOCS, $key);
    }

    public function updated(StageDocumentConfig $config): void
    {
        if (! $this->shouldSyncToBlockchain()) {
            return;
        }

        if ($config->wasChanged(['required_documents', 'optional_documents', 'is_active'])) {
            $key = $config->stage.'-'.$config->procurement_mode.'-v'.$config->getKey().'-'.time();

            $this->sync->publish($config, Stream::CONFIG_STAGE_DOCS, $key);
        }
    }
}
