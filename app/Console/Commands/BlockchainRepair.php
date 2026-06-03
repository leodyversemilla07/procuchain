<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\StreamEnums;
use App\Models\ProcurementMirror;
use App\Services\BlockchainMirrorSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Blockchain Repair Command
 *
 * Repairs procurement mirror data from the blockchain for a specific PR.
 * Re-syncs chain data to the mirror and marks any unresolved breaches as repaired.
 */
class BlockchainRepair extends Command
{
    protected $signature = 'blockchain:repair
        {pr_number : The PR number to repair}
        {--stream= : Repair only a specific stream}';

    protected $description = 'Repair procurement mirror data from blockchain for a specific PR';

    public function handle(): int
    {
        $prNumber = $this->argument('pr_number');
        $stream = $this->option('stream');

        $this->info("Repairing mirror data for PR: {$prNumber}");

        if ($stream) {
            $this->info("  Stream: {$stream}");
        } else {
            $this->info('  Streams: all procurement streams');
        }

        $this->newLine();

        try {
            // Step 1: Count unresolved breaches before repair
            $unresolvedBefore = ProcurementMirror::forKey($prNumber)
                ->unresolved()
                ->count();

            if ($unresolvedBefore > 0) {
                $this->warn("  Found {$unresolvedBefore} unresolved breach(es) before repair.");
            }

            // Step 2: Repair from chain
            $syncService = app(BlockchainMirrorSyncService::class);
            $repairedCount = $syncService->repairFromChain($prNumber, $stream);

            // Step 3: Mark any remaining unresolved breaches as repaired
            $remainingBreaches = ProcurementMirror::forKey($prNumber)
                ->unresolved()
                ->get();

            $markedCount = 0;

            foreach ($remainingBreaches as $breach) {
                $breach->markAsRepaired();
                $markedCount++;
            }

            $this->newLine();
            $this->info("✓ Repaired {$repairedCount} items from chain for PR {$prNumber}");

            if ($markedCount > 0) {
                $this->info("✓ Marked {$markedCount} remaining breach(es) as repaired");
            }

            // Step 4: Verify no unresolved breaches remain
            $unresolvedAfter = ProcurementMirror::forKey($prNumber)
                ->unresolved()
                ->count();

            if ($unresolvedAfter > 0) {
                $this->warn("⚠ {$unresolvedAfter} breach(es) still unresolved — may require manual review.");
            } else {
                $this->info('✓ All breaches resolved — mirror integrity restored.');
            }

            Log::info('BlockchainRepair: completed', [
                'pr_number' => $prNumber,
                'stream' => $stream,
                'repaired_count' => $repairedCount,
                'marked_count' => $markedCount,
                'unresolved_before' => $unresolvedBefore,
                'unresolved_after' => $unresolvedAfter,
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Repair failed: {$e->getMessage()}");
            Log::error('BlockchainRepair: fatal error', [
                'pr_number' => $prNumber,
                'stream' => $stream,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }
}
