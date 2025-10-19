<?php

use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('UserLoginLog Model - Configuration', function () {
    test('has correct fillable fields', function () {
        $log = new UserLoginLog;
        $expectedFillable = [
            'user_id',
            'ip_address',
            'user_agent',
            'device_type',
            'browser',
            'platform',
            'location',
            'successful',
            'login_at',
            'logout_at',
        ];

        expect($log->getFillable())->toBe($expectedFillable);
    });

    test('casts attributes correctly', function () {
        $log = UserLoginLog::factory()->create([
            'login_at' => now(),
            'logout_at' => now()->addHours(2),
            'successful' => true,
        ]);

        expect($log->login_at)->toBeInstanceOf(\Carbon\Carbon::class);
        expect($log->logout_at)->toBeInstanceOf(\Carbon\Carbon::class);
        expect($log->successful)->toBeBool();
    });

    test('timestamps are managed automatically', function () {
        $log = UserLoginLog::factory()->create();

        expect($log->created_at)->toBeInstanceOf(\Carbon\Carbon::class);
        expect($log->updated_at)->toBeInstanceOf(\Carbon\Carbon::class);
    });
});

describe('UserLoginLog Model - Relationships', function () {
    test('belongs to user', function () {
        $user = User::factory()->create();
        $log = UserLoginLog::factory()->create([
            'user_id' => $user->id,
        ]);

        expect($log->user)->toBeInstanceOf(User::class);
        expect($log->user->id)->toBe($user->id);
    });

    test('can eager load user relationship', function () {
        $user = User::factory()->create();
        $log = UserLoginLog::factory()->create([
            'user_id' => $user->id,
        ]);

        $loadedLog = UserLoginLog::with('user')->find($log->id);

        expect($loadedLog->relationLoaded('user'))->toBeTrue();
        expect($loadedLog->user)->not->toBeNull();
    });

    test('user can have multiple login logs', function () {
        $user = User::factory()->create();

        UserLoginLog::factory()->count(5)->create([
            'user_id' => $user->id,
        ]);

        expect($user->loginLogs)->toHaveCount(5);
    });
});

describe('UserLoginLog Model - Static Methods', function () {
    test('recentLoginsForUser returns logs for specific user', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        UserLoginLog::factory()->count(3)->create([
            'user_id' => $user1->id,
            'login_at' => now()->subMinutes(fake()->numberBetween(1, 100)),
        ]);

        UserLoginLog::factory()->count(2)->create([
            'user_id' => $user2->id,
            'login_at' => now()->subMinutes(fake()->numberBetween(1, 100)),
        ]);

        $logs = UserLoginLog::recentLoginsForUser($user1->id);

        expect($logs)->toHaveCount(3);
        expect($logs->pluck('user_id')->unique()->first())->toBe($user1->id);
    });

    test('recentLoginsForUser respects limit parameter', function () {
        $user = User::factory()->create();

        UserLoginLog::factory()->count(15)->create([
            'user_id' => $user->id,
            'login_at' => now()->subMinutes(fake()->numberBetween(1, 100)),
        ]);

        $logs = UserLoginLog::recentLoginsForUser($user->id, 5);

        expect($logs)->toHaveCount(5);
    });

    test('recentLoginsForUser orders by login_at descending', function () {
        $user = User::factory()->create();

        $oldest = UserLoginLog::factory()->create([
            'user_id' => $user->id,
            'login_at' => now()->subDays(3),
        ]);

        $newest = UserLoginLog::factory()->create([
            'user_id' => $user->id,
            'login_at' => now(),
        ]);

        $middle = UserLoginLog::factory()->create([
            'user_id' => $user->id,
            'login_at' => now()->subDays(1),
        ]);

        $logs = UserLoginLog::recentLoginsForUser($user->id);

        expect($logs->first()->id)->toBe($newest->id);
        expect($logs->last()->id)->toBe($oldest->id);
    });

    test('recentLogins returns logs across all users', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        UserLoginLog::factory()->count(3)->create(['user_id' => $user1->id]);
        UserLoginLog::factory()->count(2)->create(['user_id' => $user2->id]);

        $logs = UserLoginLog::recentLogins();

        expect($logs)->toHaveCount(5);
    });

    test('recentLogins eager loads user relationship', function () {
        $user = User::factory()->create();
        UserLoginLog::factory()->create(['user_id' => $user->id]);

        $logs = UserLoginLog::recentLogins();

        expect($logs->first()->relationLoaded('user'))->toBeTrue();
    });

    test('recentLogins respects limit parameter', function () {
        $user = User::factory()->create();
        UserLoginLog::factory()->count(60)->create(['user_id' => $user->id]);

        $logs = UserLoginLog::recentLogins(20);

        expect($logs)->toHaveCount(20);
    });
});

