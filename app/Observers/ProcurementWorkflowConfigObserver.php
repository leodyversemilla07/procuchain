<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\StreamEnums;
use App\Models\ProcurementWorkflowConfig;
use App\Services\BlockchainSyncService;

/**
 * Workflow Config Observer — Auto-publish to blockchain on update.
 *
 * Workflow configuration changes are written to both MySQL and blockchain.
 * This ensures config tampering can be detected and recovered.
 */
class ProcurementWorkflowConfigObserver
{
    /**
     * Handle the ProcurementWorkflowConfig "created" event.
     */
    public function created(ProcurementWorkflowConfig $config): void
    {
        if ($config->txid !== null) {
            return;
        }

        $key = $config->procurement_mode.'-v'.$config->getKey();

        BlockchainSyncService::publish($config, StreamEnums::CONFIG_WORKFLOWS, $key);
    }

    /**
     * Handle the ProcurementWorkflowConfig "updated" event.
     */
    public function updated(ProcurementWorkflowConfig $config): void
    {
        // Only publish if meaningful fields changed
        if ($config->wasChanged(['stages', 'optional_stages', 'is_active'])) {
            $key = $config->procurement_mode.'-v'.$config->getKey().'-'.time();

            BlockchainSyncService::publish($config, StreamEnums::CONFIG_WORKFLOWS, $key);
        }
    }
}
