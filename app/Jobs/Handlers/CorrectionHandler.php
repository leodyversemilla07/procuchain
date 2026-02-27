<?php

declare(strict_types=1);

namespace App\Jobs\Handlers;

use App\DataTransferObjects\ProcurementData;
use App\Jobs\Handlers\Concerns\HandlesTempFiles;
use App\Services\Publishers\CorrectionPublisher;
use App\Services\Publishers\ProcurementCorrectionPublisher;

class CorrectionHandler
{
    use HandlesTempFiles;

    public function __construct(
        private readonly CorrectionPublisher $correctionPublisher,
        private readonly ProcurementCorrectionPublisher $procurementCorrectionPublisher,
    ) {}

    public function executeDocumentCorrection(array $data): array
    {
        $correctedFile = null;

        if (! empty($data['temp_file_path'])) {
            $correctedFile = $this->reconstituteTempFile(
                $data['temp_file_path'],
                $data['original_filename'],
                $data['mime_type'],
            );
        }

        try {
            return $this->correctionPublisher->publish(
                prNumber: $data['pr_number'],
                procurementTitle: $data['procurement_title'],
                originalTxid: $data['original_txid'],
                originalDocumentHash: $data['original_document_hash'],
                correctionType: $data['correction_type'],
                action: $data['action'],
                reason: $data['reason'],
                correctedBy: $data['corrected_by'],
                userAddress: $data['user_address'],
                correctedFile: $correctedFile,
                originalStage: $data['original_stage'] ?? null,
            );
        } finally {
            if (! empty($data['temp_file_path'])) {
                $this->cleanupTempFile($data['temp_file_path']);
            }
        }
    }

    public function executeProcurementCorrection(array $data): array
    {
        $originalProcurement = ProcurementData::fromArray($data['original_procurement']);

        return $this->procurementCorrectionPublisher->publishCorrection(
            originalProcurement: $originalProcurement,
            correctedData: $data['corrected_data'],
            reason: $data['reason'],
            correctedBy: $data['corrected_by'],
            userAddress: $data['user_address'],
        );
    }
}
