<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $file_key
 * @property string|null $pr_number
 * @property string|null $procurement_title
 * @property string|null $document_type
 * @property string|null $stage
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property int|null $view_duration
 * @property array|null $metadata
 * @property Carbon|null $viewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
class DocumentView extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'file_key',
        'pr_number',
        'procurement_title',
        'document_type',
        'stage',
        'ip_address',
        'user_agent',
        'view_duration',
        'metadata',
        'viewed_at',
        'txid',
        'data_hash',
        'blockchain_synced_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'viewed_at' => 'datetime',
        'blockchain_synced_at' => 'datetime',
    ];

    /**
     * Get the user who viewed the document
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get recent views for a specific file
     */
    public static function getRecentViewsForFile(string $fileKey, int $limit = 10)
    {
        return static::with(['user:id,name,blockchain_address', 'user.roles:id,name'])
            ->where('file_key', $fileKey)
            ->orderBy('viewed_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get view statistics for a procurement
     */
    public static function getProcurementViewStats(string $pr_number)
    {
        return static::with(['user:id,name', 'user.roles:id,name'])
            ->where('pr_number', $pr_number)
            ->selectRaw('
                file_key,
                document_type,
                stage,
                COUNT(*) as total_views,
                COUNT(DISTINCT user_id) as unique_viewers,
                MAX(viewed_at) as last_viewed_at
            ')
            ->groupBy(['file_key', 'document_type', 'stage'])
            ->orderBy('last_viewed_at', 'desc')
            ->get();
    }

    /**
     * Check if user has viewed a specific file
     */
    public static function hasUserViewedFile(int $userId, string $fileKey): bool
    {
        return static::where('user_id', $userId)
            ->where('file_key', $fileKey)
            ->exists();
    }

    /**
     * Get most viewed documents
     */
    public static function getMostViewedDocuments(int $limit = 10)
    {
        return static::selectRaw('
                file_key,
                document_type,
                procurement_title,
                stage,
                COUNT(*) as total_views,
                COUNT(DISTINCT user_id) as unique_viewers,
                MAX(viewed_at) as last_viewed_at
            ')
            ->groupBy(['file_key', 'document_type', 'procurement_title', 'stage'])
            ->orderBy('total_views', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get detailed statistics for a file
     */
    public static function getFileStatistics(string $fileKey): array
    {
        return [
            'total_views' => static::where('file_key', $fileKey)->count(),
            'unique_viewers' => static::where('file_key', $fileKey)
                ->distinct('user_id')
                ->count('user_id'),
            'today_views' => static::where('file_key', $fileKey)
                ->whereDate('viewed_at', today())
                ->count(),
            'week_views' => static::where('file_key', $fileKey)
                ->where('viewed_at', '>=', now()->subWeek())
                ->count(),
            'month_views' => static::where('file_key', $fileKey)
                ->where('viewed_at', '>=', now()->subMonth())
                ->count(),
            'first_viewed' => static::where('file_key', $fileKey)
                ->orderBy('viewed_at')
                ->first()?->viewed_at,
            'last_viewed' => static::where('file_key', $fileKey)
                ->orderBy('viewed_at', 'desc')
                ->first()?->viewed_at,
        ];
    }
}
