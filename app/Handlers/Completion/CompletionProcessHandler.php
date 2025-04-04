<?php

namespace App\Handlers\Completion;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Handlers\BaseStageHandler;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CompletionProcessHandler extends BaseStageHandler
{
    public function handle(Request $request): array
    {
        try {
            $data = $this->prepareHandlingData($request);

            return $this->completeProcurement($data);
        } catch (Exception $e) {
            Log::error('Error in CompleteProcessHandler', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Failed to mark procurement as complete: '.$e->getMessage()];
        }
    }

    private function prepareHandlingData(Request $request): array
    {
        return [
            'procurementId' => $request->input('procurement_id'),
            'procurementTitle' => $request->input('procurement_title'),
            'remarks' => $request->input('remarks'),
            'timestamp' => now()->toIso8601String(),
            'userAddress' => $this->getUserBlockchainAddress(),
            'currentStage' => StageEnums::COMPLETED,
            'status' => StatusEnums::COMPLETED,
        ];
    }

    private function completeProcurement(array $data): array
    {
        $this->blockchainService->updateStatus(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['status']->getDisplayName(),
            $data['userAddress'],
            $data['timestamp']
        );

        $this->blockchainService->logEvent(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['remarks'],
            0,
            $data['userAddress'],
            'Procurement Completed',
            'workflow',
            'info',
            $data['timestamp']
        );

        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['status']->getDisplayName(),
            $data['timestamp'],
            0,
            $data['currentStage']->value
        );

        return ['success' => true, 'message' => 'Procurement successfully marked as completed.'];
    }
}
