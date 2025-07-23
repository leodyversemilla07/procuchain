<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class DocumentMetadataService
{
    /**
     * Prepare metadata for procurement documents.
     *
     * @param UploadedFile[] $files
     * @param array $metadata
     * @param string $procurementId
     * @param string $procurementTitle
     * @param string $stageFolder
     * @return array
     */
    public function prepareMetadata(array $files, array $metadata, string $procurementId, string $procurementTitle, string $stageFolder): array
    {
        $sanitizedTitle = preg_replace('/[^a-zA-Z0-9]/', '_', $procurementTitle);
        $basePath = trim("$procurementId-$sanitizedTitle/$stageFolder", '/');
        $metadataArray = [];
        foreach ($files as $index => $file) {
            $documentType = $metadata[$index]['document_type'] ?? "doc-$index";
            $sanitizedDocumentType = preg_replace('/[^a-zA-Z0-9_-]/', '_', $documentType);
            $hash = hash('sha256', file_get_contents($file->getRealPath()));
            $metadataArray[] = array_merge(
                [
                    'hash' => $hash,
                    'file_size' => $file->getSize(),
                    'document_type' => $documentType,
                    'sanitized_document_type' => $sanitizedDocumentType,
                    'base_path' => $basePath,
                ],
                $metadata[$index]
            );
        }
        return $metadataArray;
    }
}
