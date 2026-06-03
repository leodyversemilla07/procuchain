<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Procurement Record Model
 *
 * Stores procurement data backed by blockchain.
 * Each record contains the blockchain stream data, integrity hash,
 * breach tracking, and revision lineage.
 *
 * Blockchain = source of truth (immutable)
 * This table = query cache (mutable, verifiable)
 *
 * @property int $id
 * @property string $stream
 * @property string $stream_key
 * @property string $txid
 * @property int $revision_number
 * @property string|null $parent_txid
 * @property bool $is_latest_revision
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
class ProcurementRecord extends Model
{
    /** @var string */
    protected $table = 'procurement_records';

    /** No timestamps — use synced_at instead. */
    const CREATED_AT = null;

    const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'stream',
        'stream_key',
        'txid',
        'revision_number',
        'parent_txid',
        'is_latest_revision',
        'publisher_address',
        'blocktime',
        'data_json',
        'data_hash',
        'is_authorized',
        'verified_at',
        'breach_detected_at',
        'breach_type',
        'breach_data',
        'repaired_at',
        'synced_at',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_authorized' => true,
        'revision_number' => 1,
        'is_latest_revision' => true,
    ];

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
            'is_latest_revision' => 'boolean',
        ];
    }

    // ─── Revision Relationships ───────────────────────────────────────

    /**
     * Get the parent revision (the previous revision for this stream key).
     *
     * Links via parent_txid to the txid of the preceding record.
     */
    public function parentRevision(): BelongsTo
    {
        return $this->belongsTo(ProcurementRecord::class, 'parent_txid', 'txid')
            ->where('stream', $this->stream)
            ->where('stream_key', $this->stream_key);
    }

    /**
     * Get all child revisions (revisions that point to this record as their parent).
     */
    public function childRevisions(): HasMany
    {
        return $this->hasMany(ProcurementRecord::class, 'parent_txid', 'txid')
            ->where('stream', $this->stream)
            ->where('stream_key', $this->stream_key)
            ->orderBy('revision_number');
    }

    /**
     * Get the latest revision for the same stream + stream_key.
     */
    public function latestRevision(): BelongsTo
    {
        return $this->belongsTo(ProcurementRecord::class, 'stream_key', 'stream_key')
            ->where('stream', $this->stream)
            ->where('is_latest_revision', true);
    }

    // ─── Revision Helpers ─────────────────────────────────────────────

    /**
     * Build the full revision lineage from this record back to the root.
     *
     * Returns an array of txids from oldest (root) to this record,
     * representing the complete chain of modifications.
     *
     * @return string[] Array of txids from root → this revision
     */
    public function getRevisionLineage(): array
    {
        $lineage = [$this->txid];
        $current = $this;

        while ($current->parent_txid !== null) {
            $parent = ProcurementRecord::where('txid', $current->parent_txid)
                ->where('stream', $this->stream)
                ->where('stream_key', $this->stream_key)
                ->first();

            if ($parent === null) {
                break;
            }

            array_unshift($lineage, $parent->txid);
            $current = $parent;
        }

        return $lineage;
    }

    /**
     * Get all revisions for the same stream key, ordered by revision number.
     *
     * @return Collection
     */
    public function getRevisionHistory()
    {
        return ProcurementRecord::where('stream', $this->stream)
            ->where('stream_key', $this->stream_key)
            ->orderBy('revision_number')
            ->get();
    }

    /**
     * Check if this is the first revision (has no parent).
     */
    public function isRootRevision(): bool
    {
        return $this->parent_txid === null;
    }

    /**
     * Check if this is the most recent revision for its stream key.
     */
    public function isLatest(): bool
    {
        return $this->is_latest_revision;
    }

    // ─── Scopes ───────────────────────────────────────────────────────

    /**
     * Filter records by stream name.
     */
    public function scopeForStream($query, string $stream)
    {
        return $query->where('stream', $stream);
    }

    /**
     * Filter records by stream key.
     */
    public function scopeForKey($query, string $key)
    {
        return $query->where('stream_key', $key);
    }

    /**
     * Filter records that have any breach detected.
     */
    public function scopeWithBreaches($query)
    {
        return $query->whereNotNull('breach_detected_at');
    }

    /**
     * Filter records with unresolved breaches (detected but not repaired).
     */
    public function scopeUnresolved($query)
    {
        return $query->whereNotNull('breach_detected_at')
            ->whereNull('repaired_at');
    }

    /**
     * Filter records that are authorized.
     */
    public function scopeAuthorized($query)
    {
        return $query->where('is_authorized', true);
    }

    /**
     * Filter only the latest revision for each stream key.
     */
    public function scopeLatestRevisions($query)
    {
        return $query->where('is_latest_revision', true);
    }

    /**
     * Filter root revisions (no parent).
     */
    public function scopeRootRevisions($query)
    {
        return $query->whereNull('parent_txid');
    }

    /**
     * Filter by revision number.
     */
    public function scopeForRevision($query, int $revisionNumber)
    {
        return $query->where('revision_number', $revisionNumber);
    }

    // ─── Integrity Methods ────────────────────────────────────────────

    /**
     * Verify the integrity of this mirrored record.
     *
     * Computes the SHA-256 hash of the data_json payload and compares
     * it against the stored data_hash. Updates verified_at on success.
     *
     * @return bool True if the computed hash matches data_hash, false otherwise.
     */
    public function verifyIntegrity(): bool
    {
        $computedHash = hash('sha256', json_encode($this->data_json));
        $isValid = $computedHash === $this->data_hash;

        if ($isValid) {
            $this->verified_at = Carbon::now();
            $this->save();
        }

        return $isValid;
    }

    /**
     * Mark this record as breached with the given type and supplementary data.
     *
     * @param  string  $type  The breach type (typically a BreachTypeEnums value)
     * @param  array  $data  Additional breach context data
     */
    public function markAsBreached(string $type, array $data): void
    {
        $this->breach_detected_at = Carbon::now();
        $this->breach_type = $type;
        $this->breach_data = $data;
        $this->save();
    }

    /**
     * Mark this record's breach as repaired.
     */
    public function markAsRepaired(): void
    {
        $this->repaired_at = Carbon::now();
        $this->save();
    }

    /**
     * Check if this record currently has an unresolved breach.
     *
     * A breach is considered active when it has been detected
     * but not yet repaired.
     */
    public function isBreached(): bool
    {
        return $this->breach_detected_at !== null && $this->repaired_at === null;
    }

    /**
     * Demote this record from being the latest revision.
     *
     * Called when a newer revision is published for the same stream key.
     * Does not save — caller must save or let updateOrCreate handle it.
     */
    public function demoteAsLatest(): void
    {
        $this->is_latest_revision = false;
    }
}
