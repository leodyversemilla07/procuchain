<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

/**
 * Interface for blockchain file storage operations
 *
 * Implementations handle file upload, retrieval, and verification on blockchain
 */
interface BlockchainStorageInterface
{
    /**
     * Upload a file directly to blockchain (on-chain storage)
     *
     * @param  UploadedFile  $file  The file to upload
     * @param  string  $prNumber  PR Number (e.g., PR-2025-001)
     * @param  int  $stageId  Stage ID (1-15)
     * @param  string  $documentType  Document type (e.g., "Purchase Request")
     * @param  array<string, mixed>  $metadata  Additional metadata to store on blockchain
     * @return array{
     *     file_key: string,
     *     filename: string,
     *     size: int,
     *     hash: string,
     *     mime_type: string,
     *     data_txid: string,
     *     metadata_txid: string
     * }
     *
     * @throws \Exception If storage fails
     */
    public function uploadFile(
        UploadedFile $file,
        string $prNumber,
        int $stageId,
        string $documentType,
        array $metadata = []
    ): array;

    /**
     * Retrieve a file from blockchain storage
     *
     * @param  string  $fileKey  The unique file key
     * @param  string|null  $dataTxid  Optional data transaction ID for direct retrieval
     * @return array{
     *     content: string,
     *     filename: string,
     *     mime_type: string|null,
     *     size: int,
     *     hash: string,
     *     data_txid: string|null,
     *     file_key: string,
     *     stored_at: string|null
     * }
     *
     * @throws \Exception If file not found or retrieval fails
     */
    public function retrieveFile(string $fileKey, ?string $dataTxid = null): array;

    /**
     * Verify file integrity by comparing stored hash
     *
     * @param  string  $fileKey  The file key
     * @param  string  $metadataTxid  The metadata transaction ID
     * @return bool True if hash matches, false otherwise
     */
    public function verifyFileIntegrity(string $fileKey, string $metadataTxid): bool;

    /**
     * Get file metadata from blockchain
     *
     * @param  string  $metadataTxid  The metadata transaction ID
     * @return array<string, mixed> The file metadata
     */
    public function getFileMetadata(string $metadataTxid): array;
}
