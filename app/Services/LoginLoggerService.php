<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LoginLoggerService
{
    public function __construct(
        protected DeviceDetectionService $deviceDetection
    ) {}

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
                'device_type' => $this->deviceDetection->getDeviceType(),
                'browser' => $this->deviceDetection->getBrowser(),
                'platform' => $deviceInfo['platform'] ?? $this->deviceDetection->getPlatform(),
                'location' => $this->getLocation($request),
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
                'device_type' => $this->deviceDetection->getDeviceType(),
                'browser' => $this->deviceDetection->getBrowser(),
                'platform' => $deviceInfo['platform'] ?? $this->deviceDetection->getPlatform(),
                'location' => $this->getLocation($request),
                'successful' => false,
                'login_at' => now(),
            ]);

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
}
