<?php

namespace App\Handlers\PreBidConference;

use App\Enums\ProcurementStage;
use App\Enums\ProcurementStatus;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Handlers\BaseStageHandler;

class PreBidConferenceDocumentsHandler extends BaseStageHandler
{
    public function handle(Request $request): array
    {
        try {
            $data = $this->prepareHandlingData($request);
            $metadataArray = $this->prepareDocumentsMetadata($data);
            return $this->processDocuments($data, $metadataArray);
        } catch (Exception $e) {
            Log::error('Error in UploadPreBidDocumentsHandler', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Failed to upload pre-bid conference documents: ' . $e->getMessage()];
        }
    }
    
    private function prepareHandlingData(Request $request): array
    {
        return [
            'procurementId' => $request->input('procurement_id'),
            'procurementTitle' => $request->input('procurement_title'),
            'minutesFile' => $request->file('minutes_file'),
            'attendanceFile' => $request->file('attendance_file'),
            'meetingDate' => $request->input('meeting_date'),
            'participants' => $request->input('participants'),
            'needsBulletins' => $request->boolean('needs_bulletins', false),
            'timestamp' => now()->toIso8601String(),
            'userAddress' => $this->getUserBlockchainAddress(),
            'currentStage' => StageEnums::BIDDING_DOCUMENTS,
            'bulletinsStage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN,
            'bidOpeningStage' => StageEnums::BID_OPENING,
            'status' => StatusEnums::PRE_BID_CONFERENCE_HELD
        ];
    }
    
    private function prepareDocumentsMetadata(array $data): array
    {
        $metadataArray = [];
        
        if ($data['minutesFile']) {
            $metadataArray = array_merge($metadataArray, $this->uploadAndPrepareMetadata(
                [$data['minutesFile']],
                [['document_type' => 'Pre-Bid Minutes', 'meeting_date' => $data['meetingDate'], 'participants' => $data['participants']]],
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getStoragePathSegment()
            ));
        }

        if ($data['attendanceFile']) {
            $metadataArray = array_merge($metadataArray, $this->uploadAndPrepareMetadata(
                [$data['attendanceFile']],
                [['document_type' => 'Pre-Bid Attendance', 'meeting_date' => $data['meetingDate'], 'participants' => $data['participants']]],
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getStoragePathSegment()
            ));
        }
        
        return $metadataArray;
    }
    
    private function processDocuments(array $data, array $metadataArray): array
    {
        $this->blockchainService->publishDocuments(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['status']->getDisplayName(),
            $metadataArray,
            $data['userAddress']
        );

        // Determine next stage based on whether bulletins are needed
        $nextStage = $data['needsBulletins'] ? $data['bulletinsStage'] : $data['bidOpeningStage'];
        $transitionMessage = $data['needsBulletins']
            ? 'Pre-bid conference held - supplemental bulletins needed'
            : 'Pre-bid conference held - proceeding to bid opening';

        $this->blockchainService->handleStageTransition(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['status']->getDisplayName(),
            $data['status']->getDisplayName(),
            $data['currentStage']->getDisplayName(),
            $nextStage->getDisplayName(),
            $data['userAddress'],
            $transitionMessage
        );

        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['status']->getDisplayName(),
            $data['timestamp'],
            count($metadataArray),
            'completed',
            true,
            $nextStage->getDisplayName()
        );

        return [
            'success' => true,
            'message' => 'Pre-bid conference documents uploaded successfully. Proceeding to ' . $nextStage->getDisplayName() . '.'
        ];
    }
}
