<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LoginLoggerService
{
    public function getRecentLogins(int $limit = 50): \Illuminate\Support\Collection
    {
        return UserLoginLog::recentLogins($limit);
    }

    public function getLoginStatistics(): array
    {
        return UserLoginLog::getLoginStats();
    }

    public function getSuspiciousActivities(): \Illuminate\Support\Collection
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

    public function logLogin(User $user, Request $request): ?UserLoginLog
    {
        try {
            $deviceInfo = $request->input('device_info') ? json_decode($request->input('device_info'), true) : [];
            $loginLog = UserLoginLog::create([
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_type' => $deviceInfo['device_type'] ?? null,
                'browser' => $deviceInfo['browser'] ?? null,
                'platform' => $deviceInfo['platform'] ?? null,
                'location' => $deviceInfo['location'] ?? null,
                'successful' => true,
                'login_at' => now(),
            ]);
            $user->resetFailedLoginAttempts();

            return $loginLog;
        } catch (\Exception $e) {
            Log::error('Failed to log user login', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function logFailedLogin(?string $email, Request $request): ?UserLoginLog
    {
        try {
            $user = $email ? User::where('email', $email)->first() : null;
            $deviceInfo = $request->input('device_info') ? json_decode($request->input('device_info'), true) : [];
            $loginLog = UserLoginLog::create([
                'user_id' => $user?->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_type' => $deviceInfo['device_type'] ?? null,
                'browser' => $deviceInfo['browser'] ?? null,
                'platform' => $deviceInfo['platform'] ?? null,
                'location' => $deviceInfo['location'] ?? null,
                'successful' => false,
                'login_at' => now(),
            ]);
            if ($user) {
                $user->incrementFailedLoginAttempts();
            }

            return $loginLog;
        } catch (\Exception $e) {
            Log::error('Failed to log failed login attempt', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function logLogout(User $user): void
    {
        try {
            $recentLogin = UserLoginLog::where('user_id', $user->id)
                ->whereNull('logout_at')
                ->orderBy('login_at', 'desc')
                ->first();
            if ($recentLogin) {
                $recentLogin->update(['logout_at' => now()]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to log user logout', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
