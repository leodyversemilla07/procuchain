<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentView extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'file_key',
        'procurement_id',
        'procurement_title',
        'document_type',
        'stage',
        'ip_address',
        'user_agent',
        'view_duration',
        'metadata',
        'viewed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'viewed_at' => 'datetime',
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
        return static::with('user:id,name,role,blockchain_address')
            ->where('file_key', $fileKey)
            ->orderBy('viewed_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get view statistics for a procurement
     */
    public static function getProcurementViewStats(string $procurementId)
    {
        return static::with('user:id,name,role')
            ->where('procurement_id', $procurementId)
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
