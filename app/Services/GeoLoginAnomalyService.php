<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\SuspiciousLoginDetected;
use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Geographic Login Anomaly Detection Service
 *
 * Detects and alerts users when login occurs from a new or unusual location.
 * Uses IP-based geolocation via free API (ip-api.com) with fallback.
 */
class GeoLoginAnomalyService
{
    /**
     * Cache TTL for geolocation data (24 hours)
     */
    private const GEO_CACHE_TTL = 86400;

    /**
     * Cache TTL for known locations (30 days)
     */
    private const KNOWN_LOCATION_TTL = 2592000;

    /**
     * Check for login anomaly and send alert if detected
     */
    public function checkAndAlert(User $user, string $ipAddress, ?string $userAgent = null): array
    {
        $result = [
            'is_new_location' => false,
            'location_data' => null,
            'alert_sent' => false,
        ];

        // Skip for testing or local IPs
        if ($this->isLocalIp($ipAddress) || app()->environment('testing')) {
            return $result;
        }

        // Get geolocation for current IP
        $currentLocation = $this->getGeolocation($ipAddress);

        if (empty($currentLocation)) {
            Log::debug('GeoLoginAnomaly: Unable to determine location for IP', ['ip' => $ipAddress]);

            return $result;
        }

        $result['location_data'] = $currentLocation;

        // Check if this is a known location for the user
        $isKnownLocation = $this->isKnownLocation($user->id, $currentLocation);

        if (! $isKnownLocation) {
            $result['is_new_location'] = true;

            // Record this location as known for future logins
            $this->recordKnownLocation($user->id, $currentLocation);

            // Check if user has previous logins (not first login)
            $previousLogins = $this->getPreviousSuccessfulLogins($user->id);

            if ($previousLogins->count() > 0 && $user->email_notifications_enabled) {
                // Send alert email
                $this->sendNewLocationAlert($user, $currentLocation, $ipAddress, $userAgent);
                $result['alert_sent'] = true;

                Log::info('GeoLoginAnomaly: New location alert sent', [
                    'user_id' => $user->id,
                    'location' => $currentLocation,
                    'ip' => $ipAddress,
                ]);
            }
        }

        return $result;
    }

    /**
     * Get geolocation data for an IP address
     */
    public function getGeolocation(string $ipAddress): ?array
    {
        $cacheKey = "geo_ip:{$ipAddress}";

        return Cache::remember($cacheKey, self::GEO_CACHE_TTL, function () use ($ipAddress) {
            return $this->fetchGeolocation($ipAddress);
        });
    }

    /**
     * Fetch geolocation from external API
     */
    private function fetchGeolocation(string $ipAddress): ?array
    {
        try {
            // Using ip-api.com (free tier: 45 requests/minute)
            $url = "http://ip-api.com/json/{$ipAddress}?fields=status,message,country,countryCode,region,regionName,city,lat,lon,isp";

            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'method' => 'GET',
                ],
            ]);

            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                Log::warning('GeoLoginAnomaly: Failed to fetch geolocation', ['ip' => $ipAddress]);

                return null;
            }

            $data = json_decode($response, true);

            if ($data['status'] !== 'success') {
                Log::debug('GeoLoginAnomaly: Geolocation API returned non-success', [
                    'ip' => $ipAddress,
                    'message' => $data['message'] ?? 'Unknown error',
                ]);

                return null;
            }

            return [
                'country' => $data['country'] ?? null,
                'country_code' => $data['countryCode'] ?? null,
                'region' => $data['regionName'] ?? null,
                'city' => $data['city'] ?? null,
                'latitude' => $data['lat'] ?? null,
                'longitude' => $data['lon'] ?? null,
                'isp' => $data['isp'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('GeoLoginAnomaly: Exception during geolocation fetch', [
                'ip' => $ipAddress,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if the location is known for this user
     */
    private function isKnownLocation(int $userId, array $location): bool
    {
        $knownLocations = $this->getKnownLocations($userId);

        foreach ($knownLocations as $known) {
            // Consider same city + country as known location
            if (
                $known['country_code'] === $location['country_code'] &&
                $known['city'] === $location['city']
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get known locations for a user
     */
    private function getKnownLocations(int $userId): array
    {
        $cacheKey = "user_known_locations:{$userId}";

        return Cache::get($cacheKey, []);
    }

    /**
     * Record a new known location for a user
     */
    private function recordKnownLocation(int $userId, array $location): void
    {
        $cacheKey = "user_known_locations:{$userId}";
        $knownLocations = $this->getKnownLocations($userId);

        // Add new location
        $knownLocations[] = [
            'country' => $location['country'],
            'country_code' => $location['country_code'],
            'city' => $location['city'],
            'region' => $location['region'],
            'first_seen' => now()->toDateTimeString(),
        ];

        // Keep only last 20 locations
        if (count($knownLocations) > 20) {
            $knownLocations = array_slice($knownLocations, -20);
        }

        Cache::put($cacheKey, $knownLocations, self::KNOWN_LOCATION_TTL);
    }

    /**
     * Get previous successful logins for a user
     */
    private function getPreviousSuccessfulLogins(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return UserLoginLog::where('user_id', $userId)
            ->where('successful', true)
            ->orderBy('login_at', 'desc')
            ->limit(5)
            ->get();
    }

    /**
     * Send new location login alert email
     */
    private function sendNewLocationAlert(User $user, array $location, string $ipAddress, ?string $userAgent): void
    {
        SuspiciousLoginDetected::dispatch($user, $location, $ipAddress, $userAgent);
    }

    /**
     * Check if IP is local/private
     */
    private function isLocalIp(string $ipAddress): bool
    {
        // Check for localhost
        if (in_array($ipAddress, ['127.0.0.1', '::1', 'localhost'])) {
            return true;
        }

        // Check for private IP ranges
        return ! filter_var(
            $ipAddress,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Format location for display
     */
    public function formatLocation(?array $location): string
    {
        if (empty($location)) {
            return 'Unknown Location';
        }

        $parts = array_filter([
            $location['city'] ?? null,
            $location['region'] ?? null,
            $location['country'] ?? null,
        ]);

        return implode(', ', $parts) ?: 'Unknown Location';
    }

    /**
     * Clear known locations cache for a user (useful for testing or admin reset)
     */
    public function clearKnownLocations(int $userId): void
    {
        Cache::forget("user_known_locations:{$userId}");
    }
}
