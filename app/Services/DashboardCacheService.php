<?php

namespace App\Services;

use App\Enums\UserRole;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Centralized cache key management for dashboard controllers.
 */
class DashboardCacheService
{
    /**
     * Get cache key for procurements by key data
     */
    public static function procurements(string $role, string|int|null $userId = null): string
    {
        return self::appendUserScope("dashboard:{$role}:procurements_by_key", $userId);
    }

    /**
     * Get cache key for the last known good procurements snapshot.
     */
    public static function procurementsSnapshot(string $role, string|int|null $userId = null): string
    {
        return self::appendUserScope("dashboard:{$role}:procurements_by_key_snapshot", $userId);
    }

    /**
     * Get cache key for dashboard statistics
     */
    public static function stats(string $role, string|int|null $userId = null): string
    {
        return self::appendUserScope("dashboard:{$role}:stats", $userId);
    }

    /**
     * Get cache key for recent activities
     */
    public static function recentActivities(string $role): string
    {
        return "dashboard:{$role}:recent_activities";
    }

    /**
     * Get cache key for total documents count
     */
    public static function totalDocuments(string $role, string|int|null $userId = null): string
    {
        return self::appendUserScope("dashboard:{$role}:total_documents", $userId);
    }

    /**
     * Get cache key for procurement distribution data
     */
    public static function procurementDistribution(string $role, string|int|null $userId = null): string
    {
        return self::appendUserScope("dashboard:{$role}:procurement_distribution", $userId);
    }

    /**
     * Get cache key for priority actions (BAC Secretariat)
     */
    public static function priorityActions(string $role, string|int|null $userId = null): string
    {
        return self::appendUserScope("dashboard:{$role}:priority_actions", $userId);
    }

    /**
     * Get cache key for priority actions count (BAC Secretariat)
     */
    public static function priorityActionsCount(string $role, string|int|null $userId = null): string
    {
        return self::appendUserScope("dashboard:{$role}:priority_actions_count", $userId);
    }

    /**
     * Get cache key for user activity analytics (Admin)
     */
    public static function userActivityAnalytics(string $role): string
    {
        return "dashboard:{$role}:user_activity";
    }

    /**
     * Clear all dashboard caches for a specific role.
     */
    public static function clearAll(string $role): void
    {
        // Keys stored in database cache (large data via rememberLarge)
        $databaseKeys = [
            self::procurements($role),
            self::procurementsSnapshot($role),
            self::recentActivities($role),
            self::procurementDistribution($role),
            self::priorityActions($role),
            self::userActivityAnalytics($role),
        ];

        // Keys stored in default cache (small data via rememberSmall)
        $defaultKeys = [
            self::stats($role),
            self::totalDocuments($role),
            self::priorityActionsCount($role),
        ];

        // Clear database cache keys
        foreach ($databaseKeys as $key) {
            Cache::store('database')->forget($key);
        }

        // Clear default cache keys
        foreach ($defaultKeys as $key) {
            Cache::forget($key);
        }

        if ($role === UserRole::BAC_SECRETARIAT->value) {
            try {
                $cacheTable = config('cache.stores.database.table', 'cache');
                $prefix = "dashboard:{$role}:%:user:%";
                DB::table($cacheTable)
                    ->where('key', 'like', $prefix)
                    ->delete();
            } catch (\Exception $e) {
                Log::warning('Failed to clear user-specific caches', [
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $cacheStore = Cache::getStore();
                if (method_exists($cacheStore, 'getRedis')) {
                    $redis = $cacheStore->getRedis();
                    $prefix = config('cache.prefix', 'laravel_cache');
                    $keys = $redis->keys($prefix.":dashboard:{$role}:*:user:*");

                    if (! empty($keys)) {
                        foreach ($keys as $key) {
                            $cleanKey = str_replace($prefix.':', '', $key);
                            Cache::forget($cleanKey);
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to clear user-specific Redis caches', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Clear all dashboard caches across all roles
     */
    public static function clearAllRoles(): void
    {
        $roles = UserRole::values();

        foreach ($roles as $role) {
            self::clearAll($role);
        }
    }

    /**
     * Clear all procurement-related caches (dashboard + user-specific)
     * Called when procurement data changes (status updates, document uploads, etc.)
     */
    public static function clearAllProcurementCaches(): void
    {
        // Clear all dashboard caches for all roles
        self::clearAllRoles();

        // Clear user-specific BAC Secretariat caches
        // Pattern: dashboard:bac_secretariat:procurements_by_key:user:{id}
        try {
            $cacheDriver = Cache::getStore();
            if (method_exists($cacheDriver, 'getRedis')) {
                // Redis cache - use pattern matching
                $redis = $cacheDriver->getRedis();
                $prefix = config('cache.prefix', 'laravel_cache');
                $patterns = [
                    $prefix.':dashboard:*',                          // All dashboard caches
                    $prefix.':procurements:list:*',                  // All procurement list caches
                ];

                foreach ($patterns as $pattern) {
                    $keys = $redis->keys($pattern);
                    if (! empty($keys)) {
                        foreach ($keys as $key) {
                            $cleanKey = str_replace($prefix.':', '', $key);
                            Cache::forget($cleanKey);
                        }
                        Log::info('Cleared procurement caches', [
                            'pattern' => $pattern,
                            'count' => count($keys),
                        ]);
                    }
                }
            } else {
                // File/Database cache - manually clear known patterns
                // This is a fallback for non-Redis environments
                // Clear from both database and default cache stores
                Cache::store('database')->forget('procurements:list:all');
                Cache::store('database')->forget('procurements:list:v6:all');
                Cache::store('database')->forget('procurements:list:v6:all:with-actions');
                Cache::store('database')->forget('procurements:list:v6:all:no-actions');

                // Also try default store
                Cache::forget('procurements:list:all');
                Cache::forget('procurements:list:v6:all');
                Cache::forget('procurements:list:v6:all:with-actions');
                Cache::forget('procurements:list:v6:all:no-actions');

                Log::info('Cleared procurement caches (non-Redis)', [
                    'method' => 'manual',
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to clear some procurement caches', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get all cache keys for a specific role
     *
     * @return array<string>
     */
    public static function getAllKeys(string $role): array
    {
        return [
            self::procurements($role),
            self::procurementsSnapshot($role),
            self::stats($role),
            self::recentActivities($role),
            self::totalDocuments($role),
            self::procurementDistribution($role),
            self::priorityActions($role),
            self::priorityActionsCount($role),
            self::userActivityAnalytics($role),
        ];
    }

    private static function appendUserScope(string $key, string|int|null $userId): string
    {
        if ($userId === null || $userId === '') {
            return $key;
        }

        return "{$key}:user:{$userId}";
    }
}
