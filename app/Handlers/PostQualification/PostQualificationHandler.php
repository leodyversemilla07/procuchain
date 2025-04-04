<?php

namespace App\Handlers\PostQualification;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Handlers\BaseStageHandler;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PostQualificationHandler extends BaseStageHandler
{
    /**
     * Handle the post-qualification document upload process.
     */
    public function handle(Request $request): array
    {
        try {
            $data = $this->prepareHandlingData($request);
            $metadataArray = $this->prepareDocumentsMetadata($data);

            $this->blockchainService->publishDocuments(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getDisplayName(),
                $data['status']->getDisplayName(),
                $metadataArray,
                $data['userAddress']
            );

            return $data['outcome'] === 'Verified'
                ? $this->handleVerificationPassed($data, $metadataArray)
                : $this->handleVerificationFailed($data, $metadataArray);
        } catch (Exception $e) {
            Log::error('Error in PostQualificationHandler', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Failed to upload '.StageEnums::POST_QUALIFICATION->getDisplayName().' documents: '.$e->getMessage()];
        }
    }

    private function prepareHandlingData(Request $request): array
    {
        $outcome = $request->input('outcome');

        return [
            'procurementId' => $request->input('procurement_id'),
            'procurementTitle' => $request->input('procurement_title'),
            'taxReturnFile' => $request->file('tax_return_file'),
            'financialStatementFile' => $request->file('financial_statement_file'),
            'verificationReportFile' => $request->file('verification_report_file'),
            'submissionDate' => $request->input('submission_date'),
            'outcome' => $outcome,
            'timestamp' => now()->toIso8601String(),
            'userAddress' => $this->getUserBlockchainAddress(),
            'currentStage' => StageEnums::POST_QUALIFICATION,
            'nextStage' => StageEnums::BAC_RESOLUTION,
            'status' => $outcome === 'Verified' ?
                StatusEnums::POST_QUALIFICATION_VERIFIED :
                StatusEnums::POST_QUALIFICATION_FAILED,
        ];
    }

    private function prepareDocumentsMetadata(array $data): array
    {
        $metadataArray = [];

        if ($data['taxReturnFile']) {
            $metadataArray = array_merge($metadataArray, $this->uploadAndPrepareMetadata(
                [$data['taxReturnFile']],
                [['document_type' => 'Tax Return', 'submission_date' => $data['submissionDate'], 'outcome' => $data['outcome']]],
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getStoragePathSegment()
            ));
        }

        if ($data['financialStatementFile']) {
            $metadataArray = array_merge($metadataArray, $this->uploadAndPrepareMetadata(
                [$data['financialStatementFile']],
                [['document_type' => 'Financial Statement', 'submission_date' => $data['submissionDate'], 'outcome' => $data['outcome']]],
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getStoragePathSegment()
            ));
        }

        if ($data['verificationReportFile']) {
            $metadataArray = array_merge($metadataArray, $this->uploadAndPrepareMetadata(
                [$data['verificationReportFile']],
                [['document_type' => 'Verification Report', 'submission_date' => $data['submissionDate'], 'outcome' => $data['outcome']]],
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getStoragePathSegment()
            ));
        }

        return $metadataArray;
    }

    private function handleVerificationPassed(array $data, array $metadataArray): array
    {
        $this->blockchainService->handleStageTransition(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['status']->getDisplayName(),
            $data['status']->getDisplayName(),
            $data['currentStage']->getDisplayName(),
            $data['nextStage']->getDisplayName(),
            $data['userAddress'],
            'Proceeding to '.$data['nextStage']->getDisplayName().' after successful '.$data['currentStage']->getDisplayName()
        );

        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['status']->getDisplayName(),
            $data['timestamp'],
            count($metadataArray),
            'verified',
            true,
            $data['nextStage']->getDisplayName()
        );

        return [
            'success' => true,
            'message' => $data['currentStage']->getDisplayName().' documents uploaded successfully. Proceeding to '.$data['nextStage']->getDisplayName().'.',
        ];
    }

    private function handleVerificationFailed(array $data, array $metadataArray): array
    {
        $newTimestamp = now()->addSecond()->toIso8601String();

        $this->blockchainService->logEvent(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['currentStage']->getDisplayName().' failed - procurement process halted',
            0,
            $data['userAddress'],
            'status_update',
            'workflow',
            'warning',
            $newTimestamp
        );

        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['status']->getDisplayName(),
            $data['timestamp'],
            count($metadataArray),
            'failed',
            false,
            ''
        );

        return [
            'success' => true,
            'message' => $data['currentStage']->getDisplayName().' documents uploaded successfully with outcome: '.$data['outcome'].'. Procurement process halted.',
        ];
    }
}
