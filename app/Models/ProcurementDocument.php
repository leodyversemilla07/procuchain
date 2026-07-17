<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Stream;
use App\Services\BlockchainRpcClient;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

/**
 * ProcurementDocument Model
 *
 * Document metadata synced FROM blockchain.
 * Source: procurement.documents stream
 */
class ProcurementDocument extends Model
{
    use SoftDeletes;

    protected $table = 'procurement_documents';

    protected $fillable = [
        'procurement_id',
        'document_type',
        'stage',
        'filename',
        'file_key',
        'mime_type',
        'file_size',
        'hash',
        'description',
        'uploaded_by',
        'user_address',
        'txid',
        'data_hash',
        'blockchain_hash',
        'is_blockchain_verified',
        'last_verified_at',
        'has_breach',
        'is_active',
        'uploaded_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'uploaded_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'is_blockchain_verified' => 'boolean',
        'has_breach' => 'boolean',
        'is_active' => 'boolean',
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
            'document_type',
            'filename',
            'file_key',
            'hash',
            'uploaded_by',
            'uploaded_at',
        ];
    }

    public function toBlockchainArray(): array
    {
        return [
            'pr_number' => $this->procurement?->pr_number ?? '',
            'procurement_title' => $this->procurement?->title ?? '',
            'user_address' => $this->user_address ?? '',
            'stage' => $this->stage,
            'status' => '',
            'document_type' => $this->document_type,
            'file_key' => $this->file_key,
            'file_name' => $this->filename,
            'file_size' => $this->file_size,
            'mime_type' => $this->mime_type ?? '',
            'hash' => $this->hash,
            'data_txid' => $this->txid ?? '',
            'metadata_txid' => '',
            'uploaded_by' => $this->uploaded_by,
            'timestamp' => $this->uploaded_at?->toIso8601String() ?? '',
            'description' => $this->description,
        ];
    }

    public static function fromBlockchainArray(array $data): self
    {
        $model = new static;

        $uploadedBy = $data['uploaded_by'] ?? '';
        if (is_array($uploadedBy)) {
            $uploadedBy = $uploadedBy['name'] ?? ($uploadedBy['id'] ?? '');
        }

        $model->stage = $data['stage'] ?? 'unknown';
        $model->document_type = $data['document_type'] ?? 'unknown';
        $model->file_key = $data['file_key'] ?? '';
        $model->filename = $data['file_name'] ?? 'Unknown File';
        $model->file_size = (int) ($data['file_size'] ?? 0);
        $model->mime_type = $data['mime_type'] ?? 'application/octet-stream';
        $model->hash = $data['hash'] ?? '';
        $model->txid = $data['data_txid'] ?? '';
        $model->uploaded_by = (string) $uploadedBy;
        $model->user_address = $data['user_address'] ?? '';
        $model->uploaded_at = isset($data['timestamp']) ? Carbon::parse($data['timestamp'])->setTimezone('Asia/Manila') : now();
        $model->description = $data['description'] ?? null;

        return $model;
    }

    public function publishToBlockchain(): string
    {
        try {
            $txid = app(BlockchainRpcClient::class)->publish(
                Stream::DOCUMENTS->value,
                $this->procurement?->pr_number ?? $this->file_key,
                ['json' => $this->toBlockchainArray()]
            );

            if (! is_string($txid) || $txid === '') {
                throw new \RuntimeException('Blockchain document publish did not return a transaction id.');
            }

            Log::info('Document published to blockchain', ['txid' => $txid]);

            return $txid;
        } catch (\Exception $e) {
            Log::error('Failed to publish document', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getFormattedDateTime(): string
    {
        return $this->uploaded_at?->format('M j, Y, g:i A') ?? '';
    }

    public function getFormattedDateOnly(): string
    {
        return $this->uploaded_at?->format('M j, Y') ?? '';
    }

    public function getFormattedTimeOnly(): string
    {
        return $this->uploaded_at?->format('g:i A') ?? '';
    }

    public function getFormattedFileSize(): string
    {
        $bytes = (int) $this->file_size;

        if ($bytes < 0) {
            return 'N/A';
        }

        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes) / log(1024));
        $decimals = $i === 1 ? 0 : ($i > 1 ? 1 : 0);
        $size = round($bytes / pow(1024, $i), $decimals);

        return number_format($size, $decimals, '.', ',').' '.$units[$i];
    }

    public function getShortenedHash(int $startLength = 5, int $endLength = 5): string
    {
        if (strlen($this->hash) <= $startLength + $endLength) {
            return $this->hash;
        }

        return substr($this->hash, 0, $startLength).'...'.substr($this->hash, -$endLength);
    }
}