describe('UserLoginLog Model - Login Stats', function () {
    test('getLoginStats returns correct total logins', function () {
        UserLoginLog::factory()->count(10)->create();

        $stats = UserLoginLog::getLoginStats();

        expect($stats['total_logins'])->toBe(10);
    });

    test('getLoginStats counts successful and failed logins', function () {
        UserLoginLog::factory()->count(7)->create(['successful' => true]);
        UserLoginLog::factory()->count(3)->create(['successful' => false]);

        $stats = UserLoginLog::getLoginStats();

        expect($stats['successful_logins'])->toBe(7);
        expect($stats['failed_logins'])->toBe(3);
    });

    test('getLoginStats counts unique users', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        UserLoginLog::factory()->count(3)->create(['user_id' => $user1->id]);
        UserLoginLog::factory()->count(2)->create(['user_id' => $user2->id]);

        $stats = UserLoginLog::getLoginStats();

        expect($stats['unique_users'])->toBe(2);
    });

    test('getLoginStats counts today logins', function () {
        UserLoginLog::factory()->count(5)->create([
            'login_at' => now(),
        ]);

        UserLoginLog::factory()->count(3)->create([
            'login_at' => now()->subDays(2),
        ]);

        $stats = UserLoginLog::getLoginStats();

        expect($stats['today_logins'])->toBe(5);
    });

    test('getLoginStats counts this week logins', function () {
        UserLoginLog::factory()->count(4)->create([
            'login_at' => now()->startOfWeek()->addDays(2),
        ]);

        UserLoginLog::factory()->count(2)->create([
            'login_at' => now()->subWeeks(2),
        ]);

        $stats = UserLoginLog::getLoginStats();

        expect($stats['this_week_logins'])->toBeGreaterThanOrEqual(4);
    });

    test('getLoginStats counts this month logins', function () {
        UserLoginLog::factory()->count(6)->create([
            'login_at' => now()->startOfMonth()->addDays(5),
        ]);

        UserLoginLog::factory()->count(2)->create([
            'login_at' => now()->subMonths(2),
        ]);

        $stats = UserLoginLog::getLoginStats();

        expect($stats['this_month_logins'])->toBeGreaterThanOrEqual(6);
    });

    test('getLoginStats counts failed logins today', function () {
        UserLoginLog::factory()->count(3)->create([
            'login_at' => now(),
            'successful' => false,
        ]);

        UserLoginLog::factory()->count(2)->create([
            'login_at' => now()->subDays(2),
            'successful' => false,
        ]);

        $stats = UserLoginLog::getLoginStats();

        expect($stats['failed_today'])->toBe(3);
    });

    test('getLoginStats returns all expected keys', function () {
        UserLoginLog::factory()->create();

        $stats = UserLoginLog::getLoginStats();

        expect($stats)->toHaveKeys([
            'total_logins',
            'successful_logins',
            'failed_logins',
            'unique_users',
            'today_logins',
            'this_week_logins',
            'this_month_logins',
            'failed_today',
        ]);
    });
});

