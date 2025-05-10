<?php

namespace App\Handlers\Completion;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Handlers\BaseStageHandler;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CompletionDocumentsHandler extends BaseStageHandler
{
    public function handle(Request $request): array
    {
        try {
            $data = $this->prepareHandlingData($request);
            $metadataArray = $this->prepareDocumentsMetadata($data);

            return $this->processDocuments($data, $metadataArray);
        } catch (Exception $e) {
            Log::error('Error in CompletionDocumentsHandler', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Failed to upload completion documents: '.$e->getMessage()];
        }
    }

    private function prepareHandlingData(Request $request): array
    {
        return [
            'procurementId' => $request->input('procurement_id'),
            'procurementTitle' => $request->input('procurement_title'),
            'completionFile' => $request->file('completion_file'),
            'completionDate' => $request->input('completion_date'),
            'completionNotes' => $request->input('completion_notes'),
            'timestamp' => now()->toIso8601String(),
            'userAddress' => $this->getUserBlockchainAddress(),
            'currentStage' => StageEnums::COMPLETION,
            'nextStage' => StageEnums::COMPLETED, // Add next stage
            'status' => StatusEnums::COMPLETED, // Update status to Completed
        ];
    }

    private function prepareDocumentsMetadata(array $data): array
    {
        $metadataArray = [];

        if ($data['completionFile']) {
            $metadataArray = array_merge($metadataArray, $this->uploadAndPrepareMetadata(
                [$data['completionFile']],
                [['document_type' => 'Certificate of Completion', 'completion_date' => $data['completionDate'], 'notes' => $data['completionNotes']]],
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
            $data['status']->getDisplayName(), // Use updated status
            $metadataArray,
            $data['userAddress']
        );

        // Add stage transition logic similar to NoticeOfAward
        $this->blockchainService->handleStageTransition(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['status']->getDisplayName(), // Use updated status
            $data['status']->getDisplayName(), // Use updated status
            $data['currentStage']->getDisplayName(),
            $data['nextStage']->getDisplayName(), // Use next stage
            $data['userAddress'],
            'Marking procurement as '.$data['nextStage']->getDisplayName().' after uploading '.$data['currentStage']->getDisplayName().' documents.'
        );

        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['status']->getDisplayName(), // Use updated status
            $data['timestamp'],
            count($metadataArray),
            'completed', // Update notification status string
            true, // Indicate transition
            $data['nextStage']->getDisplayName() // Pass next stage
        );

        return [
            'success' => true,
            'message' => $data['currentStage']->getDisplayName().' documents uploaded successfully. Procurement process is now '.$data['status']->getDisplayName().'.', // Update message
        ];
    }
}
