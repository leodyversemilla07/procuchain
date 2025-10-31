<?php

namespace App\Contracts;

interface CacheStrategyInterface
{
    /**
     * Cache large or persistent data in database (not Redis)
     *
     * Use this for:
     * - Large arrays/collections
     * - Blockchain data
     * - Search results
     * - Anything >100KB
     */
    public function rememberLarge(string $key, \DateTimeInterface|\DateInterval|int $ttl, callable $callback): mixed;

    /**
     * Cache small, frequently-accessed data (can use default cache - Redis or database)
     *
     * Use this for:
     * - Counters
     * - Simple flags
     * - Small config values
     * - Anything <10KB
     */
    public function rememberSmall(string $key, \DateTimeInterface|\DateInterval|int $ttl, callable $callback): mixed;

    /**
     * Smart cache - automatically chooses database for large values
     */
    public function rememberSmart(string $key, \DateTimeInterface|\DateInterval|int $ttl, callable $callback): mixed;

    /**
     * Put large data in database cache
     */
    public function putLarge(string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null): bool;

    /**
     * Put small data in default cache
     */
    public function putSmall(string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null): bool;

    /**
     * Get estimated size of a value in KB
     */
    public function estimateSize(mixed $value): float;

    /**
     * Determine if value is "large" (>100KB) and should use database cache
     */
    public function isLarge(mixed $value): bool;
}
