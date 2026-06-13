<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Stream;
use App\Models\AuditLog;
use App\Models\DocumentViewLog;
use App\Models\ProcurementWorkflowConfig;
use App\Models\StageDocumentConfig;
use App\Models\UserLoginLog;
use App\Services\BlockchainRpcClient;
use App\Services\BlockchainSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Blockchain Table Sync/Recovery Command
 *
 * Rebuilds any blockchain-backed MySQL table from the immutable chain.
 * Used after MySQL destruction or to verify data integrity.
 *
 * Usage:
 *   php artisan blockchain:sync-table --table=audit_logs
 *   php artisan blockchain:sync-table --table=document_views --restore
 *   php artisan blockchain:sync-table --table=all
 *   php artisan blockchain:sync-table --table=audit_logs --dry-run
 */
class SyncBlockchainTables extends Command
{
    protected $signature = 'blockchain:sync-table
        {--table= : Table to sync (audit_logs, document_views, workflow_configs, stage_document_configs, user_login_logs, or all)}
        {--restore : Actually restore data (without this flag, just shows what would be restored)}
        {--dry-run : Show what would be restored without making changes}';

    protected $description = 'Sync blockchain-backed tables from chain to MySQL (recovery)';

    /**
     * Map of table names to their stream and model class.
     */
    private const TABLE_MAP = [
        'audit_logs' => [
            'stream' => Stream::AUDIT_TRAIL,
            'model' => AuditLog::class,
        ],
        'document_views' => [
            'stream' => Stream::DOCUMENT_ACCESS,
            'model' => DocumentViewLog::class,
        ],
        'procurement_workflow_configs' => [
            'stream' => Stream::CONFIG_WORKFLOWS,
            'model' => ProcurementWorkflowConfig::class,
        ],
        'stage_document_configs' => [
            'stream' => Stream::CONFIG_STAGE_DOCS,
            'model' => StageDocumentConfig::class,
        ],
        'user_login_logs' => [
            'stream' => Stream::USER_LOGIN_SESSIONS,
            'model' => UserLoginLog::class,
        ],
    ];

    public function handle(): int
    {
        $table = $this->option('table');
        $restore = $this->option('restore');
        $dryRun = $this->option('dry-run');

        if (! $table) {
            $this->error('Please specify a table with --table=<name>');
            $this->newLine();
            $this->info('Available tables:');
            foreach (array_keys(self::TABLE_MAP) as $name) {
                $this->line("  - {$name}");
            }
            $this->line('  - all (sync all tables)');

            return self::FAILURE;
        }

        if ($table === 'all') {
            return $this->syncAll($restore, $dryRun);
        }

        if (! isset(self::TABLE_MAP[$table])) {
            $this->error("Unknown table: {$table}");
            $this->info('Available tables: '.implode(', ', array_keys(self::TABLE_MAP)));

            return self::FAILURE;
        }

        return $this->syncTable($table, $restore, $dryRun);
    }

    private function syncTable(string $tableName, bool $restore, bool $dryRun): int
    {
        $config = self::TABLE_MAP[$tableName];
        $stream = $config['stream'];

        $this->info("Syncing: {$tableName} <- {$stream->value}");
        $this->newLine();

        if ($dryRun || ! $restore) {
            $this->warn('DRY RUN — no data will be modified');
            $this->newLine();
        }

        // Count current MySQL records
        $currentCount = DB::table($tableName)->count();
        $this->info("  Current MySQL records: {$currentCount}");

        // Count blockchain records
        try {
            $BlockchainRpcClient = app(BlockchainRpcClient::class);
            $items = $BlockchainRpcClient->liststreamitems($stream->value, true, 100000);
            $chainCount = is_array($items) ? count($items) : 0;
        } catch (\Exception $e) {
            $this->error("  Failed to read from blockchain: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("  Blockchain records: {$chainCount}");
        $this->newLine();

        if ($chainCount === 0) {
            $this->warn('  No records found on blockchain for this stream.');

            return self::SUCCESS;
        }

        if ($dryRun || ! $restore) {
            $this->info('  To restore, run with --restore flag:');
            $this->line("    php artisan blockchain:sync-table --table={$tableName} --restore");

            return self::SUCCESS;
        }

        // Perform the restore
        $this->info('  Restoring from blockchain...');

        $result = app(BlockchainSyncService::class)->restoreTable(
            $tableName,
            $stream,
            $config['model'],
        );

        $this->newLine();
        $this->info("  [OK] Imported: {$result['imported']}");
        $this->info("  Skipped: {$result['skipped']}");
        $this->info("  [FAIL] Errors:  {$result['errors']}");
        $this->newLine();

        // Verify
        $newCount = DB::table($tableName)->count();
        $this->info("  Final MySQL records: {$newCount}");

        return self::SUCCESS;
    }

    private function syncAll(bool $restore, bool $dryRun): int
    {
        $this->info('Syncing ALL blockchain-backed tables...');
        $this->newLine();

        $failed = 0;

        foreach (self::TABLE_MAP as $tableName => $config) {
            $result = $this->syncTable($tableName, $restore, $dryRun);
            if ($result !== self::SUCCESS) {
                $failed++;
            }
            $this->newLine();
        }

        if ($failed > 0) {
            $this->error("{$failed} table(s) failed to sync.");

            return self::FAILURE;
        }

        $this->info('All tables synced successfully.');

        return self::SUCCESS;
    }
}
