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
}
