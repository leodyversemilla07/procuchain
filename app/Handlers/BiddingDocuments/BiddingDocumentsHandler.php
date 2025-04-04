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
            'metadata' => $request->input('metadata', []),
            'timestamp' => now()->toIso8601String(),
            'userAddress' => $this->getUserBlockchainAddress(),
            'stage' => StageEnums::BIDDING_DOCUMENTS,
            'status' => StatusEnums::BIDDING_DOCUMENTS_PUBLISHED,
        ];
    }

    private function prepareDocumentsMetadata(array $data): array
    {
        $metadataArray = [];

        if ($data['biddingDocumentsFile']) {
            $metadataArray = $this->uploadAndPrepareMetadata(
                [$data['biddingDocumentsFile']],
                [$data['metadata'] + ['document_type' => $data['stage']->getDisplayName()]],
                $data['procurementId'],
                $data['procurementTitle'],
                $data['stage']->getStoragePathSegment()
            );
        }

        return $metadataArray;
    }

    private function processDocuments(array $data, array $metadataArray): array
    {
        $this->blockchainService->publishDocuments(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['stage']->getDisplayName(),
            $data['status']->getDisplayName(),
            $metadataArray,
            $data['userAddress']
        );

        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['stage']->getDisplayName(),
            $data['status']->getDisplayName(),
            $data['timestamp'],
            count($metadataArray),
            'published'
        );

        return [
            'success' => true,
            'message' => $data['stage']->getDisplayName().' published successfully',
        ];
    }
}
