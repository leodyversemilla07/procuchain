<?php

namespace App\Handlers\Monitoring;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Handlers\BaseStageHandler;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MonitoringDocumentHandler extends BaseStageHandler
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
            'status' => StatusEnums::MONITORING_COMPLETED, // Status for the monitoring document upload itself
            'nextStage' => StageEnums::COMPLETION, // Define the next stage
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
        // Publish the monitoring documents
        $this->blockchainService->publishDocuments(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['status']->getDisplayName(), // MONITORING_COMPLETED
            $metadataArray,
            $data['userAddress']
        );

        // --- Handle Transition to Completion Stage ---
        $transitionTimestamp = now()->toIso8601String();
        $transitionStatus = StatusEnums::COMPLETED; // Status for the completion stage

        // Handle the stage transition on the blockchain
        $this->blockchainService->handleStageTransition(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['status']->getDisplayName(), // Status before transition (MONITORING_COMPLETED)
            $transitionStatus->getDisplayName(), // Status after transition (COMPLETED)
            $data['currentStage']->getDisplayName(),
            $data['nextStage']->getDisplayName(),
            $data['userAddress'],
            'Transitioning to '.$data['nextStage']->getDisplayName().' after recording '.$data['currentStage']->getDisplayName().' documents.'
        );

        // Notify about the transition to the Completion stage
        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(), // Stage transitioned FROM
            $transitionStatus->getDisplayName(), // Status transitioned TO (COMPLETED)
            $transitionTimestamp,
            'transitioned',
            count($metadataArray), // Number of docs from the *previous* step
            true, // This IS a transition notification
            $data['nextStage']->getDisplayName() // Specify the next stage name
        );

        return [
            'success' => true,
            'message' => $data['currentStage']->getDisplayName().' documents uploaded and process transitioned to '.$data['nextStage']->getDisplayName().' stage successfully.',
        ];
    }
}
