<?php

namespace App\Handlers\ProcurementInitiation;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Handlers\BaseStageHandler;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProcurementInitiationHandler extends BaseStageHandler
{
    public function handle(Request $request): array
    {
        $data = [];
        try {
            $data = $this->prepareHandlingData($request);

            return $this->processDocuments($data);
        } catch (Exception $e) {
            Log::error('Error in ProcurementInitiationHandler', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Failed to publish '.($data['stage'] ?? StageEnums::PROCUREMENT_INITIATION)->getDisplayName().' documents: '.$e->getMessage()];
        }
    }

    private function prepareHandlingData(Request $request): array
    {
        return [
            'procurementId' => $request->input('procurement_id'),
            'procurementTitle' => $request->input('procurement_title'),
            'files' => $request->file('files', []),
            'metadata' => $request->input('metadata', []),
            'timestamp' => now()->toIso8601String(),
            'userAddress' => $this->getUserBlockchainAddress(),
            'stage' => StageEnums::PROCUREMENT_INITIATION,
            'status' => StatusEnums::PROCUREMENT_SUBMITTED,
        ];
    }

    private function processDocuments(array $data): array
    {
        $metadataArray = [];

        if (! empty($data['files'])) {
            $uploadedMetadataArray = $this->uploadAndPrepareMetadata(
                $data['files'],
                $data['metadata'],
                $data['procurementId'],
                $data['procurementTitle'],
                $data['stage']->getStoragePathSegment()
            );
            $metadataArray = array_merge($metadataArray, $uploadedMetadataArray);
        }

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
            'submitted'
        );

        return [
            'success' => true,
            'message' => $data['stage']->getDisplayName().' documents published successfully',
        ];
    }
}
