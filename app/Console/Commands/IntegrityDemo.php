<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Procurement;
use App\Services\IntegrityVerificationService;
use App\Services\NormalizedTableSyncService;
use Illuminate\Console\Command;

/**
 * Integrity System Demonstration Command
 *
 * Demonstrates the integrity verification system by:
 * 1. Modifying a procurement record in DB
 * 2. Running verification (detects tampering)
 * 3. Restoring FROM blockchain
 *
 * Usage:
 *   php artisan integrity:demo
 *   php artisan integrity:demo --restore
 */
class IntegrityDemo extends Command
{
    protected $signature = 'integrity:demo {--restore : Restore from blockchain}';

    protected $description = 'Demonstrate integrity verification with simulated tampering';

    public function handle(): int
    {
        $this->newLine();
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║      DATA INTEGRITY SYSTEM DEMO                            ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();

        if ($this->option('restore')) {
            return $this->restoreFromBlockchain();
        }

        // Find a real PR from blockchain
        $procurement = Procurement::first();
        if (! $procurement) {
            $this->error('No procurements found. Run: php artisan blockchain:sync-normalized');

            return 1;
        }

        $prNumber = $procurement->pr_number;
        $originalTitle = $procurement->title;

        // Step 1: Show original
        $this->step(1, 'Original Record');
        $this->info("  PR Number: {$prNumber}");
        $this->info("  Title: {$originalTitle}");
        $this->info("  Data Hash: {$procurement->data_hash}");

        // Step 2: Tamper with the record
        $this->step(2, 'Simulating Tampering');
        $hackedTitle = 'HACKED - '.time();
        $procurement->update(['title' => $hackedTitle]);
        $this->info("  Title changed to: {$hackedTitle}");

        // Step 3: Run verification
        $this->step(3, 'Running Integrity Verification');
        $service = app(IntegrityVerificationService::class);
        $result = $service->verifyAndRepair(false, 'demo');

        $breachCount = array_sum($result['violations']);
        $this->info("  Verified: {$result['verified']} records");
        $this->info("  Breaches found: {$breachCount}");

        if ($breachCount > 0) {
            $this->newLine();
            $this->error('  ⚠️  TAMPERING DETECTED!');
            $this->info('  The system detected that the database was modified.');
            $this->info('  Blockchain is the source of truth.');

            // Step 4: Restore
            $this->step(4, 'Restoring FROM Blockchain');
            $syncService = app(NormalizedTableSyncService::class);
            $syncService->syncAll();

            $procurement->refresh();
            $this->info("  Title restored to: {$procurement->title}");
            $this->info("  Data Hash: {$procurement->data_hash}");

            $this->newLine();
            $this->info('  ✅ Record restored from blockchain!');
        } else {
            $this->info('  No breaches detected.');
        }

        return 0;
    }

    private function restoreFromBlockchain(): int
    {
        $this->info('Restoring all data from blockchain...');

        $syncService = app(NormalizedTableSyncService::class);
        $counts = $syncService->syncAll();

        $this->info('Synced:');
        foreach ($counts as $table => $count) {
            $this->info("  {$table}: {$count}");
        }

        return 0;
    }

    private function step(int $num, string $title): void
    {
        $this->newLine();
        $this->info("Step {$num}: {$title}");
        $this->info(str_repeat('-', 50));
    }
}
