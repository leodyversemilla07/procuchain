<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\NormalizedTableSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Blockchain Sync Command
 *
 * Syncs procurement blockchain streams to normalized read-model tables.
 */
class BlockchainSync extends Command
{
    protected $signature = 'blockchain:sync
        {--stream= : Sync only a specific stream by name}
        {--all : Sync all streams including user streams}';

    protected $description = 'Sync blockchain procurement streams to normalized read-model tables';

    public function handle(): int
    {
        $this->info('Starting blockchain sync...');
        $this->newLine();

        try {
            if ($stream = $this->option('stream')) {
                $this->error("Single-stream sync is no longer supported by blockchain:sync ({$stream}). Use blockchain:sync-normalized for normalized read models.");

                return self::FAILURE;
            }

            if ($this->option('all')) {
                $this->warn('The --all option is deprecated; syncing normalized procurement streams only.');
            }

            return $this->syncProcurementStreams(app(NormalizedTableSyncService::class));
        } catch (\Exception $e) {
            $this->error("Sync failed: {$e->getMessage()}");
            Log::error('BlockchainSync: fatal error', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }
    }

    private function syncProcurementStreams(NormalizedTableSyncService $syncService): int
    {
        $results = $syncService->syncAll();

        $this->newLine();
        $this->displaySummary($results);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $results
     */
    private function displaySummary(array $results): void
    {
        $this->info('Sync Summary:');
        $this->newLine();

        $total = 0;

        foreach ($results as $stream => $count) {
            $this->line("  - {$stream}: {$count} items");
            $total += $count;
        }

        $this->newLine();
        $this->info("Total: {$total} items synced across ".count($results).' streams');
    }
}
