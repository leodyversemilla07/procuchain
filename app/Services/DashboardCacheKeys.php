<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Centralized cache key management for dashboard controllers
 *
 * This class provides consistent cache key naming and makes it easier
 * to invalidate caches programmatically across different dashboard views.
 */
class DashboardCacheKeys
{
    /**
     * Get cache key for procurements by key data
     */
    public static function procurements(string $role): string
    {
        return "dashboard:{$role}:procurements_by_key";
    }

    /**
     * Get cache key for dashboard statistics
     */
    public static function stats(string $role): string
    {
        return "dashboard:{$role}:stats";
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
    public static function totalDocuments(string $role): string
    {
        return "dashboard:{$role}:total_documents";
    }

    /**
     * Get cache key for procurement distribution data
     */
    public static function procurementDistribution(string $role): string
    {
        return "dashboard:{$role}:procurement_distribution";
    }

    /**
     * Get cache key for priority actions (BAC Secretariat)
     */
    public static function priorityActions(string $role): string
    {
        return "dashboard:{$role}:priority_actions";
    }

    /**
     * Get cache key for priority actions count (BAC Secretariat)
     */
    public static function priorityActionsCount(string $role): string
    {
        return "dashboard:{$role}:priority_actions_count";
    }

    /**
     * Get cache key for user activity analytics (Admin)
     */
    public static function userActivityAnalytics(string $role): string
    {
        return "dashboard:{$role}:user_activity";
    }

    /**
     * Clear all dashboard caches for a specific role
     *
     * Important: Dashboard uses two cache stores:
     * - 'database' store for large data (procurements, activities, distribution)
     * - 'default' store for small data (stats)
     */
    public static function clearAll(string $role): void
    {
        // Keys stored in database cache (large data via rememberLarge)
        $databaseKeys = [
            self::procurements($role),
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

        // Also clear user-specific BAC Secretariat cache
        if ($role === 'bac_secretariat') {
            // Clear all user-specific procurement caches
            // These are stored in database cache as well
            try {
                $cacheTable = config('cache.stores.database.table', 'cache');
                $prefix = self::procurements($role).':user:';
                \Illuminate\Support\Facades\DB::table($cacheTable)
                    ->where('key', 'like', $prefix.'%')
                    ->delete();
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to clear user-specific caches', [
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
        $roles = ['admin', 'hope', 'bac_chairman', 'bac_secretariat'];

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
                        \Illuminate\Support\Facades\Log::info('Cleared procurement caches', [
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

                \Illuminate\Support\Facades\Log::info('Cleared procurement caches (non-Redis)', [
                    'method' => 'manual',
                ]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to clear some procurement caches', [
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
            self::stats($role),
            self::recentActivities($role),
            self::totalDocuments($role),
            self::procurementDistribution($role),
            self::priorityActions($role),
            self::priorityActionsCount($role),
            self::userActivityAnalytics($role),
        ];
    }
}
