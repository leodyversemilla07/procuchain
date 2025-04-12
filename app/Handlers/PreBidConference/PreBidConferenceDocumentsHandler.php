<?php

namespace App\Handlers\PreBidConference;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Handlers\BaseStageHandler;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

            return ['success' => false, 'message' => 'Failed to upload pre-bid conference documents: '.$e->getMessage()];
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
            'currentStage' => StageEnums::PRE_BID_CONFERENCE,
            'nextStage' => StageEnums::SUPPLEMENTAL_BID_BULLETIN,
            'status' => StatusEnums::PRE_BID_CONFERENCE_COMPLETED,
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

        $this->blockchainService->handleStageTransition(
            $data['procurementId'],
            $data['procurementTitle'],
            StatusEnums::PRE_BID_CONFERENCE_HELD->getDisplayName(), // From PRE_BID_CONFERENCE_HELD
            $data['status']->getDisplayName(),                      // To PRE_BID_CONFERENCE_COMPLETED
            $data['currentStage']->getDisplayName(),                // From PRE_BID_CONFERENCE
            $data['nextStage']->getDisplayName(),                   // To SUPPLEMENTAL_BID_BULLETIN
            $data['userAddress'],
            'Proceeding to '.$data['nextStage']->getDisplayName().' after completing '.$data['currentStage']->getDisplayName()
        );

        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['status']->getDisplayName(),
            $data['timestamp'],
            'completed',
            true,
            $data['nextStage']->getDisplayName()
        );

        return [
            'success' => true,
            'message' => $data['currentStage']->getDisplayName().' documents uploaded successfully. Proceeding to '.$data['nextStage']->getDisplayName().'.',
        ];
    }
}
