<?php

namespace App\Services;

use App\Models\UserLoginLog;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class AdminAnalyticsService
{
    /**
     * Generate complete user activity analytics for the admin dashboard
     */
    public function getUserActivityAnalytics(string $timeRange = '30_days', ?int $userId = null): array
    {
        try {
            return [
                'login_patterns' => $this->getLoginPatterns($timeRange, $userId),
                'role_activity' => $this->getRoleActivityBreakdown($timeRange),
                'session_analytics' => $this->getSessionAnalytics($timeRange, $userId),
                'security_metrics' => $this->getSecurityMetrics($timeRange),
                'generated_at' => now()->toISOString(),
            ];
        } catch (Exception $e) {
            Log::error('Failed to generate user activity analytics', [
                'error' => $e->getMessage(),
                'time_range' => $timeRange,
                'user_id' => $userId,
            ]);

            return $this->getEmptyUserAnalytics();
        }
    }

    /**
     * Get login patterns analytics
     */
    public function getLoginPatterns(string $timeRange, ?int $userId): array
    {
        $dateConstraint = $this->getDateConstraint($timeRange);

        $query = UserLoginLog::query()
            ->when($dateConstraint, fn ($q) => $q->where('login_at', '>=', $dateConstraint))
            ->when($userId, fn ($q) => $q->where('user_id', $userId));

        $totalLogins = $query->count();
        $successfulLogins = $query->where('successful', true)->count();
        $failedLogins = $totalLogins - $successfulLogins;

        $peakHours = $query->selectRaw('HOUR(login_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('count', 'desc')
            ->take(3)
            ->get()
            ->map(fn ($item) => [
                'hour' => $item->hour,
                'count' => $item->count,
                'formatted_hour' => sprintf('%02d:00 - %02d:59', $item->hour, $item->hour),
            ]);

        $dailyLogins = $query->selectRaw('DATE(login_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn ($item) => [$item->date => $item->count]);

        return [
            'total_logins' => $totalLogins,
            'successful_logins' => $successfulLogins,
            'failed_logins' => $failedLogins,
            'success_rate' => $totalLogins > 0 ? round($successfulLogins / $totalLogins * 100, 2) : 0,
            'peak_hours' => $peakHours->toArray(),
            'daily_login_trend' => $dailyLogins->toArray(),
        ];
    }

    /**
     * Get role activity breakdown
     */
    public function getRoleActivityBreakdown(string $timeRange): array
    {
        $dateConstraint = $this->getDateConstraint($timeRange);

        return UserLoginLog::query()
            ->join('users', 'user_login_logs.user_id', '=', 'users.id')
            ->when($dateConstraint, fn ($q) => $q->where('login_at', '>=', $dateConstraint))
            ->selectRaw('users.role, COUNT(*) as login_count')
            ->groupBy('users.role')
            ->orderBy('login_count', 'desc')
            ->get()
            ->mapWithKeys(fn ($item) => [$item->role => $item->login_count])
            ->toArray();
    }

    /**
     * Get session analytics
     */
    public function getSessionAnalytics(string $timeRange, ?int $userId): array
    {
        $dateConstraint = $this->getDateConstraint($timeRange);

        $query = UserLoginLog::query()
            ->when($dateConstraint, fn ($q) => $q->where('login_at', '>=', $dateConstraint))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->where('successful', true);

        // Calculate session durations (assuming logout_at field exists, or estimate from next login)
        $sessions = $query->orderBy('user_id')->orderBy('login_at')->get();

        $sessionDurations = [];
        $userSessions = $sessions->groupBy('user_id');

        foreach ($userSessions as $userId => $userLogins) {
            $sortedLogins = $userLogins->sortBy('login_at');

            for ($i = 0; $i < $sortedLogins->count() - 1; $i++) {
                $currentLogin = $sortedLogins->values()[$i];
                $nextLogin = $sortedLogins->values()[$i + 1];

                // Estimate session duration as time until next login (simplified)
                $duration = $currentLogin->login_at->diffInMinutes($nextLogin->login_at);
                if ($duration > 0 && $duration < 480) { // Max 8 hours session
                    $sessionDurations[] = $duration;
                }
            }
        }

        $averageSessionDuration = ! empty($sessionDurations) ? array_sum($sessionDurations) / count($sessionDurations) : 0;

        // Session frequency analysis
        $dailySessions = $query->selectRaw('DATE(login_at) as date, COUNT(DISTINCT user_id) as unique_users, COUNT(*) as total_sessions')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn ($item) => [$item->date => [
                'unique_users' => $item->unique_users,
                'total_sessions' => $item->total_sessions,
            ]]);

        // User engagement levels
        $userEngagement = $query->selectRaw('user_id, COUNT(*) as session_count')
            ->groupBy('user_id')
            ->get()
            ->map(function ($item) {
                $level = match (true) {
                    $item->session_count >= 20 => 'Highly Active',
                    $item->session_count >= 10 => 'Active',
                    $item->session_count >= 5 => 'Moderate',
                    $item->session_count >= 1 => 'Low',
                    default => 'Inactive',
                };

                return [
                    'user_id' => $item->user_id,
                    'session_count' => $item->session_count,
                    'engagement_level' => $level,
                ];
            });

        $engagementDistribution = collect($userEngagement)->groupBy('engagement_level')
            ->map(fn ($group) => $group->count())
            ->toArray();

        return [
            'average_session_duration_minutes' => round($averageSessionDuration, 2),
            'total_sessions' => $sessions->count(),
            'unique_active_users' => $sessions->unique('user_id')->count(),
            'daily_session_trends' => $dailySessions,
            'user_engagement_distribution' => $engagementDistribution,
            'sessions_per_user_average' => $sessions->count() > 0 ? round($sessions->count() / $sessions->unique('user_id')->count(), 2) : 0,
        ];
    }

    /**
     * Get security metrics
     */
    public function getSecurityMetrics(string $timeRange): array
    {
        $dateConstraint = $this->getDateConstraint($timeRange);

        $totalAttempts = UserLoginLog::query()
            ->when($dateConstraint, fn ($q) => $q->where('login_at', '>=', $dateConstraint))
            ->count();

        $failedAttempts = UserLoginLog::query()
            ->when($dateConstraint, fn ($q) => $q->where('login_at', '>=', $dateConstraint))
            ->where('successful', false)
            ->count();

        $suspiciousIPs = UserLoginLog::query()
            ->when($dateConstraint, fn ($q) => $q->where('login_at', '>=', $dateConstraint))
            ->where('successful', false)
            ->selectRaw('ip_address, COUNT(*) as failed_count')
            ->groupBy('ip_address')
            ->having('failed_count', '>=', 5)
            ->count();

        return [
            'security_score' => $totalAttempts > 0 ? round((1 - $failedAttempts / $totalAttempts) * 100, 2) : 100,
            'failed_login_rate' => $totalAttempts > 0 ? round($failedAttempts / $totalAttempts * 100, 2) : 0,
            'suspicious_ip_count' => $suspiciousIPs,
        ];
    }

    /**
     * Get empty user analytics fallback
     */
    public function getEmptyUserAnalytics(): array
    {
        return [
            'login_patterns' => [],
            'role_activity' => [],
            'session_analytics' => [],
            'security_metrics' => [],
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Get date constraint based on time range
     */
    private function getDateConstraint(string $timeRange): ?Carbon
    {
        return match ($timeRange) {
            '7_days' => now()->subDays(7),
            '30_days' => now()->subDays(30),
            '90_days' => now()->subDays(90),
            '1_year' => now()->subYear(),
            default => now()->subDays(30),
        };
    }
}
