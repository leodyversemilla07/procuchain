<?php

namespace App\Handlers\BidOpening;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Handlers\BaseStageHandler;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BidOpeningHandler extends BaseStageHandler
{
    /**
     * Handle the bid submission document upload process
     */
    public function handle(Request $request): array
    {
        try {
            $data = $this->prepareHandlingData($request);
            $metadataArray = $this->prepareBidDocumentsMetadata($data);

            if (count($metadataArray) > 0) {
                return $this->processBidDocuments($data, $metadataArray);
            } else {
                return [
                    'success' => false,
                    'message' => 'No valid bid documents were provided.',
                ];
            }
        } catch (Exception $e) {
            Log::error('Error in BidSubmissionHandler', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Failed to upload bid documents: '.$e->getMessage(),
            ];
        }
    }

    private function prepareHandlingData(Request $request): array
    {
        return [
            'procurementId' => $request->input('procurement_id'),
            'procurementTitle' => $request->input('procurement_title'),
            'bidDocuments' => $request->file('bid_documents', []),
            'biddersData' => $request->input('bidders_data', []),
            'openingDateTime' => $request->input('opening_date_time'),
            'timestamp' => now()->toIso8601String(),
            'userAddress' => $this->getUserBlockchainAddress(),
            'currentStage' => StageEnums::BID_OPENING,
            'nextStage' => StageEnums::BID_EVALUATION,
            'status' => StatusEnums::BIDS_OPENED,
        ];
    }

    private function prepareBidDocumentsMetadata(array $data): array
    {
        $metadataArray = [];

        foreach ($data['bidDocuments'] as $index => $file) {
            if ($file && isset($data['biddersData'][$index])) {
                $bidderName = $data['biddersData'][$index]['bidder_name'] ?? 'Unknown Bidder';
                $bidValue = $data['biddersData'][$index]['bid_value'] ?? '0';

                $metadataInfo = [
                    'document_type' => 'Bid Document',
                    'bidder_name' => $bidderName,
                    'bid_value' => $bidValue,
                    'opening_date_time' => $data['openingDateTime'],
                ];

                // Add bid document to metadata array
                $fileMetadata = $this->uploadAndPrepareMetadata(
                    [$file],
                    [$metadataInfo],
                    $data['procurementId'],
                    $data['procurementTitle'],
                    $data['currentStage']->getStoragePathSegment(),
                );

                $metadataArray = array_merge($metadataArray, $fileMetadata);
            }
        }

        return $metadataArray;
    }

    private function processBidDocuments(array $data, array $metadataArray): array
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
            'Proceeding to '.$data['nextStage']->getDisplayName().' stage after opening bids'
        );

        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['status']->getDisplayName(),
            $data['timestamp'],
            count($metadataArray),
            'opened',
            true,
            $data['nextStage']->getDisplayName()
        );

        return [
            'success' => true,
            'message' => count($metadataArray).' bid documents uploaded successfully. Proceeding to '.$data['nextStage']->getDisplayName().' stage.',
        ];
    }
}
