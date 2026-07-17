<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * File Model
 *
 * File storage metadata synced FROM blockchain.
 * Source: File.metadata stream
 */
class File extends Model
{
    use SoftDeletes;

    protected $table = 'Files';

    protected $fillable = [
        'file_key',
        'filename',
        'mime_type',
        'size',
        'hash',
        'storage_method',
        'data_txid',
        'data_key',
        'pr_number',
        'stage',
        'document_type',
        'txid',
        'data_hash',
        'blockchain_hash',
        'is_blockchain_verified',
        'last_verified_at',
        'has_breach',
        'additional_metadata',
        'stored_at',
    ];

    protected $casts = [
        'size' => 'integer',
        'stored_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'is_blockchain_verified' => 'boolean',
        'has_breach' => 'boolean',
        'additional_metadata' => 'array',
    ];

    /**
     * Get the fields that are included in the hash for integrity verification.
     */
    public static function getHashableFields(): array
    {
        return [
            'file_key',
            'filename',
            'mime_type',
            'size',
            'hash',
            'storage_method',
        ];
    }

    public function toBlockchainArray(): array
    {
        $base = [
            'filename' => $this->filename,
            'file_key' => $this->file_key,
            'data_txid' => $this->data_txid,
            'data_key' => $this->data_key,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'hash' => $this->hash,
            'storage_method' => $this->storage_method,
            'stored_at' => $this->stored_at?->toIso8601String() ?? now()->toIso8601String(),
        ];

        if ($this->additional_metadata !== null) {
            $base = array_merge($base, $this->additional_metadata);
        }

        return $base;
    }

    public static function fromBlockchainArray(array $data): self
    {
        $model = new static;

        $baseFields = ['filename', 'file_key', 'data_txid', 'data_key', 'mime_type', 'size', 'hash', 'storage_method', 'stored_at'];
        $additionalMetadata = array_diff_key($data, array_flip($baseFields));

        $model->filename = $data['filename'] ?? '';
        $model->file_key = $data['file_key'] ?? '';
        $model->data_txid = $data['data_txid'] ?? '';
        $model->data_key = $data['data_key'] ?? '';
        $model->mime_type = $data['mime_type'] ?? '';
        $model->size = (int) ($data['size'] ?? 0);
        $model->hash = $data['hash'] ?? '';
        $model->storage_method = $data['storage_method'] ?? '';
        $model->stored_at = isset($data['stored_at']) ? Carbon::parse($data['stored_at'])->setTimezone('Asia/Manila') : now();
        $model->additional_metadata = ! empty($additionalMetadata) ? $additionalMetadata : null;

        return $model;
    }
}
