<?php

declare(strict_types=1);

namespace App\Models;

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
}
