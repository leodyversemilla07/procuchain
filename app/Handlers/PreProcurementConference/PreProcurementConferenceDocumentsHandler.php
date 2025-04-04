<?php

namespace App\Handlers\PreProcurementConference;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Handlers\BaseStageHandler;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PreProcurementConferenceDocumentsHandler extends BaseStageHandler
{
    public function handle(Request $request): array
    {
        try {
            $data = $this->prepareHandlingData($request);
            $metadataArray = $this->prepareDocumentsMetadata($data);

            return $this->processDocuments($data, $metadataArray);
        } catch (Exception $e) {
            Log::error('Error in UploadPreProcurementDocumentsHandler', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Failed to upload '.StageEnums::PRE_PROCUREMENT_CONFERENCE->getDisplayName().' documents: '.$e->getMessage()];
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
            'timestamp' => now()->toIso8601String(),
            'userAddress' => $this->getUserBlockchainAddress(),
            'currentStage' => StageEnums::PRE_PROCUREMENT_CONFERENCE,
            'nextStage' => StageEnums::BIDDING_DOCUMENTS,
            'status' => StatusEnums::PRE_PROCUREMENT_CONFERENCE_COMPLETED,
        ];
    }

    private function prepareDocumentsMetadata(array $data): array
    {
        $metadataArray = [];

        if ($data['minutesFile']) {
            $metadataArray = array_merge($metadataArray, $this->uploadAndPrepareMetadata(
                [$data['minutesFile']],
                [['document_type' => 'Minutes', 'meeting_date' => $data['meetingDate'], 'participants' => $data['participants']]],
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getStoragePathSegment()
            ));
        }

        if ($data['attendanceFile']) {
            $metadataArray = array_merge($metadataArray, $this->uploadAndPrepareMetadata(
                [$data['attendanceFile']],
                [['document_type' => 'Attendance', 'meeting_date' => $data['meetingDate'], 'participants' => $data['participants']]],
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
            $data['status']->getDisplayName(),
            $data['status']->getDisplayName(),
            $data['currentStage']->getDisplayName(),
            $data['nextStage']->getDisplayName(),
            $data['userAddress'],
            'Proceeding to '.$data['nextStage']->getDisplayName().' after '.$data['currentStage']->getDisplayName()
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
            $data['nextStage']->getDisplayName()
        );

        return [
            'success' => true,
            'message' => $data['currentStage']->getDisplayName().' documents uploaded successfully. Proceeding to '.$data['nextStage']->getDisplayName().'.',
        ];
    }
}
