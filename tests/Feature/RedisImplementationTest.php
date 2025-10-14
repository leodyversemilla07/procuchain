<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

test('redis connection is working', function () {
    $pong = Redis::ping();

    expect($pong)->toBeTrue();
});

test('redis can store and retrieve data', function () {
    Redis::set('test_key', 'test_value');
    $value = Redis::get('test_key');

    expect($value)->toBe('test_value');

    Redis::del('test_key');
});

test('cache driver is using redis', function () {
    expect(config('cache.default'))->toBe('redis');
});

test('cache can store and retrieve data', function () {
    Cache::put('test_cache_key', 'test_cache_value', 60);

    expect(Cache::has('test_cache_key'))->toBeTrue();
    expect(Cache::get('test_cache_key'))->toBe('test_cache_value');

    Cache::forget('test_cache_key');
});

test('queue driver is using redis', function () {
    expect(config('queue.default'))->toBe('redis');
});

test('session driver is using redis', function () {
    expect(config('session.driver'))->toBe('redis');
});

test('redis can handle complex data structures', function () {
    $data = [
        'name' => 'ProcuChain',
        'version' => '1.0',
        'features' => ['blockchain', 'procurement', 'transparency'],
    ];

    Cache::put('complex_data', $data, 60);

    expect(Cache::get('complex_data'))->toBe($data);

    Cache::forget('complex_data');
});
