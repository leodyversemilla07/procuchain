<?php

namespace App\Services;

use App\Models\ProcurementDocument;
use Illuminate\Http\UploadedFile;

class DocumentUploadService
{
    public function __construct(
        protected FileStorageService $fileStorageService,
        protected DocumentMetadataService $documentMetadataService
    ) {}

    /**
     * Upload files and prepare metadata for procurement documents.
     *
     * @param  UploadedFile[]  $files
     */
    public function uploadAndPrepare(array $files, array $metadata, string $procurementId, string $procurementTitle, string $stageFolder): array
    {
        $metadataArray = $this->documentMetadataService->prepareMetadata(
            $files,
            $metadata,
            $procurementId,
            $procurementTitle,
            $stageFolder
        );

        foreach ($files as $index => $file) {
            // Use base_path and sanitized_document_type from metadataArray
            $meta = &$metadataArray[$index];

            // Upload file with on-chain blockchain storage
            $result = $this->fileStorageService->uploadFile(
                $file,
                $meta['base_path'].'/',
                $meta['sanitized_document_type'],
                [
                    'procurement_id' => $procurementId,
                    'procurement_title' => $procurementTitle,
                    'document_type' => $meta['document_type'] ?? 'unknown',
                    'stage' => $stageFolder,
                ]
            );

            // Store file_key, data_txid, metadata_txid for on-chain storage
            $meta['file_key'] = $result['file_key'];
            $meta['data_txid'] = $result['data_txid'];
            $meta['metadata_txid'] = $result['metadata_txid'];
            $meta['file_hash'] = $result['hash'];

            // Create ProcurementDocument record in database immediately
            // This ensures the record exists before the job tries to update it
            ProcurementDocument::create([
                'procurement_id' => $procurementId,
                'file_key' => $result['file_key'],
                'file_name' => $file->getClientOriginalName(),
                'document_type' => $meta['document_type'] ?? 'unknown',
                'stage' => $stageFolder,
                'metadata' => $meta,
                'data_txid' => $result['data_txid'],
                'metadata_txid' => $result['metadata_txid'],
                'blockchain_status' => 'pending',
                'blockchain_status_updated_at' => now(),
            ]);
        }

        return $metadataArray;
    }
}
