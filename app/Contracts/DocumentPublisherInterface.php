<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Exceptions\BlockchainException;
use App\Exceptions\DocumentUploadException;
use Illuminate\Http\UploadedFile;

/**
 * Interface for publishing documents to blockchain storage
 *
 * Implementations handle file upload, hash generation, and blockchain recording
 */
interface DocumentPublisherInterface
{
    /**
     * Publish a document with file to blockchain
     *
     * @param  string  $prNumber  PR Number
     * @param  string  $procurementTitle  Procurement title
     * @param  string  $userAddress  User blockchain address
     * @param  StageEnums  $stage  Stage identifier
     * @param  string  $status  Current status
     * @param  DocumentTypeEnums  $documentType  Document type
     * @param  UploadedFile  $file  File to upload
     * @param  string  $uploadedBy  Who uploaded the document
     * @param  string|null  $description  Optional description
     * @param  array<string, mixed>|null  $stageMetadata  Optional stage-specific metadata
     * @return array{
     *     success: bool,
     *     file_txid: string,
     *     document_txid: string,
     *     file_hash: string,
     *     filename: string,
     *     original_filename: string,
     *     file_size: int,
     *     mime_type: string,
     *     document_type: string,
     *     stage: string,
     *     timestamp: string
     * }
     *
     * @throws DocumentUploadException If publication fails
     */
    public function publish(
        string $prNumber,
        string $procurementTitle,
        string $userAddress,
        StageEnums $stage,
        string $status,
        DocumentTypeEnums $documentType,
        UploadedFile $file,
        string $uploadedBy,
        ?string $description = null,
        ?array $stageMetadata = null
    ): array;

    /**
     * Publish document metadata without file
     *
     * @param  string  $prNumber  PR Number
     * @param  string  $procurementTitle  Procurement title
     * @param  string  $userAddress  User blockchain address
     * @param  StageEnums  $stage  Stage identifier
     * @param  string  $status  Current status
     * @param  DocumentTypeEnums  $documentType  Document type
     * @param  string  $uploadedBy  Who uploaded the document
     * @param  string|null  $description  Optional description
     * @param  array<string, mixed>|null  $stageMetadata  Optional stage-specific metadata
     * @return array<string, mixed>
     *
     * @throws BlockchainException If publication fails
     */
    public function publishMetadataOnly(
        string $prNumber,
        string $procurementTitle,
        string $userAddress,
        StageEnums $stage,
        string $status,
        DocumentTypeEnums $documentType,
        string $uploadedBy,
        ?string $description = null,
        ?array $stageMetadata = null
    ): array;
}
