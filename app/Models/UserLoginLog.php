<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $ip_address
 * @property string $user_agent
 * @property string|null $device_type
 * @property string|null $browser
 * @property string|null $platform
 * @property string|null $location
 * @property bool $successful
 * @property Carbon|null $login_at
 * @property Carbon|null $logout_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
class UserLoginLog extends Model
{
    use HasFactory;

    protected $fillable = [
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

    protected $casts = [
        'login_at' => 'datetime',
        'logout_at' => 'datetime',
        'successful' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get recent logins for a specific user
     */
    public static function recentLoginsForUser(int $userId, int $limit = 10)
    {
        return static::where('user_id', $userId)
            ->orderBy('login_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get all recent logins across all users (for admin view)
     */
    public static function recentLogins(int $limit = 50)
    {
        return static::with('user')
            ->orderBy('login_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get login stats for dashboard
     */
    public static function getLoginStats()
    {
        $now = now();

        $totalLogins = static::count();
        $successfulLogins = static::where('successful', true)->count();
        $failedLogins = static::where('successful', false)->count();
        $uniqueUsers = static::distinct('user_id')->whereNotNull('user_id')->count();

        return [
            'total_logins' => $totalLogins,
            'successful_logins' => $successfulLogins,
            'failed_logins' => $failedLogins,
            'unique_users' => $uniqueUsers,
            'today_logins' => static::whereDate('login_at', $now->toDateString())->count(),
            'this_week_logins' => static::whereBetween('login_at', [
                $now->copy()->startOfWeek()->toDateTimeString(),
                $now->copy()->endOfWeek()->toDateTimeString(),
            ])->count(),
            'this_month_logins' => static::whereMonth('login_at', $now->month)
                ->whereYear('login_at', $now->year)
                ->count(),
            'failed_today' => static::whereDate('login_at', $now->toDateString())
                ->where('successful', false)
                ->count(),
        ];
    }
}
