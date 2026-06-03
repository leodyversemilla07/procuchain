<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BlockchainRecordSyncService;
use Illuminate\Console\Command;

/**
 * Repair a specific PR from blockchain.
 *
 * Syncs blockchain data to normalized tables.
 */
class BlockchainRepair extends Command
{
    protected $signature = 'blockchain:repair {prNumber}';

    protected $description = 'Repair a specific PR from blockchain to normalized tables';

    public function handle(BlockchainRecordSyncService $syncService): int
    {
        $prNumber = $this->argument('prNumber');

        $this->info("Repairing PR: {$prNumber}");

        try {
            $count = $syncService->repairFromChain($prNumber);

            $this->info("Repaired {$count} item(s) from blockchain.");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Repair failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
