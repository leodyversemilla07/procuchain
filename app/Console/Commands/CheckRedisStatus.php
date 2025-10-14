<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class CheckRedisStatus extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'redis:status';

    /**
     * The console command description.
     */
    protected $description = 'Check Redis connection and configuration status';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking Redis Configuration...');
        $this->newLine();

        // Check configuration
        $this->displayConfiguration();
        $this->newLine();

        // Test connection
        $this->testConnection();
        $this->newLine();

        // Test operations
        $this->testOperations();

        return Command::SUCCESS;
    }

    protected function displayConfiguration(): void
    {
        $this->components->info('Configuration:');

        $this->table(
            ['Setting', 'Value'],
            [
                ['Redis Client', config('database.redis.client')],
                ['Redis Host', config('database.redis.default.host')],
                ['Redis Port', config('database.redis.default.port')],
                ['Cache Driver', config('cache.default')],
                ['Queue Driver', config('queue.default')],
                ['Session Driver', config('session.driver')],
            ]
        );
    }

    protected function testConnection(): void
    {
        $this->components->info('Testing Connection:');

        try {
            $result = Redis::ping();
            $this->components->task('Redis Ping', fn () => $result);

            $info = Redis::info();
            $version = $info['redis_version'];
            $this->line("  Redis Version: {$version}");

            // Version status indicator
            if (version_compare($version, '7.4.0', '>=')) {
                $this->line('  Version Status: ✅ Latest 7.x LTS (recommended)');
            } elseif (version_compare($version, '7.0.0', '>=')) {
                $this->line('  Version Status: ⚠️  Consider updating to 7.4+');
            } else {
                $this->line('  Version Status: ⚠️  Update recommended');
            }

            $this->line("  Connected Clients: {$info['connected_clients']}");
            $this->line("  Used Memory: {$info['used_memory_human']}");

        } catch (\Exception $e) {
            $this->components->error('Connection Failed: '.$e->getMessage());
            $this->newLine();
            $this->warn('Make sure Redis is running. You can start it with:');
            $this->line('  docker compose up -d redis');
        }
    }

    protected function testOperations(): void
    {
        $this->components->info('Testing Operations:');

        try {
            // Test Redis direct
            Redis::set('test_key', 'test_value');
            $value = Redis::get('test_key');
            $this->components->task('Redis Set/Get', fn () => $value === 'test_value');
            Redis::del('test_key');

            // Test Cache
            Cache::put('test_cache', 'cache_value', 60);
            $cached = Cache::get('test_cache');
            $this->components->task('Cache Store/Retrieve', fn () => $cached === 'cache_value');
            Cache::forget('test_cache');

            $this->components->success('All operations completed successfully!');

        } catch (\Exception $e) {
            $this->components->error('Operation Failed: '.$e->getMessage());
        }
    }
}
