<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Stream;
use App\Services\BlockchainRpcClient;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

/**
 * ProcurementEvent Model
 *
 * Audit trail events synced FROM blockchain.
 * Source: procurement.events stream
 */
class ProcurementEvent extends Model
{
    protected $table = 'procurement_events';

    protected $fillable = [
        'procurement_id',
        'event_type',
        'category',
        'severity',
        'details',
        'stage',
        'document_count',
        'user_address',
        'user_name',
        'txid',
        'data_hash',
        'blockchain_hash',
        'is_blockchain_verified',
        'last_verified_at',
        'has_breach',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'document_count' => 'integer',
        'occurred_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'is_blockchain_verified' => 'boolean',
        'has_breach' => 'boolean',
        'metadata' => 'array',
    ];

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    /**
     * Get the fields that are included in the hash for integrity verification.
     */
    public static function getHashableFields(): array
    {
        return [
            'procurement_id',
            'event_type',
            'category',
            'severity',
            'details',
            'stage',
            'occurred_at',
        ];
    }

    public function toBlockchainArray(): array
    {
        return [
            'pr_number' => $this->procurement?->pr_number ?? '',
            'procurement_title' => $this->procurement?->title ?? '',
            'stage' => $this->stage,
            'event_type' => $this->event_type,
            'category' => $this->category,
            'severity' => $this->severity,
            'details' => $this->details,
            'document_count' => $this->document_count,
            'user_address' => $this->user_address ?? '',
            'timestamp' => $this->occurred_at?->toIso8601String() ?? now()->toIso8601String(),
            'metadata' => $this->metadata,
        ];
    }

    public static function fromBlockchainArray(array $data): self
    {
        $model = new static;

        $model->stage = $data['stage'] ?? '';
        $model->event_type = $data['event_type'] ?? '';
        $model->category = $data['category'] ?? '';
        $model->severity = $data['severity'] ?? '';
        $model->details = $data['details'] ?? '';
        $model->document_count = (int) ($data['document_count'] ?? 0);
        $model->user_address = $data['user_address'] ?? '';
        $model->occurred_at = isset($data['timestamp']) ? Carbon::parse($data['timestamp'])->setTimezone('Asia/Manila') : now();
        $model->metadata = $data['metadata'] ?? null;

        return $model;
    }

    public function publishToBlockchain(): ?string
    {
        try {
            $key = $this->procurement?->pr_number.'_'.str_replace(' ', '_', strtolower($this->procurement?->title ?? ''));

            $txid = app(BlockchainRpcClient::class)->publish(
                Stream::EVENTS->value,
                $key,
                ['json' => $this->toBlockchainArray()]
            );

            if (! is_string($txid) || $txid === '') {
                throw new \RuntimeException('Blockchain event publish did not return a transaction id.');
            }

            Log::info('Event published to blockchain', ['txid' => $txid]);

            return $txid;
        } catch (\Exception $e) {
            Log::error('Failed to publish event', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getFormattedDateTime(): string
    {
        return $this->occurred_at?->format('M j, Y, g:i A') ?? '';
    }

    public function getFormattedDateOnly(): string
    {
        return $this->occurred_at?->format('M j, Y') ?? '';
    }

    public function getFormattedTimeOnly(): string
    {
        return $this->occurred_at?->format('g:i A') ?? '';
    }
}
