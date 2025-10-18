<?php

namespace App\Services;

use App\Mail\AccountLockedMail;
use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Jenssegers\Agent\Agent;

class LoginService
{
    protected Agent $agent;

    public function __construct()
    {
        $this->agent = new Agent;
    }

    /**
     * Log a successful login
     */
    public function logLogin(User $user, Request $request): UserLoginLog
    {
        try {
            $deviceInfo = $this->parseDeviceInfo($request->input('device_info'));

            $loginLog = UserLoginLog::create([
                'user_id' => $user->id,
                'ip_address' => $this->getClientIp($request),
                'user_agent' => $request->userAgent(),
                'device_type' => $this->getDeviceType(),
                'browser' => $this->getBrowser(),
                'platform' => $deviceInfo['platform'] ?? $this->getPlatform(),
                'location' => $this->getLocation($request), // You can implement geolocation later
                'successful' => true,
                'login_at' => now(),
            ]);

            // Reset failed login attempts on successful login
            $user->resetFailedLoginAttempts();

            return $loginLog;
        } catch (\Exception $e) {
            Log::error('Failed to log user login', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            // Return a minimal log entry even if full logging fails
            return new UserLoginLog([
                'user_id' => $user->id,
                'ip_address' => $this->getClientIp($request),
                'successful' => true,
                'login_at' => now(),
            ]);
        }
    }

    /**
     * Log a failed login attempt
     */
    public function logFailedLogin(?string $email, Request $request): UserLoginLog
    {
        try {
            // Try to find user by email for failed attempts
            $user = $email ? User::where('email', $email)->first() : null;
            $deviceInfo = $this->parseDeviceInfo($request->input('device_info'));

            $loginLog = UserLoginLog::create([
                'user_id' => $user?->id,
                'ip_address' => $this->getClientIp($request),
                'user_agent' => $request->userAgent(),
                'device_type' => $this->getDeviceType(),
                'browser' => $this->getBrowser(),
                'platform' => $deviceInfo['platform'] ?? $this->getPlatform(),
                'location' => $this->getLocation($request),
                'successful' => false,
                'login_at' => now(),
            ]);

            // Handle account locking for failed attempts
            if ($user) {
                $this->handleFailedLoginAttempt($user);
            }

            return $loginLog;
        } catch (\Exception $e) {
            Log::error('Failed to log failed login attempt', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return new UserLoginLog([
                'ip_address' => $this->getClientIp($request),
                'successful' => false,
                'login_at' => now(),
            ]);
        }
    }

    /**
     * Log user logout
     */
    public function logLogout(User $user): void
    {
        try {
            // Find the most recent login session and update logout time
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

    /**
     * Get client IP address, handling proxies and load balancers
     */
    protected function getClientIp(Request $request): string
    {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($ipKeys as $key) {
            if (! empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $request->ip();
    }

    /**
     * Get device type (Desktop, Mobile, Tablet)
     */
    protected function getDeviceType(): string
    {
        if ($this->agent->isTablet()) {
            return 'Tablet';
        } elseif ($this->agent->isMobile()) {
            return 'Mobile';
        } elseif ($this->agent->isDesktop()) {
            return 'Desktop';
        }

        return 'Unknown';
    }

    /**
     * Get browser information
     */
    protected function getBrowser(): string
    {
        $browser = $this->agent->browser();
        $version = $this->agent->version($browser);

        return $browser.($version ? " {$version}" : '');
    }

    /**
     * Get platform/OS information with enhanced Windows 11 detection
     */
    protected function getPlatform(): string
    {
        $platform = $this->agent->platform();
        $version = $this->agent->version($platform);
        $userAgent = request()->userAgent() ?? '';

        // Enhanced Windows 11 detection
        if ($platform === 'Windows' && $this->isWindows11($userAgent)) {
            return 'Windows 11';
        }

        return $platform.($version ? " {$version}" : '');
    }

    /**
     * Detect Windows 11 using various indicators
     */
    protected function isWindows11(string $userAgent): bool
    {
        // Check for Windows 11 specific patterns in User Agent
        $windows11Patterns = [
            // Edge on Windows 11 often includes "Windows NT 10.0; Win64; x64; WebView/3.0"
            '/Windows NT 10\.0.*WebView\/3\.0/',
            // Some browsers include "Windows 11" explicitly
            '/Windows 11/',
            // Chrome 110+ on Windows 11 may include specific patterns
            '/Windows NT 10\.0.*Chrome\/1[1-9][0-9]\./',
        ];

        foreach ($windows11Patterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }

        // Additional checks for Windows NT 10.0 that might be Windows 11
        if (preg_match('/Windows NT 10\.0/', $userAgent)) {
            // Check for modern browser versions that are more likely to be on Windows 11
            $modernBrowserPatterns = [
                '/Chrome\/([0-9]+)\./' => 96,  // Chrome 96+ likely on Windows 11
                '/Firefox\/([0-9]+)\./' => 94, // Firefox 94+ likely on Windows 11
                '/Edge\/([0-9]+)\./' => 96,    // Edge 96+ likely on Windows 11
            ];

            foreach ($modernBrowserPatterns as $pattern => $minVersion) {
                if (preg_match($pattern, $userAgent, $matches)) {
                    $version = (int) $matches[1];
                    if ($version >= $minVersion) {
                        // Additional indicators that suggest Windows 11
                        if (strpos($userAgent, 'Win64') !== false &&
                            strpos($userAgent, 'x64') !== false) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Parse enhanced device information from client-side detection
     */
    protected function parseDeviceInfo(?string $deviceInfoJson): array
    {
        if (empty($deviceInfoJson)) {
            return [];
        }

        try {
            $deviceInfo = json_decode($deviceInfoJson, true);

            if (! is_array($deviceInfo)) {
                return [];
            }

            // Use client-side platform detection if available and more accurate
            $result = [];

            if (isset($deviceInfo['platform'])) {
                $result['platform'] = $deviceInfo['platform'];
            }

            // Log additional device info for debugging/analytics
            if (isset($deviceInfo['detectedWindows11']) && $deviceInfo['detectedWindows11']) {
                Log::info('Windows 11 detected via client-side detection', [
                    'user_agent' => request()->userAgent(),
                    'screen_resolution' => $deviceInfo['screenResolution'] ?? 'unknown',
                    'pixel_ratio' => $deviceInfo['pixelRatio'] ?? 'unknown',
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::warning('Failed to parse device info JSON', [
                'device_info' => $deviceInfoJson,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get location information (placeholder for future geolocation implementation)
     */
    protected function getLocation(Request $request): ?string
    {
        // You can implement geolocation here using services like:
        // - MaxMind GeoLite2
        // - IPStack
        // - ipapi.co
        // For now, return null
        return null;
    }

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
    public function getRecentLogins(int $limit = 50): \Illuminate\Support\Collection
    {
        return UserLoginLog::recentLogins($limit);
    }

    /**
     * Get suspicious login activities (multiple failed attempts, unusual IPs, etc.)
     */
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

    /**
     * Handle failed login attempt and check for account locking
     */
    protected function handleFailedLoginAttempt(User $user): void
    {
        try {
            // Don't process if account is already locked
            if ($user->isAccountLocked()) {
                return;
            }

            // Increment failed attempts
            $user->incrementFailedLoginAttempts();

            // Check if we need to lock the account (3 attempts = lock)
            if ($user->failed_login_attempts >= 3) {
                $lockDurationMinutes = config('auth.account_lockout_duration', 30);
                $user->lockAccount('Account locked due to multiple failed login attempts', $lockDurationMinutes);

                Log::warning('User account locked due to failed login attempts', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'failed_attempts' => $user->failed_login_attempts,
                    'locked_until' => $user->lock_expires_at,                ]);

                // Send account locked notification email if user has email notifications enabled
                if ($user->email_notifications_enabled) {
                    try {
                        Mail::to($user->email)->send(new AccountLockedMail(
                            $user,
                            'Account locked due to multiple failed login attempts',
                            "{$lockDurationMinutes} minutes"
                        ));

                        Log::info('Account locked notification email sent', [
                            'user_id' => $user->id,
                            'user_email' => $user->email,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to send account locked notification email', [
                            'user_id' => $user->id,
                            'user_email' => $user->email,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } else {
                Log::info('Failed login attempt recorded', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'failed_attempts' => $user->failed_login_attempts,
                    'attempts_remaining' => 3 - $user->failed_login_attempts,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle failed login attempt', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if a user account is locked
     */
    public function isAccountLocked(string $email): bool
    {
        $user = User::where('email', $email)->first();

        return $user ? $user->isAccountLocked() : false;
    }

    /**
     * Get locked accounts
     */
    public function getLockedAccounts(): \Illuminate\Support\Collection
    {
        return User::where('account_locked', true)
            ->whereNotNull('locked_at')
            ->with(['loginLogs', 'roles'])
            ->orderBy('locked_at', 'desc')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()?->name ?? null,
                    'two_factor_secret' => $user->two_factor_secret,
                    'two_factor_recovery_codes' => $user->two_factor_recovery_codes ? json_decode($user->two_factor_recovery_codes, true) : null,
                    'two_factor_confirmed_at' => $user->two_factor_confirmed_at,
                    'locked_at' => $user->locked_at,
                    'lock_expires_at' => $user->lock_expires_at,
                    'locked_reason' => $user->locked_reason,
                    'failed_attempts' => $user->failed_login_attempts,
                    'time_remaining' => $user->getLockTimeRemaining(),
                    'time_remaining_minutes' => $user->remaining_lock_time, // Using the accessor attribute
                    'recent_failed_logins' => $user->loginLogs()
                        ->where('successful', false)
                        ->where('login_at', '>=', now()->subHours(24))
                        ->count(),
                ];
            });
    }

    /**
     * Manually unlock a user account (for admin use)
     */
    public function unlockUserAccount(int $userId, string $adminReason = 'Manually unlocked by administrator'): bool
    {
        try {
            $user = User::findOrFail($userId);

            if (! $user->isAccountLocked()) {
                return false; // Account wasn't locked
            }

            $user->unlockAccount($adminReason, false);
            Log::info('User account manually unlocked', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'unlocked_by' => Auth::check() ? Auth::id() : null,
                'reason' => $adminReason,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to unlock user account', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Unlock a user account (wrapper for admin controller)
     */
    public function unlockAccount(User $user, string $reason = 'Manually unlocked by admin'): bool
    {
        try {
            if (! $user->isAccountLocked()) {
                return false; // Account wasn't locked
            }

            $user->unlockAccount($reason, false); // manually unlocked with reason

            Log::info('User account unlocked', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'reason' => $reason,
                'unlocked_by' => Auth::check() ? Auth::id() : null,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to unlock user account', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Lock a user account (wrapper for admin controller)
     */
    public function lockAccount(User $user, string $reason, int $durationHours = 24): bool
    {
        try {
            $durationMinutes = $durationHours * 60;
            $user->lockAccount($reason, $durationMinutes);

            Log::warning('User account manually locked', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'reason' => $reason,
                'duration_hours' => $durationHours,
                'locked_by' => Auth::check() ? Auth::id() : null,
            ]);

            // Send account locked notification email if user has email notifications enabled
            if ($user->email_notifications_enabled) {
                try {
                    Mail::to($user->email)->send(new AccountLockedMail(
                        $user,
                        $reason,
                        "{$durationHours} hours"
                    ));

                    Log::info('Account locked notification email sent for manual lock', [
                        'user_id' => $user->id,
                        'user_email' => $user->email,
                        'reason' => $reason,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send account locked notification email for manual lock', [
                        'user_id' => $user->id,
                        'user_email' => $user->email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to lock user account', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Reset failed login attempts for a user (wrapper for admin controller)
     */
    public function resetFailedAttempts(User $user): bool
    {
        try {
            $previousAttempts = $user->failed_login_attempts;
            $user->resetFailedLoginAttempts();

            Log::info('Failed login attempts reset', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'previous_attempts' => $previousAttempts,
                'reset_by' => Auth::check() ? Auth::id() : null,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to reset failed login attempts', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get account lockout statistics
     */
    public function getAccountLockoutStatistics(): array
    {
        $now = now();
        $last24Hours = $now->subHours(24);
        $last7Days = $now->subDays(7);

        return [
            'currently_locked' => User::where('account_locked', true)->count(),
            'locked_last_24h' => User::where('locked_at', '>=', $last24Hours)->count(),
            'locked_last_7_days' => User::where('locked_at', '>=', $last7Days)->count(),
            'total_lockouts' => User::whereNotNull('locked_at')->count(),
            'failed_attempts_last_24h' => UserLoginLog::where('successful', false)
                ->where('login_at', '>=', $last24Hours)
                ->count(),
        ];
    }
}
