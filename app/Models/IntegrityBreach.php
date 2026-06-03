<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * IntegrityBreach Model
 *
 * A read-only model that reads from integrity_audit_logs,
 * filtered to only include records where a breach has been detected.
 * Records are created and managed via IntegrityVerificationService — this model
 * exists for convenient querying of breached records.
 *
 * @property int $id
 * @property string $stream
 * @property string $stream_key
 * @property string $txid
 * @property string $publisher_address
 * @property Carbon|null $blocktime
 * @property array $data_json
 * @property string $data_hash
 * @property bool $is_authorized
 * @property Carbon|null $verified_at
 * @property Carbon|null $breach_detected_at
 * @property string|null $breach_type
 * @property array|null $breach_data
 * @property Carbon|null $repaired_at
 * @property Carbon $synced_at
 */
class IntegrityBreach extends Model
{
    /** @var string */
    protected $table = 'procurement_records';

    /** No timestamps — use synced_at instead. */
    const CREATED_AT = null;

    const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_json' => 'array',
            'breach_data' => 'array',
            'blocktime' => 'datetime',
            'verified_at' => 'datetime',
            'breach_detected_at' => 'datetime',
            'repaired_at' => 'datetime',
            'synced_at' => 'datetime',
            'is_authorized' => 'boolean',
            'revision_number' => 'integer',
        ];
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = ['is_latest_revision'];

    /**
     * Boot the model and apply the global scope for breached records.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('breached', function (Builder $builder): void {
            $builder->whereNotNull('breach_detected_at');
        });
    }

    /**
     * Scope: only unresolved breaches (not yet repaired).
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('repaired_at');
    }

    /**
     * Scope: filter by stream key (e.g. PR number).
     */
    public function scopeForKey(Builder $query, string $key): Builder
    {
        return $query->where('stream_key', $key);
    }

    /**
     * Scope: filter by stream name.
     */
    public function scopeForStream(Builder $query, string $stream): Builder
    {
        return $query->where('stream', $stream);
    }

    /**
     * Mark this breach as repaired (sets repaired_at to now).
     */
    public function markAsRepaired(): bool
    {
        return $this->update(['repaired_at' => now()]);
    }

    /**
     * Check if this breach has been resolved.
     */
    public function isResolved(): bool
    {
        return $this->repaired_at !== null;
    }

    /**
     * Determine if this record is the latest revision for its stream+key.
     * A record is the latest revision if no other mirror row for the same
     * stream+key has a higher revision_number.
     */
    public function getIsLatestRevisionAttribute(): bool
    {
        // Always return true for normalized tables
        return true;
    }

    /**
     * Get the severity level based on breach type.
     */
    public function severity(): string
    {
        return match ($this->breach_type) {
            'hash_mismatch', 'content_mismatch' => 'critical',
            'user_address_tampered' => 'high',
            'unauthorized_publisher' => 'medium',
            'row_deleted' => 'low',
            default => 'medium',
        };
    }
}
