<?php

namespace App\Services;

use App\Contracts\CacheStrategyInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

/**
 * Cache Strategy Service - Ensures efficient use of 30MB Redis free tier
 *
 * This service enforces cache storage to database for large/persistent data
 * while keeping small, frequently-accessed data in Redis (when configured).
 *
 * RECOMMENDED USAGE (Issue #14 fix - automatic strategy enforcement):
 * - Use remember() for automatic cache strategy (recommended for most cases)
 * - Use put() for automatic cache strategy on writes
 *
 * MANUAL CONTROL (only when you know the data size):
 * - Use rememberLarge() to force database cache
 * - Use rememberSmall() to force default cache
 */
class CacheStrategyService implements CacheStrategyInterface
{
    public function __construct(
        protected CacheRepository $cache
    ) {}

    /**
     * Cache large or persistent data in database (not Redis)
     *
     * Use this for:
     * - Large arrays/collections
     * - Blockchain data
     * - Search results
     * - Anything >100KB
     */
    public function rememberLarge(string $key, \DateTimeInterface|\DateInterval|int $ttl, callable $callback): mixed
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
     */
    public function rememberSmall(string $key, \DateTimeInterface|\DateInterval|int $ttl, callable $callback): mixed
    {
        // If cache is Redis, this is fine for small data
        // If cache is database, this is also fine
        return $this->cache->remember($key, $ttl, $callback);
    }

    /**
     * Put large data in database cache
     */
    public function putLarge(string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null): bool
    {
        return Cache::store('database')->put($key, $value, $ttl);
    }

    /**
     * Put small data in default cache
     */
    public function putSmall(string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null): bool
    {
        return $this->cache->put($key, $value, $ttl);
    }

    /**
     * Get estimated size of a value in KB
     */
    public function estimateSize(mixed $value): float
    {
        return strlen(serialize($value)) / 1024;
    }

    /**
     * Determine if value is "large" (>100KB) and should use database cache
     */
    public function isLarge(mixed $value): bool
    {
        return $this->estimateSize($value) > 100; // >100KB
    }

    /**
     * Smart cache - automatically chooses database for large values
     */
    public function rememberSmart(string $key, \DateTimeInterface|\DateInterval|int $ttl, callable $callback): mixed
    {
        // Try to get from cache first
        $cached = $this->cache->get($key);
        if ($cached !== null) {
            return $cached;
        }

        // Generate value
        $value = $callback();

        // Choose storage based on size
        if ($this->isLarge($value)) {
            Cache::store('database')->put($key, $value, $ttl);
        } else {
            $this->cache->put($key, $value, $ttl);
        }

        return $value;
    }

    /**
     * Automatic cache strategy - RECOMMENDED method (Issue #14 fix)
     *
     * This is the primary method developers should use.
     * Automatically chooses the best cache store based on data size:
     * - Small data (<100KB): Uses default cache (Redis or database)
     * - Large data (≥100KB): Uses database cache to avoid Redis memory limits
     *
     * @param  string  $key  Cache key
     * @param  \DateTimeInterface|\DateInterval|int  $ttl  Time to live
     * @param  callable  $callback  Callback to generate value if not cached
     * @return mixed Cached or generated value
     */
    public function remember(string $key, \DateTimeInterface|\DateInterval|int $ttl, callable $callback): mixed
    {
        return $this->rememberSmart($key, $ttl, $callback);
    }

    /**
     * Automatic cache strategy for writes - RECOMMENDED method (Issue #14 fix)
     *
     * Automatically chooses the best cache store based on data size:
     * - Small data (<100KB): Uses default cache (Redis or database)
     * - Large data (≥100KB): Uses database cache to avoid Redis memory limits
     *
     * @param  string  $key  Cache key
     * @param  mixed  $value  Value to cache
     * @param  \DateTimeInterface|\DateInterval|int|null  $ttl  Time to live (null = forever)
     * @return bool Success status
     */
    public function put(string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null): bool
    {
        if ($this->isLarge($value)) {
            return Cache::store('database')->put($key, $value, $ttl);
        }

        return $this->cache->put($key, $value, $ttl);
    }
}
