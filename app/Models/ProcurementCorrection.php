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
 * ProcurementCorrection Model
 *
 * Correction records synced FROM blockchain.
 * Source: procurement.corrections stream
 */
class ProcurementCorrection extends Model
{
    protected $table = 'procurement_corrections';

    protected $fillable = [
        'procurement_id',
        'correction_type',
        'action',
        'reason',
        'original_txid',
        'original_document_hash',
        'corrected_by',
        'user_address',
        'txid',
        'data_hash',
        'blockchain_hash',
        'is_blockchain_verified',
        'last_verified_at',
        'has_breach',
        'corrected_metadata',
        'corrected_at',
    ];

    protected $casts = [
        'corrected_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'is_blockchain_verified' => 'boolean',
        'has_breach' => 'boolean',
        'corrected_metadata' => 'array',
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
            'correction_type',
            'action',
            'reason',
            'original_txid',
            'corrected_by',
            'corrected_at',
        ];
    }

    public function toBlockchainArray(): array
    {
        return [
            'pr_number' => $this->procurement?->pr_number ?? '',
            'procurement_title' => $this->procurement?->title ?? '',
            'original_txid' => $this->original_txid,
            'original_document_hash' => $this->original_document_hash,
            'correction_type' => $this->correction_type,
            'action' => $this->action,
            'reason' => $this->reason,
            'corrected_by' => $this->corrected_by,
            'user_address' => $this->user_address ?? '',
            'timestamp' => $this->corrected_at?->toIso8601String() ?? now()->toIso8601String(),
            'corrected_metadata' => $this->corrected_metadata,
        ];
    }

    public static function fromBlockchainArray(array $data, string $txid): self
    {
        $model = new static;

        $correctedBy = $data['corrected_by'] ?? '';
        if (is_array($correctedBy)) {
            $correctedBy = $correctedBy['name'] ?? ($correctedBy['id'] ?? '');
        }

        $model->original_txid = $data['original_txid'] ?? '';
        $model->original_document_hash = $data['original_document_hash'] ?? '';
        $model->correction_type = $data['correction_type'] ?? '';
        $model->action = $data['action'] ?? '';
        $model->reason = $data['reason'] ?? '';
        $model->corrected_by = (string) $correctedBy;
        $model->user_address = $data['user_address'] ?? '';
        $model->corrected_at = isset($data['timestamp']) ? Carbon::parse($data['timestamp'])->setTimezone('Asia/Manila') : now();
        $model->corrected_metadata = $data['corrected_metadata'] ?? null;
        $model->txid = $txid;

        return $model;
    }

    public function publishToBlockchain(): ?string
    {
        try {
            $txid = app(BlockchainRpcClient::class)->publish(
                Stream::CORRECTIONS->value,
                $this->procurement?->pr_number ?? '',
                ['json' => $this->toBlockchainArray()]
            );

            if (! is_string($txid) || $txid === '') {
                throw new \RuntimeException('Blockchain correction publish did not return a transaction id.');
            }

            Log::info('Correction published to blockchain', ['txid' => $txid]);

            return $txid;
        } catch (\Exception $e) {
            Log::error('Failed to publish correction', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
