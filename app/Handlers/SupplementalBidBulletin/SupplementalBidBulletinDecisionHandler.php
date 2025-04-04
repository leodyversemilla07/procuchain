<?php

namespace App\Handlers\SupplementalBidBulletin;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Handlers\BaseStageHandler;

class SupplementalBidBulletinDecisionHandler extends BaseStageHandler
{    
    /**
     * Handle supplemental bid bulletin completion decision.
     * 
     * @param Request $request
     * @return array
     */
    public function handle(Request $request): array
    {
        try {
            $data = $this->prepareHandlingData($request);
            
            if ($data['hasMoreBulletins']) {
                return $this->handleMoreBulletins($data);
            } else {
                return $this->handleBulletinsCompleted($data);
            }
        } catch (Exception $e) {
            Log::error('Error completing supplemental bid bulletins', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Failed to process supplemental bid bulletin decision: ' . $e->getMessage()
            ];
        }
    }
    
    private function prepareHandlingData(Request $request): array
    {
        return [
            'procurementId' => $request->input('procurement_id'),
            'procurementTitle' => $request->input('procurement_title'),
            'hasMoreBulletins' => $request->boolean('has_more_bulletins', false),
            'timestamp' => now()->toIso8601String(),
            'userAddress' => $this->getUserBlockchainAddress(),
            'currentStage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN,
            'nextStage' => StageEnums::BID_OPENING,
        ];
    }
    
    private function handleMoreBulletins(array $data): array
    {
        $status = StatusEnums::SUPPLEMENTAL_BULLETINS_ONGOING;
        
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
            'Additional supplemental bid bulletins to be issued',
            0,
            $data['userAddress'],
            'decision',
            'workflow',
            'info',
            $data['timestamp']
        );
        
        return [
            'success' => true,
            'message' => 'Please upload additional supplemental bid bulletins.'
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
            $data['nextStage']->getDisplayName(),
            $data['userAddress'],
            'All supplemental bid bulletins issued'
        );
        
        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $status->getDisplayName(),
            $data['timestamp'],
            0,
            'completed',
            true,
            $data['nextStage']->getDisplayName()
        );
        
        return [
            'success' => true,
            'message' => $status->getDisplayName() . 
                '. Proceeding to ' . $data['nextStage']->getDisplayName() . '.'
        ];
    }
}
