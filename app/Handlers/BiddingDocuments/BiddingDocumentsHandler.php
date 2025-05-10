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
                    'end_date' => $data['validityPeriodEnd'],
                ],
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
        // First publish documents with the published status
        $this->blockchainService->publishDocuments(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['status']->getDisplayName(),
            $metadataArray,
            $data['userAddress']
        );

        // Then handle stage transition - this is crucial for advancing stages!
        $this->blockchainService->handleStageTransition(
            $data['procurementId'],
            $data['procurementTitle'],
            StatusEnums::PRE_PROCUREMENT_CONFERENCE_COMPLETED->getDisplayName(), // From PRE_PROCUREMENT_CONFERENCE_COMPLETED
            $data['status']->getDisplayName(),                                    // To BIDDING_DOCUMENTS_PUBLISHED
            $data['currentStage']->getDisplayName(),                              // From BIDDING_DOCUMENTS
            $data['nextStage']->getDisplayName(),                                 // To PRE_BID_CONFERENCE
            $data['userAddress'],
            'Proceeding to '.$data['nextStage']->getDisplayName().' after publishing bidding documents'
        );

        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['status']->getDisplayName(),
            $data['timestamp'],
            'published',
            true,
            $data['nextStage']->getDisplayName()
        );

        return [
            'success' => true,
            'message' => $data['currentStage']->getDisplayName().' published successfully. Proceeding to '.$data['nextStage']->getDisplayName().'.',
        ];
    }
}
