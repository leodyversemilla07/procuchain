<?php

namespace App\Handlers\Monitoring;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Handlers\BaseStageHandler;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MonitoringHandler extends BaseStageHandler
{
    /**
     * Handle the monitoring document upload process.
     */
    public function handle(Request $request): array
    {
        try {
            $data = $this->prepareHandlingData($request);
            $metadataArray = $this->prepareDocumentsMetadata($data);

            return $this->processDocuments($data, $metadataArray);
        } catch (Exception $e) {
            Log::error('Error in MonitoringHandler', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Failed to upload compliance report: '.$e->getMessage()];
        }
    }

    private function prepareHandlingData(Request $request): array
    {
        return [
            'procurementId' => $request->input('procurement_id'),
            'procurementTitle' => $request->input('procurement_title'),
            'complianceFile' => $request->file('compliance_file'),
            'reportDate' => $request->input('report_date'),
            'reportNotes' => $request->input('report_notes'),
            'timestamp' => now()->toIso8601String(),
            'userAddress' => $this->getUserBlockchainAddress(),
            'currentStage' => StageEnums::MONITORING,
            'status' => StatusEnums::MONITORING,
        ];
    }

    private function prepareDocumentsMetadata(array $data): array
    {
        $metadataArray = [];

        if ($data['complianceFile']) {
            $metadataArray = array_merge($metadataArray, $this->uploadAndPrepareMetadata(
                [$data['complianceFile']],
                [['document_type' => 'Compliance Report', 'report_date' => $data['reportDate'], 'report_notes' => $data['reportNotes']]],
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getStoragePathSegment()
            ));
        }

        return $metadataArray;
    }

    private function processDocuments(array $data, array $metadataArray): array
    {
        $this->blockchainService->publishDocuments(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['status']->getDisplayName(),
            $metadataArray,
            $data['userAddress']
        );

        // Unlike other handlers, this one doesn't transition to a next stage
        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['status']->getDisplayName(),
            $data['timestamp'],
            count($metadataArray),
            'uploaded',
            false,
            ''
        );

        return [
            'success' => true,
            'message' => 'Compliance report uploaded successfully. Notifications sent to BAC Chairman and HOPE.',
        ];
    }
}
