<?php

namespace App\Handlers\NoticeOfAward;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Handlers\BaseStageHandler;

class NoticeOfAwardHandler extends BaseStageHandler
{
    /**
     * Handle the Notice of Award document upload process.
     */
    public function handle(Request $request): array
    {
        try {
            $data = $this->prepareHandlingData($request);
            $metadataArray = $this->prepareDocumentsMetadata($data);
            return $this->processDocuments($data, $metadataArray);
        } catch (Exception $e) {
            Log::error('Error in NoticeOfAwardHandler', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Failed to upload ' . StageEnums::NOTICE_OF_AWARD->getDisplayName() . ' document: ' . $e->getMessage()];
        }
    }
    
    private function prepareHandlingData(Request $request): array
    {
        return [
            'procurementId' => $request->input('procurement_id'),
            'procurementTitle' => $request->input('procurement_title'),
            'noaFile' => $request->file('noa_file'),
            'issuanceDate' => $request->input('issuance_date'),
            'signatoryDetails' => $request->input('signatory_details'),
            'timestamp' => now()->toIso8601String(),
            'userAddress' => $this->getUserBlockchainAddress(),
            'currentStage' => StageEnums::NOTICE_OF_AWARD,
            'nextStage' => StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
            'status' => StatusEnums::AWARDED
        ];
    }
    
    private function prepareDocumentsMetadata(array $data): array
    {
        $metadataArray = [];
        
        if ($data['noaFile']) {
            $metadataArray = array_merge($metadataArray, $this->uploadAndPrepareMetadata(
                [$data['noaFile']],
                [['document_type' => 'Notice of Award', 'issuance_date' => $data['issuanceDate'], 'signatory_details' => $data['signatoryDetails']]],
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
        
        // Log publication to PhilGEPS
        $publicationTimestamp = now()->addSecond()->toIso8601String();
        $this->blockchainService->logEvent(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            'Published Notice of Award to PhilGEPS',
            1,
            $data['userAddress'],
            'publication',
            'workflow',
            'info',
            $publicationTimestamp
        );

        $this->blockchainService->handleStageTransition(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['status']->getDisplayName(),
            $data['status']->getDisplayName(),
            $data['currentStage']->getDisplayName(),
            $data['nextStage']->getDisplayName(),
            $data['userAddress'],
            'Proceeding to ' . $data['nextStage']->getDisplayName() . ' stage after recording ' . $data['currentStage']->getDisplayName()
        );

        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['status']->getDisplayName(),
            $data['timestamp'],
            count($metadataArray),
            'awarded',
            true,
            $data['nextStage']->getDisplayName()
        );

        return [
            'success' => true,
            'message' => $data['currentStage']->getDisplayName() . ' document uploaded and published successfully. Proceeding to ' . $data['nextStage']->getDisplayName() . ' stage.'
        ];
    }
}
