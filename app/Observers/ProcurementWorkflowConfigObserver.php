<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\Stream;
use App\Models\ProcurementWorkflowConfig;
use App\Observers\Concerns\HandlesBlockchainSync;
use App\Services\BlockchainSyncService;

class ProcurementWorkflowConfigObserver
{
    use HandlesBlockchainSync;

    public function __construct(
        private readonly BlockchainSyncService $sync,
    ) {}

    public function created(ProcurementWorkflowConfig $config): void
    {
        if (! $this->shouldSyncToBlockchain($config)) {
            return;
        }

        $key = $config->procurement_mode.'-v'.$config->getKey();

        $this->sync->publish($config, Stream::CONFIG_WORKFLOWS, $key);
    }

    public function updated(ProcurementWorkflowConfig $config): void
    {
        if (! $this->shouldSyncToBlockchain()) {
            return;
        }

        if ($config->wasChanged(['stages', 'optional_stages', 'is_active'])) {
            $key = $config->procurement_mode.'-v'.$config->getKey().'-'.time();

            $this->sync->publish($config, Stream::CONFIG_WORKFLOWS, $key);
        }
    }
}
