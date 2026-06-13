<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ProcurementStatus;
use App\Enums\StageEnums;
use App\Models\User;
use App\Repositories\ProcurementRepository;
use App\Repositories\StatusRepository;
use App\Services\Publishers\StatusPublisher;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Corrects incorrect status records in blockchain for specified procurements.
 */
class CorrectProcurementStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'status:correct
                            {pr_number? : Specific PR number to correct}
                            {--dry-run : Preview changes without applying them}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Correct incorrect status records in blockchain by publishing corrected statuses';

    /**
     * Procurements to correct with their correct statuses
     */
    private array $correctionsNeeded = [
        'PR-2025-0011-1496' => [
            'stage' => 'procurement_initiation',
            'incorrect_status' => 'procurement_submitted',
            'correct_status' => 'procurement_initiated',
            'reason' => 'Status mismatch: Stage is PROCUREMENT_INITIATION but status was PROCUREMENT_SUBMITTED. Correct status should be PROCUREMENT_INITIATED.',
        ],
        'PR-2025-0001-0043' => [
            'stage' => 'abstract_of_quotations',
            'incorrect_status' => 'quotations_received',
            'correct_status' => 'abstract_prepared',
            'reason' => 'Status mismatch: Stage is ABSTRACT_OF_QUOTATIONS but status was QUOTATIONS_RECEIVED. Correct status should be ABSTRACT_PREPARED.',
        ],
        'PR-2025-0001-0001' => [
            'stage' => 'procurement_initiation',
            'incorrect_status' => 'procurement_submitted',
            'correct_status' => 'procurement_initiated',
            'reason' => 'Status mismatch: Stage is PROCUREMENT_INITIATION but status was PROCUREMENT_SUBMITTED. Correct status should be PROCUREMENT_INITIATED.',
        ],
    ];

    public function __construct(
        private StatusRepository $statusRepository,
        private ProcurementRepository $procurementRepository,
        private StatusPublisher $statusPublisher
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Procurement Status Correction Tool');
        $this->newLine();

        try {
            // Get PR number from argument or process all
            $targetPrNumber = $this->argument('pr_number');
            $isDryRun = $this->option('dry-run');
            $isForce = $this->option('force');

            // Filter corrections based on target
            $correctionsToApply = $targetPrNumber
                ? [$targetPrNumber => $this->correctionsNeeded[$targetPrNumber] ?? null]
                : $this->correctionsNeeded;

            // Remove null entries
            $correctionsToApply = array_filter($correctionsToApply);

            if (empty($correctionsToApply)) {
                $this->error('[FAIL] No corrections found for the specified PR number.');

                return self::FAILURE;
            }

            // Display corrections to be applied
            $this->displayCorrections($correctionsToApply, $isDryRun);

            // Confirm if not dry-run and not forced
            if (! $isDryRun && ! $isForce) {
                if (! $this->confirm('Do you want to proceed with these corrections?', false)) {
                    $this->warn('[WARN]  Operation cancelled.');

                    return self::SUCCESS;
                }
            }

            // Apply corrections
            if ($isDryRun) {
                $this->info('[OK] Dry run complete. No changes were made to the blockchain.');

                return self::SUCCESS;
            }

            return $this->applyCorrections($correctionsToApply);
        } catch (Exception $e) {
            $this->error('[FAIL] Error: '.$e->getMessage());
            Log::error('Status correction command failed', [
                'error' => $e->getMessage(),
                'trace' => sprintf('%s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Display corrections in a table
     */
    private function displayCorrections(array $corrections, bool $isDryRun): void
    {
        $this->info($isDryRun ? 'Preview Mode - No changes will be made' : 'Corrections to Apply:');
        $this->newLine();

        $tableData = [];
        foreach ($corrections as $prNumber => $correction) {
            $tableData[] = [
                $prNumber,
                strtoupper(str_replace('_', ' ', $correction['stage'])),
                strtoupper(str_replace('_', ' ', $correction['incorrect_status'])),
                strtoupper(str_replace('_', ' ', $correction['correct_status'])),
            ];
        }

        $this->table(
            ['PR Number', 'Stage', 'Current Status (Wrong)', 'Correct Status'],
            $tableData
        );

        $this->newLine();
    }

    /**
     * Apply corrections to blockchain
     */
    private function applyCorrections(array $corrections): int
    {
        $successCount = 0;
        $failureCount = 0;

        $this->info('Publishing corrected statuses to blockchain...');
        $this->newLine();

        foreach ($corrections as $prNumber => $correction) {
            try {
                $this->line("Processing {$prNumber}...");

                // Get procurement data
                $procurement = $this->procurementRepository->findByProcurement($prNumber);

                if (! $procurement) {
                    $this->error("  [FAIL] Procurement not found: {$prNumber}");
                    $failureCount++;

                    continue;
                }

                // Verify current status is indeed incorrect
                $currentStatus = $this->statusRepository->getLatest($prNumber);

                if (! $currentStatus) {
                    $this->error("  [FAIL] No status found for: {$prNumber}");
                    $failureCount++;

                    continue;
                }

                if ($currentStatus->currentStatus !== $correction['incorrect_status']) {
                    $this->warn("  [WARN]  Current status ({$currentStatus->currentStatus}) doesn't match expected incorrect status ({$correction['incorrect_status']}). Skipping.");

                    continue;
                }

                // Publish corrected status
                $stage = StageEnums::from($correction['stage']);
                $correctStatus = ProcurementStatus::from($correction['correct_status']);
                $incorrectStatus = ProcurementStatus::from($correction['incorrect_status']);

                // Get the original user's blockchain address from the procurement's userId
                $user = User::find($procurement->userId);
                if (! $user || ! $user->blockchain_address) {
                    $this->error("  [FAIL] User not found or missing blockchain address for: {$prNumber}");
                    $failureCount++;

                    continue;
                }

                $userAddress = $user->blockchain_address;

                $result = $this->statusPublisher->publish(
                    prNumber: $prNumber,
                    procurementTitle: $procurement->title,
                    stage: $stage,
                    currentStatus: $correctStatus,
                    userAddress: $userAddress,
                    previousStatus: $incorrectStatus,
                    metadata: [
                        'correction_type' => 'status_mismatch_fix',
                        'correction_reason' => $correction['reason'],
                        'corrected_at' => now()->toIso8601String(),
                        'corrected_by' => 'system_admin',
                        'original_incorrect_status' => $correction['incorrect_status'],
                    ]
                );

                if ($result['success']) {
                    $this->info("  [OK] Corrected: {$prNumber}");
                    $this->line("     TXID: {$result['status_txid']}");
                    $this->line("     Stage: {$correction['stage']}");
                    $this->line("     Status: {$correction['incorrect_status']} -> {$correction['correct_status']}");
                    $successCount++;
                } else {
                    $this->error("  [FAIL] Failed to publish correction for: {$prNumber}");
                    $failureCount++;
                }

                $this->newLine();
            } catch (Exception $e) {
                $this->error("  [FAIL] Error processing {$prNumber}: ".$e->getMessage());
                Log::error('Failed to correct status', [
                    'pr_number' => $prNumber,
                    'error' => $e->getMessage(),
                ]);
                $failureCount++;

                $this->newLine();
            }
        }

        // Summary
        $this->newLine();
        $this->info('Summary:');
        $this->line("  [OK] Successful: {$successCount}");

        if ($failureCount > 0) {
            $this->line("  [FAIL] Failed: {$failureCount}");
        }

        if ($successCount === count($corrections)) {
            $this->newLine();
            $this->info('All corrections applied successfully!');
            $this->line('The procurement list should now display correct statuses.');

            return self::SUCCESS;
        }

        return $failureCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}
