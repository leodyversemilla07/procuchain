<?php

namespace App\Handlers\PerformanceBondContractAndPo;

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Handlers\BaseStageHandler;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class PerformanceBondContractAndPoHandler extends BaseStageHandler
{
    /**
     * Handle the upload of performance bond, contract and purchase order documents.
     */
    public function handle(Request $request): array
    {
        $processedDocumentsCount = 0;
        $errors = [];

        try {
            $data = $this->prepareHandlingData($request);

            // Process Performance Bond
            if ($data['performanceBondFile']) {
                try {
                    $bondMetadata = $this->prepareSingleDocumentMetadata(
                        $data['performanceBondFile'],
                        'Performance Bond',
                        ['submission_date' => $data['submissionDate'], 'bond_amount' => $data['bondAmount']],
                        $data
                    );
                    $this->publishSingleDocument($bondMetadata, $data);
                    $processedDocumentsCount++;
                } catch (Exception $e) {
                    $errors[] = 'Failed to process Performance Bond: '.$e->getMessage();
                    Log::error('Error processing Performance Bond', ['error' => $e->getMessage(), 'data' => $data]);
                }
            }

            // Process Contract
            if ($data['contractFile']) {
                try {
                    $contractMetadata = $this->prepareSingleDocumentMetadata(
                        $data['contractFile'],
                        'Contract',
                        ['signing_date' => $data['signingDate']],
                        $data
                    );
                    $this->publishSingleDocument($contractMetadata, $data);
                    $processedDocumentsCount++;
                } catch (Exception $e) {
                    $errors[] = 'Failed to process Contract: '.$e->getMessage();
                    Log::error('Error processing Contract', ['error' => $e->getMessage(), 'data' => $data]);
                }
            }

            // Process Purchase Order (PO)
            if ($data['poFile']) {
                try {
                    $poMetadata = $this->prepareSingleDocumentMetadata(
                        $data['poFile'],
                        'Purchase Order', // Changed from 'PO' for clarity
                        ['signing_date' => $data['signingDate']],
                        $data
                    );
                    $this->publishSingleDocument($poMetadata, $data);
                    $processedDocumentsCount++;
                } catch (Exception $e) {
                    $errors[] = 'Failed to process Purchase Order: '.$e->getMessage();
                    Log::error('Error processing Purchase Order', ['error' => $e->getMessage(), 'data' => $data]);
                }
            }

            // Check if at least one document was processed successfully
            if ($processedDocumentsCount === 0 && ! empty($errors)) {
                // If no documents were processed and there were errors, throw the first error
                throw new Exception(implode('; ', $errors));
            } elseif ($processedDocumentsCount === 0) {
                // If no files were provided at all
                throw new Exception('No document files were provided for upload.');
            }

            // Proceed with stage transition and notification only if at least one doc was processed
            $this->finalizeStageProcessing($data, $processedDocumentsCount);

            $successMessage = $data['currentStage']->getDisplayName().' documents processed successfully ('.$processedDocumentsCount.' files). Proceeding to '.$data['nextStage']->getDisplayName().' stage.';
            if (! empty($errors)) {
                $successMessage .= ' Some files failed: '.implode('; ', $errors);
            }

            return [
                'success' => true,
                'message' => $successMessage,
            ];

        } catch (Exception $e) {
            Log::error('Error in PerformanceBondContractAndPoHandler handle method', ['error' => $e->getMessage()]);
            $errorMessage = 'Failed to upload '.StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO->getDisplayName().' documents: '.$e->getMessage();
            if (! empty($errors)) {
                $errorMessage .= ' Individual errors: '.implode('; ', $errors);
            }

            return ['success' => false, 'message' => $errorMessage];
        }
    }

    private function prepareHandlingData(Request $request): array
    {
        return [
            'procurementId' => $request->input('procurement_id'),
            'procurementTitle' => $request->input('procurement_title'),
            'performanceBondFile' => $request->file('performance_bond_file'),
            'contractFile' => $request->file('contract_file'),
            'poFile' => $request->file('po_file'),
            'submissionDate' => $request->input('submission_date'),
            'bondAmount' => $request->input('bond_amount'),
            'signingDate' => $request->input('signing_date'),
            'timestamp' => now()->toIso8601String(),
            'userAddress' => $this->getUserBlockchainAddress(),
            'currentStage' => StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
            'nextStage' => StageEnums::NOTICE_TO_PROCEED,
            'status' => StatusEnums::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED,
        ];
    }

    /**
     * Prepares metadata for a single document file.
     *
     * @param  UploadedFile  $file  The uploaded file object.
     * @param  string  $documentType  The specific type of the document (e.g., 'Performance Bond').
     * @param  array  $specificMetadata  Additional metadata specific to this document type.
     * @param  array  $commonData  Common data like procurement ID, title, etc.
     * @return array The prepared metadata array for the single document.
     *
     * @throws Exception
     */
    private function prepareSingleDocumentMetadata(UploadedFile $file, string $documentType, array $specificMetadata, array $commonData): array
    {
        // Use the existing uploadAndPrepareMetadata, but ensure it handles a single file correctly.
        // We pass the file in an array and the metadata in a nested array as expected by the original method.
        $metadataResult = $this->uploadAndPrepareMetadata(
            [$file], // File needs to be in an array
            [['document_type' => $documentType] + $specificMetadata], // Metadata needs to be nested
            $commonData['procurementId'],
            $commonData['procurementTitle'],
            $documentType // Use document type as a unique identifier for the upload context if needed
        );

        // uploadAndPrepareMetadata returns an array of metadata arrays, we need the first one.
        if (empty($metadataResult) || ! isset($metadataResult[0])) {
            throw new Exception("Failed to prepare metadata for document type: {$documentType}");
        }

        return $metadataResult[0]; // Return the metadata for the single processed file
    }

    /**
     * Publishes a single document's metadata to the blockchain.
     *
     * @param  array  $documentMetadata  The metadata array for the single document.
     * @param  array  $commonData  Common data like procurement ID, title, stage, status, user address.
     *
     * @throws Exception
     */
    private function publishSingleDocument(array $documentMetadata, array $commonData): void
    {
        // Call publishDocuments with the single metadata array wrapped in another array,
        // as the original method expects an array of metadata arrays.
        $this->blockchainService->publishDocuments(
            $commonData['procurementId'],
            $commonData['procurementTitle'],
            $commonData['currentStage']->getDisplayName(),
            $commonData['status']->getDisplayName(), // Use the status relevant to this stage
            [$documentMetadata], // Wrap the single metadata array
            $commonData['userAddress']
        );
        Log::info('Successfully published document', [
            'procurement_id' => $commonData['procurementId'], // Corrected variable access
            'document_type' => $documentMetadata['document_type'] ?? 'Unknown',
            'hash' => $documentMetadata['hash'] ?? 'N/A',
        ]);
    }

    /**
     * Finalizes the stage processing by handling stage transition and notification.
     *
     * @param  array  $data  Common handling data.
     * @param  int  $processedDocumentsCount  The number of documents successfully processed.
     */
    private function finalizeStageProcessing(array $data, int $processedDocumentsCount): void
    {
        // Only transition stage and notify if at least one document was successfully processed.
        if ($processedDocumentsCount > 0) {
            $this->blockchainService->handleStageTransition(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['status']->getDisplayName(), // Status indicating documents recorded
                $data['status']->getDisplayName(), // Current status remains the same for the transition event itself
                $data['currentStage']->getDisplayName(),
                $data['nextStage']->getDisplayName(),
                $data['userAddress'],
                'Proceeding to '.$data['nextStage']->getDisplayName().' stage after recording '.$processedDocumentsCount.' document(s)'
            );

            $this->notificationService->notifyStageUpdate(
                $data['procurementId'],
                $data['procurementTitle'],
                $data['currentStage']->getDisplayName(),
                $data['status']->getDisplayName(),
                $data['timestamp'], // Use the timestamp from prepared data
                $processedDocumentsCount,
                'recorded', // Action verb
                true, // Indicate completion of this specific action
                $data['nextStage']->getDisplayName() // Next stage hint
            );
        } else {
            Log::warning('Skipping stage transition and notification as no documents were processed successfully.', ['procurement_id' => $data['procurementId']]);
        }
    }

    // Remove the old prepareDocumentsMetadata and processDocuments methods
    // private function prepareDocumentsMetadata(array $data): array { ... } // REMOVED
    // private function processDocuments(array $data, array $metadataArray): array { ... } // REMOVED
}
