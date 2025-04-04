<?php

namespace App\Handlers\BacResolution;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Handlers\BaseStageHandler;

class BacResolutionHandler extends BaseStageHandler
{
    /**
     * Handle the BAC resolution document upload process.
     */
    public function handle(Request $request): array
    {
        try {
            $data = $this->prepareHandlingData($request);
            $metadataArray = $this->prepareDocumentsMetadata($data);
            return $this->processDocuments($data, $metadataArray);
        } catch (Exception $e) {
            Log::error('Error in BacResolutionHandler', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Failed to upload ' . StageEnums::BAC_RESOLUTION->getDisplayName() . ' document: ' . $e->getMessage()];
        }
    }
    
    private function prepareHandlingData(Request $request): array
    {
        return [
            'procurementId' => $request->input('procurement_id'),
            'procurementTitle' => $request->input('procurement_title'),
            'bacResolutionFile' => $request->file('bac_resolution_file'),
            'issuanceDate' => $request->input('issuance_date'),
            'signatoryDetails' => $request->input('signatory_details'),
            'timestamp' => now()->toIso8601String(),
            'userAddress' => $this->getUserBlockchainAddress(),
            'currentStage' => StageEnums::BAC_RESOLUTION,
            'nextStage' => StageEnums::NOTICE_OF_AWARD,
            'status' => StatusEnums::RESOLUTION_RECORDED
        ];
    }
    
    private function prepareDocumentsMetadata(array $data): array
    {
        $metadataArray = [];
        
        if ($data['bacResolutionFile']) {
            $metadataArray = array_merge($metadataArray, $this->uploadAndPrepareMetadata(
                [$data['bacResolutionFile']],
                [['document_type' => 'BAC Resolution', 'issuance_date' => $data['issuanceDate'], 'signatory_details' => $data['signatoryDetails']]],
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

        $this->blockchainService->handleStageTransition(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['status']->getDisplayName(),
            $data['status']->getDisplayName(),
            $data['currentStage']->getDisplayName(),
            $data['nextStage']->getDisplayName(),
            $data['userAddress'],
            'Proceeding to ' . $data['nextStage']->getDisplayName() . ' after recording ' . $data['currentStage']->getDisplayName()
        );

        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['status']->getDisplayName(),
            $data['timestamp'],
            count($metadataArray),
            'recorded',
            true,
            $data['nextStage']->getDisplayName()
        );

        return [
            'success' => true,
            'message' => $data['currentStage']->getDisplayName() . ' document uploaded successfully. Proceeding to ' . $data['nextStage']->getDisplayName() . '.'
        ];
    }
}
