<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupDatabaseCache extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cache:cleanup {--hours=24 : Remove cache entries older than this many hours}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up old cache and session data from database to optimize storage';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $cutoffTime = now()->subHours($hours)->timestamp;

        $this->info("Cleaning up database cache and session data older than {$hours} hours...");

        // Clean expired cache entries
        if (DB::getSchemaBuilder()->hasTable('cache')) {
            $deleted = DB::table('cache')
                ->where('expiration', '<', now()->timestamp)
                ->delete();
            $this->info("Deleted {$deleted} expired cache entries");
        }

        // Clean expired cache locks
        if (DB::getSchemaBuilder()->hasTable('cache_locks')) {
            $deleted = DB::table('cache_locks')
                ->where('expiration', '<', now()->timestamp)
                ->delete();
            $this->info("Deleted {$deleted} expired cache locks");
        }

        // Clean old sessions
        if (DB::getSchemaBuilder()->hasTable('sessions')) {
            $deleted = DB::table('sessions')
                ->where('last_activity', '<', $cutoffTime)
                ->delete();
            $this->info("Deleted {$deleted} old sessions");
        }

        // Clean completed/failed jobs older than cutoff
        if (DB::getSchemaBuilder()->hasTable('jobs')) {
            // Note: Active jobs should not be deleted
            $this->warn('Skipping jobs table - only remove if you have custom cleanup logic');
        }

        $this->info('✅ Database cleanup completed!');

        return Command::SUCCESS;
    }
}
