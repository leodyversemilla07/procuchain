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
        return UserLoginLog::recentLogins($limit);
    }

    /**
     * Get suspicious login activities (multiple failed attempts, unusual IPs, etc.)
     */
    public function getSuspiciousActivities(): Collection
    {
        $recentTime = now()->subHours(24);

        return UserLoginLog::where('login_at', '>=', $recentTime)
            ->where(function ($query) use ($recentTime) {
                $query->where('successful', false)
                    ->orWhereRaw('ip_address IN (
                        SELECT ip_address 
                        FROM user_login_logs 
                        WHERE login_at >= ? AND successful = false 
                        GROUP BY ip_address 
                        HAVING COUNT(*) >= 3
                    )', [$recentTime]);
            })->with('user')
            ->orderBy('login_at', 'desc')
            ->get();
    }
}
