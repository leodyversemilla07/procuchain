<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Cache Strategy Helper - Ensures efficient use of 30MB Redis free tier
 *
 * This helper enforces cache storage to database for large/persistent data
 * while keeping small, frequently-accessed data in Redis (when configured).
 */
class CacheStrategyHelper
{
    /**
     * Cache large or persistent data in database (not Redis)
     *
     * Use this for:
     * - Large arrays/collections
     * - Blockchain data
     * - Search results
     * - Anything >100KB
     *
     * @param  mixed  $value
     */
    public static function rememberLarge(string $key, \DateTimeInterface|\DateInterval|int $ttl, callable $callback): mixed
    {
        return Cache::store('database')->remember($key, $ttl, $callback);
    }

    /**
     * Cache small, frequently-accessed data (can use default cache - Redis or database)
     *
     * Use this for:
     * - Counters
     * - Simple flags
     * - Small config values
     * - Anything <10KB
     *
     * @param  mixed  $value
     */
    public static function rememberSmall(string $key, \DateTimeInterface|\DateInterval|int $ttl, callable $callback): mixed
    {
        // If cache is Redis, this is fine for small data
        // If cache is database, this is also fine
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Put large data in database cache
     */
    public static function putLarge(string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null): bool
    {
        return Cache::store('database')->put($key, $value, $ttl);
    }

    /**
     * Put small data in default cache
     */
    public static function putSmall(string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null): bool
    {
        return Cache::put($key, $value, $ttl);
    }

    /**
     * Get estimated size of a value in KB
     */
    public static function estimateSize(mixed $value): float
    {
        return strlen(serialize($value)) / 1024;
    }

    /**
     * Determine if value is "large" (>100KB) and should use database cache
     */
    public static function isLarge(mixed $value): bool
    {
        return self::estimateSize($value) > 100; // >100KB
    }

    /**
     * Smart cache - automatically chooses database for large values
     *
     * @param  mixed  $value
     */
    public static function rememberSmart(string $key, \DateTimeInterface|\DateInterval|int $ttl, callable $callback): mixed
    {
        // Try to get from cache first
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached;
        }

        // Generate value
        $value = $callback();

        // Choose storage based on size
        if (self::isLarge($value)) {
            Cache::store('database')->put($key, $value, $ttl);
        } else {
            Cache::put($key, $value, $ttl);
        }

        return $value;
    }
}
