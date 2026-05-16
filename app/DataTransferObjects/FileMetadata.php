<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Carbon\Carbon;

/**
 * File Metadata Transfer Object
 *
 * Represents immutable file metadata stored on blockchain (file.metadata stream)
 */
final class FileMetadata
{
    public function __construct(
        public readonly string $filename,
        public readonly string $fileKey,
        public readonly string $dataTxid,
        public readonly string $dataKey,
        public readonly string $mimeType,
        public readonly int $size,
        public readonly string $hash,
        public readonly string $storageMethod,
        public readonly Carbon $storedAt,
        public readonly ?array $additionalMetadata = null,
    ) {}

    public function toBlockchainArray(): array
    {
        $base = [
            'filename' => $this->filename,
            'file_key' => $this->fileKey,
            'data_txid' => $this->dataTxid,
            'data_key' => $this->dataKey,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'hash' => $this->hash,
            'storage_method' => $this->storageMethod,
            'stored_at' => $this->storedAt->toIso8601String(),
        ];

        // Merge additional metadata if provided
        if ($this->additionalMetadata !== null) {
            $base = array_merge($base, $this->additionalMetadata);
        }

        return $base;
    }

    public static function fromBlockchainArray(array $data): self
    {
        // Extract base fields
        $baseFields = [
            'filename',
            'file_key',
            'data_txid',
            'data_key',
            'mime_type',
            'size',
            'hash',
            'storage_method',
            'stored_at',
        ];

        // Additional metadata is everything else
        $additionalMetadata = array_diff_key($data, array_flip($baseFields));

        return new self(
            filename: $data['filename'],
            fileKey: $data['file_key'],
            dataTxid: $data['data_txid'],
            dataKey: $data['data_key'],
            mimeType: $data['mime_type'],
            size: (int) $data['size'],
            hash: $data['hash'],
            storageMethod: $data['storage_method'],
            storedAt: Carbon::parse($data['stored_at'])->setTimezone(config('app.timezone', 'Asia/Manila')),
            additionalMetadata: ! empty($additionalMetadata) ? $additionalMetadata : null,
        );
    }
}
