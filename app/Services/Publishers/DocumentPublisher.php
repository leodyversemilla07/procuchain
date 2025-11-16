<?php

declare(strict_types=1);

namespace App\Services\Publishers;

use App\DataTransferObjects\DocumentData;
use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Repositories\DocumentRepository;
use App\Services\BlockchainStorageService;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Document Publisher Service
 *
 * Publishes documents to the blockchain
 * - Handles file upload and validation
 * - Publishes to procurement.documents stream
 * - Returns transaction ID for tracking
 */
class DocumentPublisher
{
    public function __construct(
        private BlockchainStorageService $fileStorage,
        private DocumentRepository $documents
    ) {}

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
     * @param  array|null  $stageMetadata  Optional stage-specific metadata
     * @return array File and document information
     *
     * @throws Exception If publication fails
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
    ): array {
        try {
            // Step 1: Upload file to blockchain
            Log::info('DocumentPublisher: Uploading file', [
                'pr_number' => $prNumber,
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'stage' => $stage->value,
            ]);

            $fileResult = $this->fileStorage->uploadFile(
                $file,
                $prNumber,
                $stage->getId(),
                $documentType->value,
                [
                    'pr_number' => $prNumber,
                    'procurement_title' => $procurementTitle,
                    'stage' => $stage->value,
                    'document_type' => $documentType->value,
                ]
            );

            // Step 2: Publish document metadata
            Log::info('DocumentPublisher: Publishing metadata', [
                'pr_number' => $prNumber,
                'file_key' => $fileResult['file_key'],
            ]);

            $document = new DocumentData(
                prNumber: $prNumber,
                procurementTitle: $procurementTitle,
                userAddress: $userAddress,
                stage: $stage->value,
                status: $status,
                documentType: $stage->value, // Must match stage for blockchain filter
                fileKey: $fileResult['file_key'],
                fileName: $fileResult['filename'],
                fileSize: $fileResult['size'],
                mimeType: $fileResult['mime_type'],
                hash: $fileResult['hash'],
                dataTxid: $fileResult['data_txid'],
                metadataTxid: $fileResult['metadata_txid'],
                uploadedBy: $uploadedBy,
                timestamp: now(),
                description: $description,
                stageMetadata: $stageMetadata,
            );

            $txid = $this->documents->create($document);

            Log::info('DocumentPublisher: Success', [
                'pr_number' => $prNumber,
                'document_txid' => $txid,
            ]);

            return [
                'success' => true,
                'document_txid' => $txid,
                'file' => [
                    'file_key' => $fileResult['file_key'],
                    'filename' => $fileResult['filename'],
                    'size' => $fileResult['size'],
                    'hash' => $fileResult['hash'],
                    'data_txid' => $fileResult['data_txid'],
                    'metadata_txid' => $fileResult['metadata_txid'],
                ],
            ];
        } catch (Exception $e) {
            Log::error('DocumentPublisher: Failed', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Publish multiple documents at once
     *
     * @param  array  $documents  Array of document data with files
     * @return array Results for each document
     */
    public function publishBatch(array $documents): array
    {
        $results = [];
        $errors = [];

        foreach ($documents as $index => $docData) {
            try {
                $result = $this->publish(
                    prNumber: $docData['pr_number'],
                    procurementTitle: $docData['procurement_title'],
                    userAddress: $docData['user_address'],
                    stage: $docData['stage'],
                    status: $docData['status'],
                    documentType: $docData['document_type'],
                    file: $docData['file'],
                    uploadedBy: $docData['uploaded_by'],
                    description: $docData['description'] ?? null,
                    stageMetadata: $docData['stage_metadata'] ?? null,
                );

                $results[] = $result;
            } catch (Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'pr_number' => $docData['pr_number'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => empty($errors),
            'published' => count($results),
            'failed' => count($errors),
            'results' => $results,
            'errors' => $errors,
        ];
    }
}
