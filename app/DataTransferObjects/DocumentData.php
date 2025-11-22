<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Carbon\Carbon;

/**
 * Document Data Transfer Object
 *
 * Represents immutable document metadata stored on blockchain (procurement.documents stream)
 */
final class DocumentData
{
    public function __construct(
        public readonly string $prNumber,
        public readonly string $procurementTitle,
        public readonly string $userAddress,
        public readonly string $stage,
        public readonly string $status,
        public readonly string $documentType,
        public readonly string $fileKey,
        public readonly string $fileName,
        public readonly int $fileSize,
        public readonly string $mimeType,
        public readonly string $hash,
        public readonly string $dataTxid,
        public readonly string $metadataTxid,
        public readonly string $uploadedBy,
        public readonly Carbon $timestamp,
        public readonly ?string $description = null,
        public readonly ?array $stageMetadata = null,
    ) {}

    public function toBlockchainArray(): array
    {
        return [
            'pr_number' => $this->prNumber,
            'procurement_title' => $this->procurementTitle,
            'user_address' => $this->userAddress,
            'stage' => $this->stage,
            'status' => $this->status,
            'document_type' => $this->documentType,
            'file_key' => $this->fileKey,
            'file_name' => $this->fileName,
            'file_size' => $this->fileSize,
            'mime_type' => $this->mimeType,
            'hash' => $this->hash,
            'data_txid' => $this->dataTxid,
            'metadata_txid' => $this->metadataTxid,
            'uploaded_by' => $this->uploadedBy,
            'timestamp' => $this->timestamp->toIso8601String(),
            'description' => $this->description,
            'stage_metadata' => $this->stageMetadata,
        ];
    }

    public static function fromBlockchainArray(array $data): self
    {
        // Backward compatibility: try pr_number first, fall back to pr_number
        $prNumber = $data['pr_number'] ?? '';

        try {
            return new self(
                prNumber: $prNumber,
                procurementTitle: $data['procurement_title'],
                userAddress: $data['user_address'],
                stage: $data['stage'],
                status: $data['status'],
                documentType: $data['document_type'],
                fileKey: $data['file_key'],
                fileName: $data['file_name'],
                fileSize: (int) $data['file_size'],
                mimeType: $data['mime_type'],
                hash: $data['hash'],
                dataTxid: $data['data_txid'],
                metadataTxid: $data['metadata_txid'],
                uploadedBy: $data['uploaded_by'],
                timestamp: isset($data['timestamp']) ? Carbon::parse($data['timestamp']) : Carbon::now(),
                description: $data['description'] ?? null,
                stageMetadata: $data['stage_metadata'] ?? null,
            );
        } catch (\Exception $e) {
            throw new \Exception('Failed to create DocumentData from blockchain array: '.$e->getMessage());
        }
    }

    /**
     * Format timestamp to full date and time
     */
    public function getFormattedDateTime(): string
    {
        return $this->timestamp->format('M j, Y, g:i A');
    }

    /**
     * Format timestamp to date only
     */
    public function getFormattedDateOnly(): string
    {
        return $this->timestamp->format('M j, Y');
    }

    /**
     * Format timestamp to time only
     */
    public function getFormattedTimeOnly(): string
    {
        return $this->timestamp->format('g:i A');
    }

    /**
     * Format file size to human-readable format
     */
    public function getFormattedFileSize(): string
    {
        if ($this->fileSize < 0) {
            return 'N/A';
        }

        if ($this->fileSize === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($this->fileSize) / log(1024));
        $decimals = $i === 1 ? 0 : ($i > 1 ? 1 : 0);
        $size = round($this->fileSize / pow(1024, $i), $decimals);

        return number_format($size, $decimals, '.', ',').' '.$units[$i];
    }

    /**
     * Shorten hash for display
     */
    public function getShortenedHash(int $startLength = 5, int $endLength = 5): string
    {
        if (strlen($this->hash) <= $startLength + $endLength) {
            return $this->hash;
        }

        return substr($this->hash, 0, $startLength).'...'.substr($this->hash, -$endLength);
    }
}
