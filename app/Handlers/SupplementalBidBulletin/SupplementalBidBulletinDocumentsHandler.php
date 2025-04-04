<?php

namespace App\Handlers\SupplementalBidBulletin;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Handlers\BaseStageHandler;

class SupplementalBidBulletinDocumentsHandler extends BaseStageHandler
{
    public function handle(Request $request): array
    {
        try {
            $data = $this->prepareHandlingData($request);
            
            if (!$data['bulletinFile']) {
                return [
                    'success' => false,
                    'message' => 'No bulletin file uploaded'
                ];
            }

            return $this->processUpload($data);
        } catch (Exception $e) {
            Log::error('Error uploading supplemental bid bulletin', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Failed to upload supplemental bid bulletin: ' . $e->getMessage()
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
            'status' => StatusEnums::SUPPLEMENTAL_BULLETINS_ONGOING
        ];
    }
    
    private function processUpload(array $data): array
    {
        $metadataArray = $this->uploadAndPrepareMetadata(
            [$data['bulletinFile']],
            [[
                'document_type' => 'Supplemental Bid Bulletin',
                'bulletin_number' => $data['bulletinNumber'],
                'bulletin_title' => $data['bulletinTitle'],
                'issue_date' => $data['issueDate']
            ]],
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getStoragePathSegment()
        );

        $this->blockchainService->publishDocuments(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['status']->getDisplayName(),
            $metadataArray,
            $data['userAddress']
        );

        $this->blockchainService->updateStatus(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['status']->getDisplayName(),
            $data['currentStage']->getDisplayName(),
            $data['userAddress'],
            $data['timestamp']
        );

        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['status']->getDisplayName(),
            $data['timestamp'],
            count($metadataArray),
            'document_uploaded'
        );

        return [
            'success' => true,
            'message' => 'Supplemental Bid Bulletin #' . $data['bulletinNumber'] . ' uploaded successfully',
            'metadata' => $metadataArray[0]
        ];
    }
}
