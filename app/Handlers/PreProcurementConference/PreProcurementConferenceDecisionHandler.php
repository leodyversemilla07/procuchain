<?php

namespace App\Handlers\PreProcurementConference;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Handlers\BaseStageHandler;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PreProcurementConferenceDecisionHandler extends BaseStageHandler
{
    public function handle(Request $request): array
    {
        try {
            $data = $this->prepareHandlingData($request);

            if ($data['conferenceHeld']) {
                return $this->handleConferenceHeld($data);
            } else {
                return $this->handleConferenceSkipped($data);
            }
        } catch (Exception $e) {
            Log::error('Error in PreProcurementDecisionHandler', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Failed to process '.StageEnums::PRE_PROCUREMENT_CONFERENCE->getDisplayName().' decision: '.$e->getMessage(),
            ];
        }
    }

    private function prepareHandlingData(Request $request): array
    {
        return [
            'procurementId' => $request->input('procurement_id'),
            'procurementTitle' => $request->input('procurement_title'),
            'conferenceHeld' => $request->boolean('conference_held'),
            'timestamp' => now()->toIso8601String(),
            'userAddress' => $this->getUserBlockchainAddress(),
            'currentStage' => StageEnums::PRE_PROCUREMENT_CONFERENCE,
            'initialStage' => StageEnums::PROCUREMENT_INITIATION,
            'nextStage' => StageEnums::BIDDING_DOCUMENTS,
        ];
    }

    private function handleConferenceHeld(array $data): array
    {
        $status = StatusEnums::PRE_PROCUREMENT_CONFERENCE_HELD;

        $this->blockchainService->updateStatus(
            $data['procurementId'],
            $data['procurementTitle'],
            $status->getDisplayName(),
            $data['currentStage']->getDisplayName(),
            $data['userAddress'],
            $data['timestamp']
        );

        $this->blockchainService->logEvent(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            'Pre-procurement conference held - documents pending',
            0,
            $data['userAddress'],
            'decision',
            'workflow',
            'info',
            $data['timestamp']
        );

        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $status->getDisplayName(),
            $data['timestamp'],
            0,
            'held'
        );

        return [
            'success' => true,
            'message' => $status->getDisplayName().'. Please upload documents.',
        ];
    }

    private function handleConferenceSkipped(array $data): array
    {
        $initialStatus = StatusEnums::PROCUREMENT_SUBMITTED;
        $status = StatusEnums::PRE_PROCUREMENT_CONFERENCE_SKIPPED;

        $this->blockchainService->handleStageTransition(
            $data['procurementId'],
            $data['procurementTitle'],
            $initialStatus->getDisplayName(),
            $status->getDisplayName(),
            $data['initialStage']->getDisplayName(),
            $data['nextStage']->getDisplayName(),
            $data['userAddress'],
            'Pre-procurement conference skipped'
        );

        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $status->getDisplayName(),
            $data['timestamp'],
            0,
            'skipped',
            true,
            $data['nextStage']->getDisplayName()
        );

        return [
            'success' => true,
            'message' => $status->getDisplayName().
                '. Proceeding to '.$data['nextStage']->getDisplayName().'.',
        ];
    }
}
