<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Stream;
use App\Services\BlockchainRpcClient;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProcurementStage Model
 *
 * Stage progression history synced FROM blockchain.
 * Source: procurement.status stream
 */
class ProcurementStage extends Model
{
    protected $table = 'procurement_stages';

    protected $fillable = [
        'procurement_id',
        'stage',
        'status',
        'previous_status',
        'entered_at',
        'completed_at',
        'duration_hours',
        'user_address',
        'user_name',
        'txid',
        'data_hash',
        'blockchain_hash',
        'is_blockchain_verified',
        'last_verified_at',
        'has_breach',
        'metadata',
    ];

    protected $casts = [
        'entered_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'is_blockchain_verified' => 'boolean',
        'has_breach' => 'boolean',
        'duration_hours' => 'integer',
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
            'stage',
            'status',
            'previous_status',
            'entered_at',
            'user_address',
        ];
    }

    public function toBlockchainArray(): array
    {
        return [
            'pr_number' => $this->procurement?->pr_number ?? '',
            'procurement_title' => $this->procurement?->title ?? null,
            'stage' => strtolower($this->stage),
            'current_status' => strtolower($this->status),
            'user_address' => $this->user_address ?? '',
            'timestamp' => $this->entered_at?->toIso8601String() ?? now()->toIso8601String(),
            'previous_status' => $this->previous_status ? strtolower($this->previous_status) : null,
            'metadata' => $this->metadata,
        ];
    }

    public static function fromBlockchainArray(array $data): self
    {
        $model = new static;

        $model->stage = $data['stage'] ?? '';
        $model->status = $data['current_status'] ?? '';
        $model->user_address = $data['user_address'] ?? '';
        $model->entered_at = isset($data['timestamp']) ? Carbon::parse($data['timestamp'])->setTimezone('Asia/Manila') : now();
        $model->previous_status = $data['previous_status'] ?? null;
        $model->metadata = $data['metadata'] ?? null;

        return $model;
    }

    public function publishToBlockchain(): ?string
    {
        $txid = app(BlockchainRpcClient::class)->publish(
            Stream::STATUS->value,
            $this->procurement?->pr_number ?? '',
            ['json' => $this->toBlockchainArray()]
        );

        if (! is_string($txid) || $txid === '') {
            throw new \RuntimeException('Blockchain status publish did not return a transaction id.');
        }

        return $txid;
    }

    public function getFormattedDateTime(): string
    {
        return $this->entered_at?->format('M j, Y, g:i A') ?? '';
    }

    public function getFormattedDateOnly(): string
    {
        return $this->entered_at?->format('M j, Y') ?? '';
    }

    public function getFormattedTimeOnly(): string
    {
        return $this->entered_at?->format('g:i A') ?? '';
    }
}
