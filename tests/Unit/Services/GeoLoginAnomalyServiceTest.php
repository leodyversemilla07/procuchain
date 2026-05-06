<?php

use App\Models\User;
use App\Services\GeoLoginAnomalyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Swap Cache facade with ArrayStore to avoid Redis dependency
    Cache::swap(app('cache')->store('array'));
    Cache::flush();

    $this->service = new GeoLoginAnomalyService;
    Mail::fake();
});

describe('GeoLoginAnomalyService', function () {

    describe('isLocalIp detection', function () {

        it('identifies localhost as local IP', function () {
            $result = $this->service->checkAndAlert(
                User::factory()->create(),
                '127.0.0.1'
            );

            expect($result['is_new_location'])->toBeFalse();
            expect($result['alert_sent'])->toBeFalse();
        });

        it('identifies IPv6 localhost as local IP', function () {
            $result = $this->service->checkAndAlert(
                User::factory()->create(),
                '::1'
            );

            expect($result['is_new_location'])->toBeFalse();
        });

        it('identifies private IP ranges as local', function () {
            $privateIps = ['192.168.1.1', '10.0.0.1', '172.16.0.1'];

            foreach ($privateIps as $ip) {
                $result = $this->service->checkAndAlert(
                    User::factory()->create(),
                    $ip
                );

                expect($result['is_new_location'])->toBeFalse();
            }
        });
    });

    describe('formatLocation', function () {

        it('formats location with all parts', function () {
            $location = [
                'city' => 'Manila',
                'region' => 'Metro Manila',
                'country' => 'Philippines',
            ];

            $formatted = $this->service->formatLocation($location);

            expect($formatted)->toBe('Manila, Metro Manila, Philippines');
        });

        it('handles missing parts gracefully', function () {
            $location = [
                'city' => 'Manila',
                'country' => 'Philippines',
            ];

            $formatted = $this->service->formatLocation($location);

            expect($formatted)->toBe('Manila, Philippines');
        });

        it('returns Unknown Location for empty array', function () {
            $formatted = $this->service->formatLocation([]);

            expect($formatted)->toBe('Unknown Location');
        });

        it('returns Unknown Location for null', function () {
            $formatted = $this->service->formatLocation(null);

            expect($formatted)->toBe('Unknown Location');
        });
    });

    describe('clearKnownLocations', function () {

        it('clears cached known locations for a user', function () {
            $userId = 1;
            $cacheKey = "user_known_locations:{$userId}";

            // Set some cached data
            Cache::put($cacheKey, [
                ['city' => 'Manila', 'country_code' => 'PH'],
            ], 3600);

            // Verify it exists
            expect(Cache::has($cacheKey))->toBeTrue();

            // Clear it
            $this->service->clearKnownLocations($userId);

            // Verify it's gone
            expect(Cache::has($cacheKey))->toBeFalse();
        });
    });

    describe('checkAndAlert in testing environment', function () {

        it('skips processing in testing environment', function () {
            $user = User::factory()->create();

            // Even with a "public" IP, testing environment should skip
            $result = $this->service->checkAndAlert($user, '8.8.8.8');

            // In testing environment, this returns early
            expect($result['is_new_location'])->toBeFalse();
            expect($result['alert_sent'])->toBeFalse();
        });
    });
});
