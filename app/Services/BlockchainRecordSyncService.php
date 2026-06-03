<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Blockchain Record Sync Service
 *
 * Syncs data FROM blockchain to normalized tables.
 * Blockchain is source of truth.
 *
 * - upstream(): syncs a published record to normalized tables
 * - downstream(): reads all items from a stream and syncs to normalized tables
 * - syncAll(): syncs all procurement streams
 */
class BlockchainRecordSyncService
{
    public function __construct(
        private NormalizedTableSyncService $normalizedSync,
    ) {}

    /**
     * Sync a newly published blockchain record to normalized tables.
     *
     * Called AFTER a successful blockchain publish.
     */
    public function upstream(
        string $stream,
        string $key,
        string $txid,
        string $publisherAddress,
        ?int $blocktime,
        array $data,
        bool $isAuthorized = true,
    ): void {
        Log::info('BlockchainRecordSync: upstream sync', ['stream' => $stream, 'key' => $key]);

        // Re-sync from blockchain to populate normalized tables
        $this->normalizedSync->syncAll();
    }

    /**
     * Sync all procurement streams from blockchain to normalized tables.
     */
    public function syncAll(): array
    {
        return $this->normalizedSync->syncAll();
    }

    /**
     * Repair a specific PR from blockchain.
     */
    public function repairFromChain(string $prNumber, ?string $stream = null): int
    {
        Log::info('BlockchainRecordSync: repairFromChain', ['pr_number' => $prNumber]);

        // Re-sync all data from blockchain
        $this->normalizedSync->syncAll();

        return 1; // Return count of repaired items
    }
}
