<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class BenchmarkRedis extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'redis:benchmark {--iterations=1000 : Number of iterations for each test}';

    /**
     * The console command description.
     */
    protected $description = 'Benchmark Redis vs Database performance for cache operations';

    protected int $iterations;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->iterations = (int) $this->option('iterations');

        $this->info('Redis vs Database Performance Benchmark');
        $this->info("Iterations per test: {$this->iterations}");
        $this->newLine();

        // Run all benchmarks
        $results = [
            'write' => $this->benchmarkWriteOperations(),
            'read' => $this->benchmarkReadOperations(),
            'exists' => $this->benchmarkExistsOperations(),
            'delete' => $this->benchmarkDeleteOperations(),
            'complex' => $this->benchmarkComplexData(),
            'concurrent' => $this->benchmarkConcurrentOperations(),
        ];

        $this->newLine();
        $this->displaySummary($results);

        return Command::SUCCESS;
    }

    protected function benchmarkWriteOperations(): array
    {
        $this->components->info('📝 Benchmark 1: Write Operations');

        // Database cache
        $dbTime = $this->measureTime(function () {
            for ($i = 0; $i < $this->iterations; $i++) {
                Cache::store('database')->put("bench_write_{$i}", "value_{$i}", 3600);
            }
        });

        // Redis cache
        $redisTime = $this->measureTime(function () {
            for ($i = 0; $i < $this->iterations; $i++) {
                Cache::store('redis')->put("bench_write_{$i}", "value_{$i}", 3600);
            }
        });

        $improvement = $this->calculateImprovement($dbTime, $redisTime);

        $this->displayResult('Write', $dbTime, $redisTime, $improvement);

        return compact('dbTime', 'redisTime', 'improvement');
    }

    protected function benchmarkReadOperations(): array
    {
        $this->components->info('📖 Benchmark 2: Read Operations');

        // Prepare data
        for ($i = 0; $i < $this->iterations; $i++) {
            Cache::store('database')->put("bench_read_{$i}", "value_{$i}", 3600);
            Cache::store('redis')->put("bench_read_{$i}", "value_{$i}", 3600);
        }

        // Database cache
        $dbTime = $this->measureTime(function () {
            for ($i = 0; $i < $this->iterations; $i++) {
                Cache::store('database')->get("bench_read_{$i}");
            }
        });

        // Redis cache
        $redisTime = $this->measureTime(function () {
            for ($i = 0; $i < $this->iterations; $i++) {
                Cache::store('redis')->get("bench_read_{$i}");
            }
        });

        $improvement = $this->calculateImprovement($dbTime, $redisTime);

        $this->displayResult('Read', $dbTime, $redisTime, $improvement);

        return compact('dbTime', 'redisTime', 'improvement');
    }

    protected function benchmarkExistsOperations(): array
    {
        $this->components->info('🔍 Benchmark 3: Exists Check Operations');

        // Prepare data
        for ($i = 0; $i < 100; $i++) {
            Cache::store('database')->put("bench_exists_{$i}", "value_{$i}", 3600);
            Cache::store('redis')->put("bench_exists_{$i}", "value_{$i}", 3600);
        }

        // Database cache
        $dbTime = $this->measureTime(function () {
            for ($i = 0; $i < $this->iterations; $i++) {
                Cache::store('database')->has('bench_exists_'.($i % 100));
            }
        });

        // Redis cache
        $redisTime = $this->measureTime(function () {
            for ($i = 0; $i < $this->iterations; $i++) {
                Cache::store('redis')->has('bench_exists_'.($i % 100));
            }
        });

        $improvement = $this->calculateImprovement($dbTime, $redisTime);

        $this->displayResult('Exists Check', $dbTime, $redisTime, $improvement);

        return compact('dbTime', 'redisTime', 'improvement');
    }

    protected function benchmarkDeleteOperations(): array
    {
        $this->components->info('🗑️  Benchmark 4: Delete Operations');

        // Prepare data for database
        for ($i = 0; $i < $this->iterations; $i++) {
            Cache::store('database')->put("bench_delete_db_{$i}", "value_{$i}", 3600);
        }

        // Database cache
        $dbTime = $this->measureTime(function () {
            for ($i = 0; $i < $this->iterations; $i++) {
                Cache::store('database')->forget("bench_delete_db_{$i}");
            }
        });

        // Prepare data for Redis
        for ($i = 0; $i < $this->iterations; $i++) {
            Cache::store('redis')->put("bench_delete_redis_{$i}", "value_{$i}", 3600);
        }

        // Redis cache
        $redisTime = $this->measureTime(function () {
            for ($i = 0; $i < $this->iterations; $i++) {
                Cache::store('redis')->forget("bench_delete_redis_{$i}");
            }
        });

        $improvement = $this->calculateImprovement($dbTime, $redisTime);

        $this->displayResult('Delete', $dbTime, $redisTime, $improvement);

        return compact('dbTime', 'redisTime', 'improvement');
    }

    protected function benchmarkComplexData(): array
    {
        $this->components->info('📦 Benchmark 5: Complex Data Structures');

        $complexData = [
            'procurement' => [
                'id' => 12345,
                'title' => 'Office Supplies Procurement',
                'budget' => 50000.00,
                'items' => array_fill(0, 50, [
                    'name' => 'Item',
                    'quantity' => rand(1, 100),
                    'price' => rand(100, 10000) / 100,
                ]),
                'approvers' => array_fill(0, 10, [
                    'name' => 'Approver',
                    'role' => 'BAC Member',
                    'approved' => true,
                ]),
            ],
        ];

        // Database cache
        $dbTime = $this->measureTime(function () use ($complexData) {
            for ($i = 0; $i < $this->iterations; $i++) {
                Cache::store('database')->put("bench_complex_{$i}", $complexData, 3600);
                Cache::store('database')->get("bench_complex_{$i}");
            }
        });

        // Redis cache
        $redisTime = $this->measureTime(function () use ($complexData) {
            for ($i = 0; $i < $this->iterations; $i++) {
                Cache::store('redis')->put("bench_complex_{$i}", $complexData, 3600);
                Cache::store('redis')->get("bench_complex_{$i}");
            }
        });

        $improvement = $this->calculateImprovement($dbTime, $redisTime);

        $this->displayResult('Complex Data', $dbTime, $redisTime, $improvement);

        return compact('dbTime', 'redisTime', 'improvement');
    }

    protected function benchmarkConcurrentOperations(): array
    {
        $this->components->info('⚡ Benchmark 6: Mixed Operations (Real-world Simulation)');

        // Database cache - mixed operations
        $dbTime = $this->measureTime(function () {
            for ($i = 0; $i < $this->iterations; $i++) {
                $key = "bench_mixed_{$i}";
                Cache::store('database')->put($key, "value_{$i}", 3600);
                Cache::store('database')->has($key);
                Cache::store('database')->get($key);
                if ($i % 10 === 0) {
                    Cache::store('database')->forget($key);
                }
            }
        });

        // Redis cache - mixed operations
        $redisTime = $this->measureTime(function () {
            for ($i = 0; $i < $this->iterations; $i++) {
                $key = "bench_mixed_{$i}";
                Cache::store('redis')->put($key, "value_{$i}", 3600);
                Cache::store('redis')->has($key);
                Cache::store('redis')->get($key);
                if ($i % 10 === 0) {
                    Cache::store('redis')->forget($key);
                }
            }
        });

        $improvement = $this->calculateImprovement($dbTime, $redisTime);

        $this->displayResult('Mixed Operations', $dbTime, $redisTime, $improvement);

        return compact('dbTime', 'redisTime', 'improvement');
    }

    protected function measureTime(callable $callback): float
    {
        $start = microtime(true);
        $callback();
        $end = microtime(true);

        return ($end - $start) * 1000; // Convert to milliseconds
    }

    protected function calculateImprovement(float $dbTime, float $redisTime): float
    {
        if ($dbTime == 0) {
            return 0;
        }

        return (($dbTime - $redisTime) / $dbTime) * 100;
    }

    protected function displayResult(string $operation, float $dbTime, float $redisTime, float $improvement): void
    {
        $this->table(
            ['Driver', 'Time (ms)', 'Ops/sec'],
            [
                [
                    'Database',
                    number_format($dbTime, 2),
                    number_format($this->iterations / ($dbTime / 1000), 0),
                ],
                [
                    'Redis',
                    number_format($redisTime, 2),
                    number_format($this->iterations / ($redisTime / 1000), 0),
                ],
            ]
        );

        if ($improvement > 0) {
            $this->components->success("✓ Redis is {$this->formatImprovement($improvement)} faster");
        } else {
            $this->components->warn("⚠ Database was faster by {$this->formatImprovement(abs($improvement))}");
        }

        $this->newLine();
    }

    protected function formatImprovement(float $improvement): string
    {
        if ($improvement >= 100) {
            $times = round($improvement / 100 + 1, 1);

            return "{$times}x ({$this->formatPercentage($improvement)} improvement)";
        }

        return $this->formatPercentage($improvement).' improvement';
    }

    protected function formatPercentage(float $value): string
    {
        return number_format($value, 1).'%';
    }

    protected function displaySummary(array $results): void
    {
        $this->components->info('📊 Summary Report');

        $totalImprovement = 0;
        $count = 0;

        $summaryData = [];
        foreach ($results as $operation => $data) {
            $totalImprovement += $data['improvement'];
            $count++;

            $summaryData[] = [
                ucfirst($operation),
                number_format($data['dbTime'], 2).' ms',
                number_format($data['redisTime'], 2).' ms',
                $this->formatPercentage($data['improvement']),
            ];
        }

        $this->table(
            ['Operation', 'Database', 'Redis', 'Improvement'],
            $summaryData
        );

        $avgImprovement = $totalImprovement / $count;

        $this->newLine();
        $this->components->success("Average Performance Improvement: {$this->formatImprovement($avgImprovement)}");

        // Calculate total time saved
        $totalDbTime = array_sum(array_column($results, 'dbTime'));
        $totalRedisTime = array_sum(array_column($results, 'redisTime'));
        $timeSaved = $totalDbTime - $totalRedisTime;

        $this->line('Total time for all operations:');
        $this->line('  Database: '.number_format($totalDbTime, 2).' ms');
        $this->line('  Redis: '.number_format($totalRedisTime, 2).' ms');
        $this->line('  Time Saved: '.number_format($timeSaved, 2).' ms');

        $this->newLine();
        $this->info('💡 Tip: Run with more iterations for more accurate results:');
        $this->line('  php artisan redis:benchmark --iterations=5000');
    }
}
