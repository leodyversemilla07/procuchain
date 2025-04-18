<?php

namespace App\Handlers\PostQualification;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Handlers\BaseStageHandler;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PostQualificationDocumentsHandler extends BaseStageHandler
{
    public function handle(Request $request): array
    {
        try {
            $data = $this->prepareHandlingData($request);
            $metadataArray = $this->prepareDocumentsMetadata($data);

            return $this->processDocuments($data, $metadataArray);
        } catch (Exception $e) {
            Log::error('Error in PostQualificationHandler', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Failed to upload ' . StageEnums::POST_QUALIFICATION->getDisplayName() . ' documents: ' . $e->getMessage()];
        }
    }

    private function prepareHandlingData(Request $request): array
    {
        return [
            'procurementId' => $request->input('procurement_id'),
            'procurementTitle' => $request->input('procurement_title'),
            'postQualificationReport' => $request->file('post_qualification_report'),
            'twgCertification' => $request->file('twg_certification'),
            'noticeOfPostQualification' => $request->file('notice_of_post_qualification'),
            'submissionDate' => $request->input('submission_date'),
            'outcome' => $request->boolean('outcome'),
            'remarks' => $request->input('remarks'),
            'timestamp' => now()->toIso8601String(),
            'userAddress' => $this->getUserBlockchainAddress(),
            'currentStage' => StageEnums::POST_QUALIFICATION,
            'nextStage' => StageEnums::BAC_RESOLUTION,
            'status' => $request->boolean('outcome') ? 
                StatusEnums::POST_QUALIFICATION_VERIFIED : 
                StatusEnums::POST_QUALIFICATION_FAILED
        ];
    }

    private function prepareDocumentsMetadata(array $data): array
    {
        $metadataArray = [];
        
        $baseMetadata = [
            'submission_date' => $data['submissionDate'],
            'outcome' => $data['outcome'] ? 'Verified' : 'Failed',
            'remarks' => $data['remarks']
        ];

        $files = [
            [
                'file' => $data['postQualificationReport'],
                'documentType' => 'Post Qualification Report',
                'required' => true
            ],
            [
                'file' => $data['twgCertification'],
                'documentType' => 'TWG Certification',
                'required' => false
            ],
            [
                'file' => $data['noticeOfPostQualification'],
                'documentType' => 'Notice of Post Qualification',
                'required' => true
            ]
        ];

        foreach ($files as $fileInfo) {
            if ($fileInfo['file']) {
                $fileMetadata = array_merge(
                    ['document_type' => $fileInfo['documentType']],
                    $baseMetadata
                );

                $metadataArray = array_merge(
                    $metadataArray,
                    $this->uploadAndPrepareMetadata(
                        [$fileInfo['file']],
                        [$fileMetadata],
                        $data['procurementId'],
                        $data['procurementTitle'],
                        $data['currentStage']->getStoragePathSegment()
                    )
                );
            } elseif ($fileInfo['required']) {
                throw new Exception("Required document {$fileInfo['documentType']} is missing");
            }
        }

        return $metadataArray;
    }

    private function processDocuments(array $data, array $metadataArray): array
    {
        // First publish the documents
        $this->blockchainService->publishDocuments(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['status']->getDisplayName(),
            $metadataArray,
            $data['userAddress']
        );

        if ($data['outcome']) {
            // If verification passed, proceed to next stage
            $this->blockchainService->handleStageTransition(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['status']->getDisplayName(),
                $data['status']->getDisplayName(),
                $data['currentStage']->getDisplayName(),
                $data['nextStage']->getDisplayName(),
                $data['userAddress'],
                'Proceeding to ' . $data['nextStage']->getDisplayName() . ' after successful post-qualification'
            );
        } else {
            // If verification failed, log the failure event
            $this->blockchainService->logEvent(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getDisplayName(),
                'Post-qualification failed - procurement process halted',
                0,
                $data['userAddress'],
                'status_update',
                'workflow',
                'warning',
                now()->addSecond()->toIso8601String()
            );
        }

        $this->notificationService->notifyStageUpdate(
            $data['procurementId'],
            $data['procurementTitle'],
            $data['currentStage']->getDisplayName(),
            $data['status']->getDisplayName(),
            $data['timestamp'],
            count($metadataArray),
            $data['outcome'],
            $data['outcome']
        );

        $message = $data['currentStage']->getDisplayName() . ' documents uploaded successfully';
        if ($data['outcome']) {
            $message .= '. Proceeding to ' . $data['nextStage']->getDisplayName() . '.';
        } else {
            $message .= '. Post-qualification failed - procurement process halted.';
        }

        return [
            'success' => true,
            'message' => $message
        ];
    }
}
