<?php

declare(strict_types=1);

namespace App\Jobs\Handlers;

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Jobs\Handlers\Concerns\HandlesTempFiles;
use App\Services\Publishers\ProcurementOrchestrator;
use Exception;

class DocumentUploadHandler
{
    use HandlesTempFiles;

    public function __construct(
        private readonly ProcurementOrchestrator $orchestrator,
    ) {}

    public function execute(array $data): array
    {
        $file = $this->reconstituteTempFile(
            $data['temp_file_path'],
            $data['original_filename'],
            $data['mime_type'],
        );

        try {
            $result = $this->orchestrator->publishDocumentWorkflow(
                procurementData: [
                    'pr_number' => $data['pr_number'],
                    'procurement_title' => $data['procurement_title'],
                    'user_address' => $data['user_address'],
                ],
                file: $file,
                documentData: [
                    'stage' => StageEnums::from($data['stage']),
                    'status' => $data['status'],
                    'document_type' => DocumentTypeEnums::from($data['document_type']),
                    'uploaded_by' => $data['uploaded_by'],
                    'description' => $data['description'] ?? null,
                    'stage_metadata' => $data['stage_metadata'] ?? [],
                ],
                statusData: [
                    'stage' => StageEnums::from($data['stage']),
                    'current_status' => StatusEnums::from($data['current_status']),
                    'metadata' => [
                        'documents_uploaded' => 1,
                        'uploaded_at' => now()->toIso8601String(),
                        'progressive_upload' => true,
                    ],
                ],
                eventData: [
                    'stage' => $data['stage'],
                    'event_type' => 'document_uploaded',
                    'category' => 'procurement',
                    'severity' => 'info',
                    'details' => sprintf(
                        'Document "%s" uploaded to stage "%s"',
                        DocumentTypeEnums::from($data['document_type'])->getDisplayName(),
                        StageEnums::from($data['stage'])->getDisplayName(),
                    ),
                    'document_count' => 1,
                ],
            );

            if (! $result['success']) {
                throw new Exception($result['error'] ?? 'Orchestrator returned failure');
            }

            return $result;
        } finally {
            $this->cleanupTempFile($data['temp_file_path']);
        }
    }
}
