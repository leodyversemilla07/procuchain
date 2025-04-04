<?php

namespace App\Handlers\BidEvaluation;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Handlers\BaseStageHandler;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BidEvaluationHandler extends BaseStageHandler
{
    /**
     * Handle the bid evaluation document upload process.
     */
    public function handle(Request $request): array
    {
        try {
            $data = $this->prepareHandlingData($request);
            $metadataArray = $this->prepareDocumentsMetadata($data);

            return $this->processDocuments($data, $metadataArray);
        } catch (Exception $e) {
            Log::error('Error in BidEvaluationHandler', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Failed to upload '.StageEnums::BID_EVALUATION->getDisplayName().' documents: '.$e->getMessage()];
        }
    }

    private function prepareHandlingData(Request $request): array
    {
        return [
            'procurementId' => $request->input('procurement_id'),
            'procurementTitle' => $request->input('procurement_title'),
            'summaryFile' => $request->file('summary_file'),
            'abstractFile' => $request->file('abstract_file'),
            'evaluationDate' => $request->input('evaluation_date'),
            'evaluatorNames' => $request->input('evaluator_names'),
            'timestamp' => now()->toIso8601String(),
            'userAddress' => $this->getUserBlockchainAddress(),
            'currentStage' => StageEnums::BID_EVALUATION,
            'nextStage' => StageEnums::POST_QUALIFICATION,
            'status' => StatusEnums::BIDS_EVALUATED,
        ];
    }

    private function prepareDocumentsMetadata(array $data): array
    {
        $metadataArray = [];

        if ($data['summaryFile']) {
            $metadataArray = array_merge($metadataArray, $this->uploadAndPrepareMetadata(
                [$data['summaryFile']],
                [['document_type' => 'Evaluation Summary', 'evaluation_date' => $data['evaluationDate'], 'evaluator_names' => $data['evaluatorNames']]],
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getStoragePathSegment()
            ));
        }

        if ($data['abstractFile']) {
            $metadataArray = array_merge($metadataArray, $this->uploadAndPrepareMetadata(
                [$data['abstractFile']],
                [['document_type' => 'Abstract', 'evaluation_date' => $data['evaluationDate'], 'evaluator_names' => $data['evaluatorNames']]],
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
            'evaluated',
            true,
            $data['nextStage']->getDisplayName()
        );

        return [
            'success' => true,
            'message' => $data['currentStage']->getDisplayName().' documents uploaded successfully. Proceeding to '.$data['nextStage']->getDisplayName().'.',
        ];
    }
}
