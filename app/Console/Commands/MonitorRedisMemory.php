<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class MonitorRedisMemory extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'redis:monitor-memory {--warn-threshold=80 : Percentage of memory to trigger warning}';

    /**
     * The console command description.
     */
    protected $description = 'Monitor Redis memory usage and warn if approaching limit (useful for 30MB free tier)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $info = Redis::connection()->info('memory');
            $warnThreshold = (int) $this->option('warn-threshold');

            $usedMemory = $info['used_memory'] ?? 0;
            $usedMemoryHuman = $info['used_memory_human'] ?? 'Unknown';
            $maxMemory = $info['maxmemory'] ?? 0;
            $maxMemoryHuman = $info['maxmemory_human'] ?? 'Unknown';

            // Calculate percentage
            $percentage = $maxMemory > 0 ? ($usedMemory / $maxMemory) * 100 : 0;

            $this->info('Redis Memory Usage Report');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Used Memory', $usedMemoryHuman],
                    ['Max Memory', $maxMemoryHuman ?: '30M (assumed)'],
                    ['Percentage', round($percentage, 2).'%'],
                    ['Peak Memory', $info['used_memory_peak_human'] ?? 'N/A'],
                ]
            );

            // For 30MB free tier, assume 30M if not set
            if ($maxMemory === 0) {
                $maxMemory = 31457280; // 30MB in bytes
                $percentage = ($usedMemory / $maxMemory) * 100;
                $this->warn('⚠️  Max memory not configured. Assuming 30MB free tier.');
            }

            // Check if approaching limit
            if ($percentage >= $warnThreshold) {
                $message = "⚠️  Redis memory usage is at {$percentage}% ({$usedMemoryHuman} / {$maxMemoryHuman})";
                $this->error($message);
                Log::warning('Redis memory usage high', [
                    'used_memory' => $usedMemoryHuman,
                    'max_memory' => $maxMemoryHuman,
                    'percentage' => $percentage,
                ]);

                $this->newLine();
                $this->warn('💡 Optimization Tips:');
                $this->line('  1. Switch CACHE_STORE to database (largest memory consumer)');
                $this->line('  2. Reduce session lifetime in config/session.php');
                $this->line('  3. Ensure queue workers are processing jobs quickly');
                $this->line('  4. Check for stuck jobs: php artisan queue:failed');
                $this->line('  5. Clear cache if needed: php artisan cache:clear');

                return Command::FAILURE;
            }

            $this->info("✅ Redis memory usage is healthy at {$percentage}%");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to connect to Redis: '.$e->getMessage());
            $this->warn('Make sure Redis is configured and running.');

            return Command::FAILURE;
        }
    }
}
