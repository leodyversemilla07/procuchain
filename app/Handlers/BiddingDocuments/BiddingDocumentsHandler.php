<?php

namespace App\Handlers\BiddingDocuments;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Handlers\BaseStageHandler;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BiddingDocumentsHandler extends BaseStageHandler
{
    public function handle(Request $request): array
    {
        try {
            $data = $this->prepareHandlingData($request);
            $metadataArray = $this->prepareDocumentsMetadata($data);

            return $this->processDocuments($data, $metadataArray);
        } catch (Exception $e) {
            Log::error('Error in BiddingDocumentsHandler', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Failed to publish '.StageEnums::BIDDING_DOCUMENTS->getDisplayName().': '.$e->getMessage()];
        }
    }

    private function prepareHandlingData(Request $request): array
    {
        return [
            'procurementId' => $request->input('procurement_id'),
            'procurementTitle' => $request->input('procurement_title'),
            'biddingDocumentsFile' => $request->file('bidding_documents_file'),
            'issuanceDate' => $request->input('issuance_date'),
            'validityPeriodStart' => $request->input('validity_period_start'),
            'validityPeriodEnd' => $request->input('validity_period_end'),
            'metadata' => $request->input('metadata', []),
            'timestamp' => now()->toIso8601String(),
            'userAddress' => $this->getUserBlockchainAddress(),
            'currentStage' => StageEnums::BIDDING_DOCUMENTS,
            'nextStage' => StageEnums::PRE_BID_CONFERENCE,
            'status' => StatusEnums::BIDDING_DOCUMENTS_PUBLISHED,
        ];
    }

    private function prepareDocumentsMetadata(array $data): array
    {
        $metadataArray = [];

        if ($data['biddingDocumentsFile']) {
            $baseMetadata = [
                'document_type' => $data['currentStage']->getDisplayName(),
                'issuance_date' => $data['issuanceDate'],
                'validity_period' => [
                    'start_date' => $data['validityPeriodStart'],
                    'end_date' => $data['validityPeriodEnd']
                ]
            ];
            
            $metadataArray = $this->uploadAndPrepareMetadata(
                [$data['biddingDocumentsFile']],
                [$data['metadata'] + $baseMetadata],
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getStoragePathSegment()
            );
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

        // Handle stage transition
        $this->blockchainService->handleStageTransition(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['status']->getDisplayName(),
            $data['status']->getDisplayName(),
            $data['currentStage']->getDisplayName(),
            $data['nextStage']->getDisplayName(),
            $data['userAddress'],
            'Proceeding to ' . $data['nextStage']->getDisplayName() . ' after publishing bidding documents'
        );

        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['status']->getDisplayName(),
            $data['timestamp'],
            count($metadataArray),
            'published',
            true,
            $data['nextStage']->getDisplayName()
        );

        return [
            'success' => true,
            'message' => $data['currentStage']->getDisplayName() . ' published successfully. Proceeding to ' . $data['nextStage']->getDisplayName() . '.',
        ];
    }
}
