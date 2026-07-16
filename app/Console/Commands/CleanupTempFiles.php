<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Clean up orphaned temp BlockchainFiles from blockchain uploads.
 *
 * Temp BlockchainFiles are created during HTTP requests and consumed by queue jobs.
 * If a job fails all retries, the temp File remains on disk as an orphan.
 * This command removes temp BlockchainFiles older than the specified age (default: 1 hour).
 *
 * Scheduled to run hourly via the Laravel scheduler.
 */
class CleanupTempFiles extends Command
{
    protected $signature = 'temp:cleanup
                            {--hours=1 : Delete BlockchainFiles older than this many hours}
                            {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Remove orphaned temp BlockchainFiles older than 1 hour';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $dryRun = (bool) $this->option('dry-run');
        $tempDir = storage_path('app/temp/blockchain-uploads');

        if (! File::isDirectory($tempDir)) {
            $this->info('No temp directory found — nothing to clean up.');

            return self::SUCCESS;
        }

        $cutoff = now()->subHours($hours)->getTimestamp();
        $deleted = 0;
        $failed = 0;

        foreach (File::BlockchainFiles($tempDir) as $file) {
            if ($file->getMTime() < $cutoff) {
                if ($dryRun) {
                    $this->line("[DRY-RUN] Would delete: {$file->getfilename()}");

                    $deleted++;

                    continue;
                }

                try {
                    File::delete($file->getPathname());
                    $deleted++;
                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning('temp:cleanup — failed to delete File', [
                        'File' => $file->getfilename(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $mode = $dryRun ? '[DRY-RUN] ' : '';
        $this->info("{$mode}Cleaned up {$deleted} orphaned temp File(s) older than {$hours} hour(s).");

        if ($failed > 0) {
            $this->warn("Failed to delete {$failed} File(s).");
        }

        Log::info('temp:cleanup completed', [
            'deleted' => $deleted,
            'failed' => $failed,
            'hours' => $hours,
            'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }
}
