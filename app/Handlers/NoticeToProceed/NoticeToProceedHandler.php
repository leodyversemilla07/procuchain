<?php

namespace App\Handlers\NoticeToProceed;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Handlers\BaseStageHandler;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NoticeToProceedHandler extends BaseStageHandler
{
    /**
     * Handle the Notice to Proceed document upload process.
     */
    public function handle(Request $request): array
    {
        try {
            $data = $this->prepareHandlingData($request);
            $metadataArray = $this->prepareDocumentsMetadata($data);

            return $this->processDocuments($data, $metadataArray);
        } catch (Exception $e) {
            Log::error('Error in NoticeToProceedHandler', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Failed to upload '.StageEnums::NOTICE_TO_PROCEED->getDisplayName().' document: '.$e->getMessage()];
        }
    }

    private function prepareHandlingData(Request $request): array
    {
        return [
            'procurementId' => $request->input('procurement_id'),
            'procurementTitle' => $request->input('procurement_title'),
            'ntpFile' => $request->file('ntp_file'),
            'issuanceDate' => $request->input('issuance_date'),
            'timestamp' => now()->toIso8601String(),
            'userAddress' => $this->getUserBlockchainAddress(),
            'currentStage' => StageEnums::NOTICE_TO_PROCEED,
            'nextStage' => StageEnums::MONITORING,
            'status' => StatusEnums::NTP_RECORDED,
        ];
    }

    private function prepareDocumentsMetadata(array $data): array
    {
        $metadataArray = [];

        if ($data['ntpFile']) {
            $metadataArray = array_merge($metadataArray, $this->uploadAndPrepareMetadata(
                [$data['ntpFile']],
                [['document_type' => 'Notice to Proceed', 'issuance_date' => $data['issuanceDate']]],
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
            'Published NTP to PhilGEPS',
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
            'Proceeding to '.$data['nextStage']->getDisplayName().' stage after recording '.$data['currentStage']->getDisplayName()
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
            'message' => $data['currentStage']->getDisplayName().' document uploaded and published successfully. Proceeding to '.$data['nextStage']->getDisplayName().' stage.',
        ];
    }
}
