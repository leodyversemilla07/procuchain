<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\StreamEnums;
use App\Services\BlockchainMirrorSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Blockchain Sync Command
 *
 * Syncs blockchain stream data to the procurement_mirror database table.
 * Supports syncing individual streams, all procurement streams, or all
 * streams including user registration streams.
 */
class BlockchainSync extends Command
{
    protected $signature = 'blockchain:sync
        {--stream= : Sync only a specific stream by name}
        {--all : Sync all streams including user streams}';

    protected $description = 'Sync blockchain data to the procurement mirror database table';

    public function handle(): int
    {
        $this->info('Starting blockchain sync...');
        $this->newLine();

        try {
            $syncService = app(BlockchainMirrorSyncService::class);

            if ($stream = $this->option('stream')) {
                return $this->syncSingleStream($syncService, $stream);
            }

            if ($this->option('all')) {
                return $this->syncAllStreams($syncService);
            }

            return $this->syncProcurementStreams($syncService);
        } catch (\Exception $e) {
            $this->error("Sync failed: {$e->getMessage()}");
            Log::error('BlockchainSync: fatal error', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }
    }

    private function syncSingleStream(BlockchainMirrorSyncService $syncService, string $stream): int
    {
        $this->info("Syncing stream: {$stream}");

        $count = $syncService->downstream($stream, function (int $current, int $total): void {
            if ($total > 0 && $current % max(1, intdiv($total, 10)) === 0) {
                $this->info("  Progress: {$current}/{$total}");
            }
        });

        $this->newLine();
        $this->info("✓ Synced {$count} items from {$stream}");

        return self::SUCCESS;
    }

    private function syncProcurementStreams(BlockchainMirrorSyncService $syncService): int
    {
        $results = $syncService->syncAll(function (string $stream, int $count, int $completed): void {
            $this->info("  ✓ {$stream}: {$count} items");
        });

        $this->newLine();
        $this->displaySummary($results);

        return self::SUCCESS;
    }

    private function syncAllStreams(BlockchainMirrorSyncService $syncService): int
    {
        $results = $syncService->syncAll(function (string $stream, int $count, int $completed): void {
            $this->info("  ✓ {$stream}: {$count} items");
        });

        // Also sync user.registrations stream
        $this->info('Syncing user.registrations stream...');
        $userCount = $syncService->downstream(
            StreamEnums::USER_REGISTRATIONS->value,
            fn (int $current, int $total) => true,
        );
        $results[StreamEnums::USER_REGISTRATIONS->value] = $userCount;
        $this->info("  ✓ user.registrations: {$userCount} items");

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
            $this->line("  • {$stream}: {$count} items");
            $total += $count;
        }

        $this->newLine();
        $this->info("Total: {$total} items synced across ".count($results).' streams');
    }
}
