<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class DocumentUploadService
{
    protected $fileStorageService;
    protected $documentMetadataService;

    public function __construct(FileStorageService $fileStorageService, DocumentMetadataService $documentMetadataService)
    {
        $this->fileStorageService = $fileStorageService;
        $this->documentMetadataService = $documentMetadataService;
    }

    /**
     * Upload files and prepare metadata for procurement documents.
     *
     * @param UploadedFile[] $files
     * @param array $metadata
     * @param string $procurementId
     * @param string $procurementTitle
     * @param string $stageFolder
     * @return array
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
            $fileKey = $this->fileStorageService->uploadFile(
                $file,
                $meta['base_path'] . '/',
                $meta['sanitized_document_type']
            );
            $meta['file_key'] = $fileKey;
        }

        return $metadataArray;
    }
}
