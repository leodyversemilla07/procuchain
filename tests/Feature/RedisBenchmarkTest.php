<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

test('redis connection works for benchmark operations', function () {
    $iterations = 50;

    for ($i = 0; $i < $iterations; $i++) {
        Cache::store('redis')->put("bench_test_{$i}", "value_{$i}", 60);
    }

    for ($i = 0; $i < $iterations; $i++) {
        $value = Cache::store('redis')->get("bench_test_{$i}");
        expect($value)->toBe("value_{$i}");
    }

    // Cleanup
    for ($i = 0; $i < $iterations; $i++) {
        Cache::store('redis')->forget("bench_test_{$i}");
    }
});

test('benchmark command executes successfully', function () {
    $exitCode = Artisan::call('redis:benchmark', ['--iterations' => 100]);

    expect($exitCode)->toBe(0);
});

test('redis can handle concurrent read/write operations', function () {
    $operations = 50;

    // Perform mixed operations
    for ($i = 0; $i < $operations; $i++) {
        Cache::store('redis')->put("concurrent_{$i}", "value_{$i}", 60);
        Cache::store('redis')->has("concurrent_{$i}");
        Cache::store('redis')->get("concurrent_{$i}");
    }

    // Verify data integrity
    for ($i = 0; $i < $operations; $i++) {
        expect(Cache::store('redis')->get("concurrent_{$i}"))->toBe("value_{$i}");
    }

    // Cleanup
    for ($i = 0; $i < $operations; $i++) {
        Cache::store('redis')->forget("concurrent_{$i}");
    }
});

test('redis efficiently handles large complex data structures', function () {
    $largeData = [
        'procurement_id' => 12345,
        'items' => array_fill(0, 100, [
            'name' => 'Procurement Item',
            'quantity' => 10,
            'price' => 1500.50,
            'metadata' => ['approved' => true, 'verified' => true],
        ]),
        'approvers' => array_fill(0, 20, [
            'name' => 'Approver Name',
            'role' => 'BAC Member',
            'timestamp' => now()->toString(),
        ]),
    ];

    // Store complex data
    Cache::store('redis')->put('large_procurement_data', $largeData, 60);

    // Retrieve and verify
    $retrieved = Cache::store('redis')->get('large_procurement_data');
    expect($retrieved)->toBe($largeData);
    expect($retrieved['procurement_id'])->toBe(12345);
    expect(count($retrieved['items']))->toBe(100);
    expect(count($retrieved['approvers']))->toBe(20);

    // Cleanup
    Cache::store('redis')->forget('large_procurement_data');
});

test('redis performance metrics are measurable', function () {
    $iterations = 100;
    $startTime = microtime(true);

    // Perform operations
    for ($i = 0; $i < $iterations; $i++) {
        Cache::store('redis')->put("metric_test_{$i}", "value_{$i}", 60);
        Cache::store('redis')->get("metric_test_{$i}");
    }

    $endTime = microtime(true);
    $totalTime = ($endTime - $startTime) * 1000; // Convert to ms

    // Cleanup
    for ($i = 0; $i < $iterations; $i++) {
        Cache::store('redis')->forget("metric_test_{$i}");
    }

    // Assert that operations completed in reasonable time
    // 100 read/write pairs should complete in under 5 seconds with Redis
    // Parallel test environments may have higher latency
    expect($totalTime)->toBeLessThan(5000);
});
