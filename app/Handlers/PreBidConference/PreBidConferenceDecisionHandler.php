<?php

namespace App\Handlers\PreBidConference;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Handlers\BaseStageHandler;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PreBidConferenceDecisionHandler extends BaseStageHandler
{
    /**
     * Handle pre-bid conference decision.
     */
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
            Log::error('Error in PreBidConferenceDecisionHandler', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Failed to process '.StageEnums::PRE_BID_CONFERENCE->getDisplayName().' decision: '.$e->getMessage(),
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
            'currentStage' => StageEnums::PRE_BID_CONFERENCE,
            'nextStage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN,
        ];
    }

    private function handleConferenceHeld(array $data): array
    {
        $status = StatusEnums::PRE_BID_CONFERENCE_HELD;

        $this->blockchainService->handleStageTransition(
            $data['procurementId'],
            $data['procurementTitle'],
            $status->getDisplayName(),
            $status->getDisplayName(),
            $data['currentStage']->getDisplayName(),
            $data['currentStage']->getDisplayName(), // Stay in pre-bid conference
            $data['userAddress'],
            'Pre-bid conference held'
        );

        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $status->getDisplayName(),
            $data['timestamp'],
            'conference_held', // Action type as string
            false, // No stage transition
            '' // No next stage
        );

        return [
            'success' => true,
            'message' => $status->getDisplayName().'. Pre-bid conference is in progress.',
        ];
    }

    private function handleConferenceSkipped(array $data): array
    {
        $status = StatusEnums::PRE_BID_CONFERENCE_SKIPPED;

        $this->blockchainService->handleStageTransition(
            $data['procurementId'],
            $data['procurementTitle'],
            $status->getDisplayName(),
            $status->getDisplayName(),
            $data['currentStage']->getDisplayName(),
            $data['nextStage']->getDisplayName(), // Move to supplemental bid bulletin
            $data['userAddress'],
            'Pre-bid conference skipped'
        );

        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $status->getDisplayName(),
            $data['timestamp'],
            'conference_skipped', // Action type as string
            true, // Stage transition occurring
            $data['nextStage']->getDisplayName() // Next stage specified
        );

        return [
            'success' => true,
            'message' => $status->getDisplayName().'. Proceeding to '.$data['nextStage']->getDisplayName().'.',
        ];
    }
}