describe('UserLoginLog Model - Data Integrity', function () {
    test('requires user_id', function () {
        expect(fn () => UserLoginLog::create([
            'ip_address' => '127.0.0.1',
            'successful' => true,
            'login_at' => now(),
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });

    test('can store nullable fields', function () {
        $user = User::factory()->create();

        $log = UserLoginLog::factory()->create([
            'user_id' => $user->id,
            'logout_at' => null,
            'location' => null,
            'device_type' => null,
        ]);

        expect($log->logout_at)->toBeNull();
        expect($log->location)->toBeNull();
        expect($log->device_type)->toBeNull();
    });

    test('stores login metadata correctly', function () {
        $user = User::factory()->create();

        $log = UserLoginLog::factory()->create([
            'user_id' => $user->id,
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Mozilla/5.0',
            'device_type' => 'desktop',
            'browser' => 'Chrome',
            'platform' => 'Windows',
            'location' => 'Manila, Philippines',
        ]);

        expect($log->ip_address)->toBe('192.168.1.100');
        expect($log->user_agent)->toBe('Mozilla/5.0');
        expect($log->device_type)->toBe('desktop');
        expect($log->browser)->toBe('Chrome');
        expect($log->platform)->toBe('Windows');
        expect($log->location)->toBe('Manila, Philippines');
    });

    test('successful field defaults to boolean', function () {
        $user = User::factory()->create();

        $log = UserLoginLog::factory()->create([
            'user_id' => $user->id,
            'successful' => true,
        ]);

        expect($log->successful)->toBeTrue();
        expect($log->successful)->toBeBool();
    });
});

describe('UserLoginLog Model - Query Scenarios', function () {
    test('can filter successful logins', function () {
        $user = User::factory()->create();

        UserLoginLog::factory()->count(5)->create([
            'user_id' => $user->id,
            'successful' => true,
        ]);

        UserLoginLog::factory()->count(3)->create([
            'user_id' => $user->id,
            'successful' => false,
        ]);

        $successfulLogins = UserLoginLog::where('user_id', $user->id)
            ->where('successful', true)
            ->get();

        expect($successfulLogins)->toHaveCount(5);
    });

    test('can filter failed logins', function () {
        $user = User::factory()->create();

        UserLoginLog::factory()->count(2)->create([
            'user_id' => $user->id,
            'successful' => false,
        ]);

        UserLoginLog::factory()->count(4)->create([
            'user_id' => $user->id,
            'successful' => true,
        ]);

        $failedLogins = UserLoginLog::where('user_id', $user->id)
            ->where('successful', false)
            ->get();

        expect($failedLogins)->toHaveCount(2);
    });

    test('can filter by date range', function () {
        $user = User::factory()->create();

        UserLoginLog::factory()->count(3)->create([
            'user_id' => $user->id,
            'login_at' => now()->subDays(5),
        ]);

        UserLoginLog::factory()->count(2)->create([
            'user_id' => $user->id,
            'login_at' => now()->subDays(15),
        ]);

        $recentLogins = UserLoginLog::where('user_id', $user->id)
            ->where('login_at', '>=', now()->subDays(7))
            ->get();

        expect($recentLogins)->toHaveCount(3);
    });

    test('can filter by IP address', function () {
        $user = User::factory()->create();

        UserLoginLog::factory()->count(3)->create([
            'user_id' => $user->id,
            'ip_address' => '192.168.1.100',
        ]);

        UserLoginLog::factory()->count(2)->create([
            'user_id' => $user->id,
            'ip_address' => '10.0.0.1',
        ]);

        $logsFromIp = UserLoginLog::where('ip_address', '192.168.1.100')->get();

        expect($logsFromIp)->toHaveCount(3);
    });

    test('can get session duration when logout_at exists', function () {
        $user = User::factory()->create();

        $loginTime = now();
        $logoutTime = now()->addHours(2);

        $log = UserLoginLog::factory()->create([
            'user_id' => $user->id,
            'login_at' => $loginTime,
            'logout_at' => $logoutTime,
        ]);

        $duration = $log->login_at->diffInMinutes($log->logout_at);

        expect($duration)->toBeGreaterThanOrEqual(119);
        expect($duration)->toBeLessThanOrEqual(121);
    });
});
