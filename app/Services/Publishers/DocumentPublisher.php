<?php

declare(strict_types=1);

namespace App\Services\Publishers;

use App\Contracts\DocumentPublisherInterface;
use App\DataTransferObjects\DocumentData;
use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Repositories\DocumentRepository;
use App\Services\BlockchainStorageService;
use App\Services\DashboardCacheService;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Document Publisher Service
 *
 * Publishes documents to the blockchain
 * - Handles File upload and validation
 * - Publishes to procurement.documents stream
 * - Returns transaction ID for tracking
 */
class DocumentPublisher implements DocumentPublisherInterface
{
    public function __construct(
        private BlockchainStorageService $BlockchainFileStorage,
        private DocumentRepository $documents
    ) {}

    /**
     * Publish a document with File to blockchain
     *
     * @param  string  $prNumber  PR Number
     * @param  string  $procurementTitle  Procurement title
     * @param  string  $userAddress  User blockchain address
     * @param  StageEnums  $stage  Stage identifier
     * @param  string  $status  Current status
     * @param  DocumentTypeEnums  $documentType  Document type
     * @param  UploadedFile  $File  File to upload
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
        UploadedFile $File,
        string $uploadedBy,
        ?string $description = null,
        ?array $stageMetadata = null
    ): array {
        try {
            // Step 1: Upload File to blockchain
            Log::info('DocumentPublisher: Uploading File', [
                'pr_number' => $prNumber,
                'filename' => $File->getClientOriginalName(),
                'size' => $File->getSize(),
                'stage' => $stage->value,
            ]);

            $BlockchainFileResult = $this->BlockchainFileStorage->uploadFile(
                $File,
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
                'file_key' => $BlockchainFileResult['file_key'],
            ]);

            $document = new DocumentData(
                prNumber: $prNumber,
                procurementTitle: $procurementTitle,
                userAddress: $userAddress,
                stage: $stage->value,
                status: $status,
                documentType: $documentType->value,
                fileKey: $BlockchainFileResult['file_key'],
                filename: $BlockchainFileResult['filename'],
                fileSize: $BlockchainFileResult['size'],
                mimeType: $BlockchainFileResult['mime_type'],
                hash: $BlockchainFileResult['hash'],
                dataTxid: $BlockchainFileResult['data_txid'],
                metadataTxid: $BlockchainFileResult['metadata_txid'],
                uploadedBy: $uploadedBy,
                timestamp: now(),
                description: $description,
                stageMetadata: $stageMetadata,
            );

            $txid = $this->documents->create($document);

            // Invalidate ALL procurement list caches after document update
            $this->clearProcurementListCache();

            Log::info('DocumentPublisher: Success', [
                'pr_number' => $prNumber,
                'document_txid' => $txid,
            ]);

            return [
                'success' => true,
                'document_txid' => $txid,
                'File' => [
                    'file_key' => $BlockchainFileResult['file_key'],
                    'filename' => $BlockchainFileResult['filename'],
                    'size' => $BlockchainFileResult['size'],
                    'hash' => $BlockchainFileResult['hash'],
                    'data_txid' => $BlockchainFileResult['data_txid'],
                    'metadata_txid' => $BlockchainFileResult['metadata_txid'],
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
     * @param  array  $documents  Array of document data with BlockchainFiles
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
                    File: $docData['File'],
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

    /**
     * Publish document metadata without File
     *
     * Used for external documents or references that don't require File upload
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
    ): array {
        try {
            Log::info('DocumentPublisher: Publishing metadata only', [
                'pr_number' => $prNumber,
                'document_type' => $documentType->value,
                'stage' => $stage->value,
            ]);

            $document = new DocumentData(
                prNumber: $prNumber,
                procurementTitle: $procurementTitle,
                userAddress: $userAddress,
                stage: $stage->value,
                status: $status,
                documentType: $documentType->value,
                fileKey: null,
                filename: null,
                fileSize: 0,
                mimeType: null,
                hash: null,
                dataTxid: null,
                metadataTxid: null,
                uploadedBy: $uploadedBy,
                timestamp: now(),
                description: $description,
                stageMetadata: $stageMetadata,
            );

            $txid = $this->documents->create($document);

            Log::info('DocumentPublisher: Metadata published', [
                'pr_number' => $prNumber,
                'document_txid' => $txid,
            ]);

            return [
                'success' => true,
                'document_txid' => $txid,
                'document_type' => $documentType->value,
                'stage' => $stage->value,
                'timestamp' => now()->toISOString(),
            ];
        } catch (Exception $e) {
            Log::error('DocumentPublisher: Metadata publish failed', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Clear all procurement list caches
     * Delegates to centralized cache management
     */
    private function clearProcurementListCache(): void
    {
        DashboardCacheService::clearAllProcurementCaches();

        Log::info('Cleared all procurement caches after document upload');
    }
}
