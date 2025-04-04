<?php

namespace App\Handlers\PreBidConference;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Handlers\BaseStageHandler;

class PreBidConferenceDecisionHandler extends BaseStageHandler
{
    /**
     * Handle pre-bid conference decision.
     * 
     * @param Request $request
     * @return array
     */
    public function handle(Request $request): array
    {
        try {
            $data = $this->prepareHandlingData($request);
            
            if ($data['conferenceHeld']) {
                if ($data['needsBulletins']) {
                    return $this->handleConferenceWithBulletins($data);
                } else {
                    return $this->handleConferenceWithoutBulletins($data);
                }
            } else {
                return $this->handleConferenceSkipped($data);
            }
        } catch (Exception $e) {
            Log::error('Error in PreBidConferenceHandler', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Failed to process ' . StageEnums::BIDDING_DOCUMENTS->getDisplayName() . ' decision: ' . $e->getMessage()
            ];
        }
    }
    
    private function prepareHandlingData(Request $request): array
    {
        return [
            'procurementId' => $request->input('procurement_id'),
            'procurementTitle' => $request->input('procurement_title'),
            'conferenceHeld' => $request->boolean('conference_held'),
            'needsBulletins' => $request->boolean('needs_bulletins', false),
            'timestamp' => now()->toIso8601String(),
            'userAddress' => $this->getUserBlockchainAddress(),
            'currentStage' => StageEnums::BIDDING_DOCUMENTS,
            'bulletinsStage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN,
            'bidOpeningStage' => StageEnums::BID_OPENING,
        ];
    }
    
    private function handleConferenceWithBulletins(array $data): array
    {
        $status = StatusEnums::PRE_BID_CONFERENCE_HELD;

        $this->blockchainService->handleStageTransition(
            $data['procurementId'],
            $data['procurementTitle'],
            $status->getDisplayName(),
            $status->getDisplayName(),
            $data['currentStage']->getDisplayName(),
            $data['bulletinsStage']->getDisplayName(),
            $data['userAddress'],
            'Pre-bid conference held - supplemental bulletins needed'
        );

        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $status->getDisplayName(),
            $data['timestamp'],
            0,
            'held',
            true,
            $data['bulletinsStage']->getDisplayName()
        );

        return [
            'success' => true,
            'message' => $status->getDisplayName() .
                '. Proceeding to ' . $data['bulletinsStage']->getDisplayName() . '.'
        ];
    }
    
    private function handleConferenceWithoutBulletins(array $data): array
    {
        $status = StatusEnums::PRE_BID_CONFERENCE_HELD;

        $this->blockchainService->handleStageTransition(
            $data['procurementId'],
            $data['procurementTitle'],
            $status->getDisplayName(),
            $status->getDisplayName(),
            $data['currentStage']->getDisplayName(),
            $data['bidOpeningStage']->getDisplayName(),
            $data['userAddress'],
            'Pre-bid conference held - no supplemental bulletins needed'
        );

        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $status->getDisplayName(),
            $data['timestamp'],
            0,
            'held',
            true,
            $data['bidOpeningStage']->getDisplayName()
        );

        return [
            'success' => true,
            'message' => $status->getDisplayName() .
                '. Proceeding directly to ' . $data['bidOpeningStage']->getDisplayName() . '.'
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
            $data['bidOpeningStage']->getDisplayName(),
            $data['userAddress'],
            'Pre-bid conference skipped'
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
            $data['bidOpeningStage']->getDisplayName()
        );

        return [
            'success' => true,
            'message' => $status->getDisplayName() .
                '. Proceeding to ' . $data['bidOpeningStage']->getDisplayName() . '.'
        ];
    }
}
