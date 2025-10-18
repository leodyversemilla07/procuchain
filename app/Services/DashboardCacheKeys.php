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
     */
    public static function clearAll(string $role): void
    {
        $keys = [
            self::procurements($role),
            self::stats($role),
            self::recentActivities($role),
            self::totalDocuments($role),
            self::procurementDistribution($role),
            self::priorityActions($role),
            self::priorityActionsCount($role),
            self::userActivityAnalytics($role),
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
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
