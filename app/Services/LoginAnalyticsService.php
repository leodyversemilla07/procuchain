<?php

namespace App\Services;

use App\Models\UserLoginLog;
use Illuminate\Support\Collection;

class LoginAnalyticsService
{
    /**
     * Get formatted login statistics
     */
    public function getLoginStatistics(): array
    {
        return UserLoginLog::getLoginStats();
    }

    /**
     * Get recent logins for admin dashboard
     */
    public function getRecentLogins(int $limit = 50): Collection
    {
        return UserLoginLog::with(['user' => function ($query) {
            $query->with('roles:id,name');
        }])
            ->select(['id', 'user_id', 'ip_address', 'user_agent', 'device_type', 'browser', 'platform', 'location', 'successful', 'login_at', 'logout_at'])
            ->orderBy('login_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get suspicious login activities (multiple failed attempts, unusual IPs, etc.)
     */
    public function getSuspiciousActivities(): Collection
    {
        $recentTime = now()->subHours(24);

        // First, get IPs with multiple failed attempts (optimized query)
        $suspiciousIps = UserLoginLog::where('login_at', '>=', $recentTime)
            ->where('successful', false)
            ->groupBy('ip_address')
            ->havingRaw('COUNT(*) >= 3')
            ->pluck('ip_address')
            ->toArray();

        // Then get all suspicious logs in a single optimized query
        return UserLoginLog::where('login_at', '>=', $recentTime)
            ->where(function ($query) use ($suspiciousIps) {
                $query->where('successful', false);
                if (! empty($suspiciousIps)) {
                    $query->orWhereIn('ip_address', $suspiciousIps);
                }
            })
            ->with(['user' => function ($query) {
                $query->with('roles:id,name');
            }])
            ->select(['id', 'user_id', 'ip_address', 'user_agent', 'device_type', 'browser', 'platform', 'location', 'successful', 'login_at', 'logout_at'])
            ->orderBy('login_at', 'desc')
            ->limit(50)
            ->get();
    }
}
