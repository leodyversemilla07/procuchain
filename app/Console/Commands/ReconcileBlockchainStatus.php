<?php

namespace App\Console\Commands;

use App\Models\ProcurementDocument;
use App\Services\MultichainService;
use Illuminate\Console\Command;

class ReconcileBlockchainStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blockchain:reconcile
                            {--age=1 : Minimum age in hours for pending records}
                            {--limit=100 : Maximum number of records to process}
                            {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile blockchain status for orphaned records';

    /**
     * Execute the console command.
     */
    public function handle(MultichainService $multichainService): int
    {
        $age = (int) $this->option('age');
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        $this->info('🔍 Scanning for orphaned blockchain records...');
        $this->info("   Age threshold: {$age} hour(s)");
        $this->info("   Limit: {$limit} records");

        if ($dryRun) {
            $this->warn('   DRY RUN MODE - No changes will be made');
        }

        // Find pending documents that are stuck
        $pendingDocuments = ProcurementDocument::where('blockchain_status', 'pending')
            ->where('created_at', '<=', now()->subHours($age))
            ->limit($limit)
            ->get();

        if ($pendingDocuments->isEmpty()) {
            $this->info('✅ No orphaned records found');

            return self::SUCCESS;
        }

        $this->warn("⚠️  Found {$pendingDocuments->count()} orphaned records");

        $confirmed = 0;
        $failed = 0;
        $stillPending = 0;

        foreach ($pendingDocuments as $document) {
            $txid = $document->blockchain_txid;

            if (! $txid) {
                $this->line("  ❌ {$document->id}: No TXID - marking as failed");

                if (! $dryRun) {
                    $document->update([
                        'blockchain_status' => 'failed',
                        'blockchain_status_updated_at' => now(),
                        'blockchain_error' => 'No transaction ID recorded',
                    ]);
                }

                $failed++;

                continue;
            }

            try {
                // Try to verify transaction on blockchain
                $result = $multichainService->getRawTransaction($txid);

                if ($result && isset($result['txid'])) {
                    $confirmations = $result['confirmations'] ?? 0;

                    if ($confirmations > 0) {
                        $this->line("  ✅ {$document->id}: Found on blockchain (TXID: {$txid})");

                        if (! $dryRun) {
                            $document->update([
                                'blockchain_status' => 'confirmed',
                                'blockchain_status_updated_at' => now(),
                                'blockchain_error' => null,
                            ]);
                        }

                        $confirmed++;
                    } else {
                        $this->line("  ⏳ {$document->id}: In mempool (TXID: {$txid})");
                        $stillPending++;
                    }
                } else {
                    $this->line("  ❌ {$document->id}: Not found on blockchain (TXID: {$txid})");

                    if (! $dryRun) {
                        $document->update([
                            'blockchain_status' => 'failed',
                            'blockchain_status_updated_at' => now(),
                            'blockchain_error' => 'Transaction not found on blockchain',
                        ]);
                    }

                    $failed++;
                }

            } catch (\Exception $e) {
                $this->error("  ⚠️  {$document->id}: Error checking status - {$e->getMessage()}");
                $stillPending++;
            }
        }

        $this->newLine();
        $this->info('📊 Reconciliation Summary:');
        $this->line("   ✅ Confirmed: {$confirmed}");
        $this->line("   ❌ Failed: {$failed}");
        $this->line("   ⏳ Still pending: {$stillPending}");

        if ($dryRun) {
            $this->newLine();
            $this->warn('🔄 This was a dry run. Run without --dry-run to apply changes.');
        }

        return self::SUCCESS;
    }
}
