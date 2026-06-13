<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\NormalizedTableSyncService;
use Illuminate\Console\Command;

/**
 * Sync Blockchain to Normalized Tables
 *
 * Reads data DIRECTLY FROM blockchain and populates normalized tables.
 * No procurement_records table needed.
 */
class SyncAllTables extends Command
{
    protected $signature = 'blockchain:sync-normalized';

    protected $description = 'Sync blockchain data directly to normalized tables';

    public function handle(NormalizedTableSyncService $syncService): int
    {
        $this->info('Syncing FROM blockchain to normalized tables...');
        $this->newLine();

        $startTime = microtime(true);

        $counts = $syncService->syncAll();

        $elapsed = round(microtime(true) - $startTime, 2);

        $this->info("Sync completed in {$elapsed}s");
        $this->newLine();

        $this->table(['Table', 'Synced'], [
            ['Procurements', $counts['procurements']],
            ['Stages', $counts['stages']],
            ['Documents', $counts['documents']],
            ['Events', $counts['events']],
            ['Corrections', $counts['corrections']],
            ['Files', $counts['Files']],
        ]);

        $total = array_sum($counts);
        $this->info("Total: {$total} records synced from blockchain");

        return Command::SUCCESS;
    }
}
