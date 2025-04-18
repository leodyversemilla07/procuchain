<?php

namespace App\Handlers\SupplementalBidBulletin;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Handlers\BaseStageHandler;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SupplementalBidBulletinDocumentsHandler extends BaseStageHandler
{
    public function handle(Request $request): array
    {
        try {
            $data = $this->prepareHandlingData($request);

            // Prepare metadata for bulletin file
            $metadataArray = $this->prepareDocumentsMetadata($data);
            if (empty($metadataArray)) {
                return [
                    'success' => false,
                    'message' => 'No bulletin file uploaded',
                ];
            }
            return $this->processDocuments($data, $metadataArray);
        } catch (Exception $e) {
            Log::error('Error uploading supplemental bid bulletin', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Failed to upload supplemental bid bulletin: ' . $e->getMessage(),
            ];
        }
    }

    private function prepareHandlingData(Request $request): array
    {
        return [
            'procurementId' => $request->input('procurement_id'),
            'procurementTitle' => $request->input('procurement_title'),
            'bulletinFile' => $request->file('bulletin_file'),
            'bulletinNumber' => $request->input('bulletin_number'),
            'bulletinTitle' => $request->input('bulletin_title'),
            'issueDate' => $request->input('issue_date'),
            'timestamp' => now()->toIso8601String(),
            'userAddress' => $this->getUserBlockchainAddress(),
            'currentStage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN,
            'nextStage' => StageEnums::BID_OPENING,
            'completedStatus' => StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED,
            'status' => StatusEnums::SUPPLEMENTAL_BULLETINS_ONGOING,
        ];
    }

    // Prepare metadata array for the bulletin file
    private function prepareDocumentsMetadata(array $data): array
    {
        $metadataArray = [];
        if ($data['bulletinFile']) {
            $metadataArray = $this->uploadAndPrepareMetadata(
                [$data['bulletinFile']],
                [
                    [
                        'document_type' => 'Supplemental Bid Bulletin',
                        'bulletin_number' => $data['bulletinNumber'],
                        'bulletin_title' => $data['bulletinTitle'],
                        'issue_date' => $data['issueDate'],
                    ]
                ],
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getStoragePathSegment()
            );
        }
        return $metadataArray;
    }

    private function processDocuments(array $data, array $metadataArray): array
    {
        // Publish bulletin document
        $this->blockchainService->publishDocuments(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['status']->getDisplayName(),
            $metadataArray,
            $data['userAddress']
        );

        // Transition stage from ongoing to completed and proceed to Bid Opening
        $this->blockchainService->handleStageTransition(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['status']->getDisplayName(),
            $data['completedStatus']->getDisplayName(),
            $data['currentStage']->getDisplayName(),
            $data['nextStage']->getDisplayName(),
            $data['userAddress'],
            'Proceeding to ' . $data['nextStage']->getDisplayName() . ' after ' . $data['currentStage']->getDisplayName()
        );

        // Notify users of completion and next stage
        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['completedStatus']->getDisplayName(),
            $data['timestamp'],
            'completed',
            true,
            $data['nextStage']->getDisplayName()
        );

        return [
            'success' => true,
            'message' => $data['currentStage']->getDisplayName() . ' uploaded successfully. Proceeding to ' . $data['nextStage']->getDisplayName() . '.',
            'metadata' => $metadataArray[0],
        ];
    }
}
