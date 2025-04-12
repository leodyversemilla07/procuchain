<?php

namespace App\Handlers\SupplementalBidBulletin;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Handlers\BaseStageHandler;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SupplementalBidBulletinDecisionHandler extends BaseStageHandler
{
    /**
     * Handle supplemental bid bulletin completion decision.
     */
    public function handle(Request $request): array
    {
        try {
            $data = $this->prepareHandlingData($request);

            if ($data['supplementalBidNeeded']) {
                return $this->handleMoreBulletins($data);
            } else {
                return $this->handleBulletinsCompleted($data);
            }
        } catch (Exception $e) {
            Log::error('Error in SupplementalBidBulletinDecisionHandler', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Failed to process '.StageEnums::SUPPLEMENTAL_BID_BULLETIN->getDisplayName().' decision: '.$e->getMessage(),
            ];
        }
    }

    private function prepareHandlingData(Request $request): array
    {
        return [
            'procurementId' => $request->input('procurement_id'),
            'procurementTitle' => $request->input('procurement_title'),
            'supplementalBidNeeded' => $request->boolean('supplemental_bid_needed', false),
            'timestamp' => now()->toIso8601String(),
            'userAddress' => $this->getUserBlockchainAddress(),
            'currentStage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN,
            'nextStage' => StageEnums::BID_OPENING,
        ];
    }

    private function handleMoreBulletins(array $data): array
    {
        $status = StatusEnums::SUPPLEMENTAL_BULLETINS_ONGOING;

        $this->blockchainService->handleStageTransition(
            $data['procurementId'],
            $data['procurementTitle'],
            $status->getDisplayName(),
            $status->getDisplayName(),
            $data['currentStage']->getDisplayName(),
            $data['currentStage']->getDisplayName(), // Stay in supplemental bid bulletin stage
            $data['userAddress'],
            'Additional supplemental bid bulletins required'
        );

        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $status->getDisplayName(),
            $data['timestamp'],
            'more_bulletins_required', // Action type as string
            false, // No stage transition
            '' // No next stage
        );

        return [
            'success' => true,
            'message' => $status->getDisplayName().'. Additional supplemental bid bulletins are required.',
        ];
    }

    private function handleBulletinsCompleted(array $data): array
    {
        $status = StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED;

        $this->blockchainService->handleStageTransition(
            $data['procurementId'],
            $data['procurementTitle'],
            $status->getDisplayName(),
            $status->getDisplayName(),
            $data['currentStage']->getDisplayName(),
            $data['nextStage']->getDisplayName(), // Move to bid opening
            $data['userAddress'],
            'No additional supplemental bid bulletins needed'
        );

        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $status->getDisplayName(),
            $data['timestamp'],
            'bulletins_completed', // Action type as string
            true, // Stage transition occurring
            $data['nextStage']->getDisplayName() // Next stage specified
        );

        return [
            'success' => true,
            'message' => $status->getDisplayName().'. Proceeding to '.$data['nextStage']->getDisplayName().'.',
        ];
    }
}
