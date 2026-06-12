<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BreachTypeEnums;
use App\Models\IntegrityAuditLog;
use App\Services\IntegrityVerificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Audits procurement mirror data integrity against the blockchain.
 */
class BlockchainAudit extends Command
{
    protected $signature = 'blockchain:audit
        {--pr= : Audit only a specific PR number}
        {--repair : Auto-repair detected breaches}
        {--deep-publisher-check : Also verify transaction publishers with getrawtransaction calls}
        {--report= : Generate a report for a specific verification run ID}
        {--source=scheduled : Source label for audit records (scheduled|manual|read_time)}';

    protected $description = 'Audit procurement mirror data integrity against blockchain and optionally auto-repair';

    public function handle(): int
    {
        try {
            // Generate report for a past run
            if ($runId = $this->option('report')) {
                return $this->displayReport($runId);
            }

            $service = app(IntegrityVerificationService::class);
            $autoRepair = (bool) $this->option('repair');
            $deepPublisherCheck = (bool) $this->option('deep-publisher-check');
            $source = $this->option('source');

            if ($autoRepair) {
                $this->warn('[!] Auto-repair is ENABLED - breaches will be restored from blockchain automatically.');
                $this->newLine();
            }

            if ($deepPublisherCheck) {
                $this->warn('Deep publisher check is ENABLED - audit will perform extra getrawtransaction calls.');
                $this->newLine();
            }

            // Single PR audit
            if ($prNumber = $this->option('pr')) {
                $this->info("Auditing PR: {$prNumber}");
                $result = $service->verifyPr($prNumber, $autoRepair, $source, $deepPublisherCheck);
            } else {
                // Full audit
                $this->info('Starting full blockchain integrity audit...');
                $this->comment('  Verifying blockchain mirror records...');

                $result = $service->verifyAndRepair($autoRepair, $source, $deepPublisherCheck);

                $this->newLine();
            }

            $this->displayResults($result, $autoRepair);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Audit failed: {$e->getMessage()}");
            Log::error('BlockchainAudit: fatal error', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }
    }

    /**
     * Display verification results.
     */
    private function displayResults(array $result, bool $autoRepair): void
    {
        $this->info('Audit Summary');
        $this->newLine();

        $this->line("  Run ID:     <fg=cyan>{$result['run_id']}</>");
        $this->line("  Verified:   <fg=green>{$result['verified']}</> records");

        if (empty($result['violations'])) {
            $this->newLine();
            $this->info('  [OK] No breaches detected - mirror integrity verified.');

            return;
        }

        $this->newLine();
        $this->warn('  Breaches detected:');

        foreach ($result['violations'] as $type => $count) {
            $enum = BreachTypeEnums::tryFrom($type);
            $label = $enum ? $enum->getDisplayName() : $type;
            $severity = IntegrityAuditLog::severityForType($type);
            $color = match ($severity) {
                'critical' => 'red',
                'high' => 'yellow',
                'medium' => 'cyan',
                default => 'white',
            };

            $this->line("    - <fg={$color}>{$label}</>: {$count}");
        }

        if ($autoRepair) {
            $this->newLine();
            $this->line("  Restored:   <fg=green>{$result['restored']}</>");
            $this->line("  Failed:     <fg=red>{$result['failed']}</>");
        } else {
            $this->newLine();
            $this->comment('  Run with --repair to auto-fix detected breaches.');
            $this->comment("  Run with --report={$result['run_id']} to view the full violation report.");
        }
    }

    /**
     * Display a report for a past verification run.
     */
    private function displayReport(string $runId): int
    {
        $service = app(IntegrityVerificationService::class);
        $report = $service->generateReport($runId);

        $summary = $report['summary'];

        $this->info("Integrity Violation Report - Run {$runId}");
        $this->newLine();

        $this->line("  Total violations: {$summary['total_violations']}");
        $this->line("  Critical:  <fg=red>{$summary['critical']}</>");
        $this->line("  High:      <fg=yellow>{$summary['high']}</>");
        $this->line("  Medium:    <fg=cyan>{$summary['medium']}</>");
        $this->line("  Low:       <fg=white>{$summary['low']}</>");
        $this->newLine();

        $this->line('  Recovery status:');
        $this->line("    Restored: <fg=green>{$summary['restored']}</>");
        $this->line("    Failed:   <fg=red>{$summary['failed']}</>");
        $this->line("    Pending:  <fg=yellow>{$summary['pending']}</>");

        if (! empty($summary['by_type'])) {
            $this->newLine();
            $this->line('  By type:');

            foreach ($summary['by_type'] as $type => $count) {
                $enum = BreachTypeEnums::tryFrom($type);
                $label = $enum ? $enum->getDisplayName() : $type;
                $this->line("    - {$label}: {$count}");
            }
        }

        return self::SUCCESS;
    }
}
