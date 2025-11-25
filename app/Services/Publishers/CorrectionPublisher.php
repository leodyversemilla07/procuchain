<?php

declare(strict_types=1);

namespace App\Services\Publishers;

use App\DataTransferObjects\CorrectionData;
use App\Repositories\CorrectionRepository;
use App\Services\BlockchainStorageService;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Correction Publisher Service
 *
 * Single responsibility: Publish document corrections to blockchain
 * - Handles correction records in procurement.corrections stream
 * - Supports both replacement and invalidation corrections
 */
final class CorrectionPublisher
{
    public function __construct(
        private CorrectionRepository $corrections,
        private BlockchainStorageService $fileStorage
    ) {}

    /**
     * Publish a document correction
     *
     * @param  string  $prNumber  PR Number
     * @param  string  $procurementTitle  Procurement title
     * @param  string  $originalTxid  Original document transaction ID
     * @param  string  $originalDocumentHash  Original document hash
     * @param  string  $correctionType  Correction type
     * @param  string  $action  Action type (replace or invalidate)
     * @param  string  $reason  Reason for correction
     * @param  string  $correctedBy  Who made the correction
     * @param  string  $userAddress  User blockchain address
     * @param  UploadedFile|null  $correctedFile  New file (for replacements)
     * @return array Correction transaction information
     *
     * @throws Exception If publication fails
     */
    public function publish(
        string $prNumber,
        string $procurementTitle,
        string $originalTxid,
        string $originalDocumentHash,
        string $correctionType,
        string $action,
        string $reason,
        string $correctedBy,
        string $userAddress,
        ?UploadedFile $correctedFile = null
    ): array {
        try {
            Log::info('CorrectionPublisher: Publishing correction', [
                'pr_number' => $prNumber,
                'original_txid' => $originalTxid,
                'action' => $action,
            ]);

            $correctedMetadata = null;

            // If replacing, upload the new file
            if ($action === 'replace' && $correctedFile !== null) {
                $fileResult = $this->fileStorage->uploadFile(
                    $correctedFile,
                    $prNumber,
                    1, // Use stage 1 as default for corrections
                    'corrected_document',
                    [
                        'pr_number' => $prNumber,
                        'correction_type' => 'replace',
                        'original_txid' => $originalTxid,
                        'corrected_by' => $correctedBy,
                    ]
                );

                $correctedMetadata = [
                    'file_name' => $fileResult['filename'],
                    'file_size' => $fileResult['size'],
                    'mime_type' => $fileResult['mime_type'],
                    'file_key' => $fileResult['file_key'],
                    'hash' => $fileResult['hash'],
                    'data_txid' => $fileResult['data_txid'],
                    'metadata_txid' => $fileResult['metadata_txid'],
                ];
            }

            // Create correction record
            $correction = new CorrectionData(
                prNumber: $prNumber,
                procurementTitle: $procurementTitle,
                originalTxid: $originalTxid,
                originalDocumentHash: $originalDocumentHash,
                correctionType: $correctionType,
                action: $action,
                reason: $reason,
                correctedBy: $correctedBy,
                correctedMetadata: $correctedMetadata,
                userAddress: $userAddress,
                timestamp: now(),
            );

            $txid = $this->corrections->create($correction);

            Log::info('CorrectionPublisher: Success', [
                'pr_number' => $prNumber,
                'correction_txid' => $txid,
            ]);

            return [
                'success' => true,
                'correction_txid' => $txid,
                'action' => $action,
                'corrected_file' => $correctedMetadata,
            ];
        } catch (Exception $e) {
            Log::error('CorrectionPublisher: Failed', [
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Publish a replacement correction (new file replaces old)
     *
     * @param  string  $prNumber  PR Number
     * @param  string  $procurementTitle  Procurement title
     * @param  string  $originalTxid  Original document transaction ID
     * @param  string  $originalDocumentHash  Original document hash
     * @param  string  $correctionType  Correction type
     * @param  string  $reason  Reason for replacement
     * @param  string  $correctedBy  Who made the correction
     * @param  string  $userAddress  User blockchain address
     * @param  UploadedFile  $correctedFile  New file
     * @return array Correction transaction information
     */
    public function publishReplacement(
        string $prNumber,
        string $procurementTitle,
        string $originalTxid,
        string $originalDocumentHash,
        string $correctionType,
        string $reason,
        string $correctedBy,
        string $userAddress,
        UploadedFile $correctedFile
    ): array {
        return $this->publish(
            prNumber: $prNumber,
            procurementTitle: $procurementTitle,
            originalTxid: $originalTxid,
            originalDocumentHash: $originalDocumentHash,
            correctionType: $correctionType,
            action: 'replace',
            reason: $reason,
            correctedBy: $correctedBy,
            userAddress: $userAddress,
            correctedFile: $correctedFile,
        );
    }

    /**
     * Publish an invalidation correction (mark document as invalid)
     *
     * @param  string  $prNumber  PR Number
     * @param  string  $procurementTitle  Procurement title
     * @param  string  $originalTxid  Original document transaction ID
     * @param  string  $originalDocumentHash  Original document hash
     * @param  string  $correctionType  Correction type
     * @param  string  $reason  Reason for invalidation
     * @param  string  $correctedBy  Who made the correction
     * @param  string  $userAddress  User blockchain address
     * @param  array|null  $originalDocumentData  Original document metadata
     * @return array Correction transaction information
     */
    public function publishInvalidation(
        string $prNumber,
        string $procurementTitle,
        string $originalTxid,
        string $originalDocumentHash,
        string $correctionType,
        string $reason,
        string $correctedBy,
        string $userAddress,
        ?array $originalDocumentData = null
    ): array {
        return $this->publish(
            prNumber: $prNumber,
            procurementTitle: $procurementTitle,
            originalTxid: $originalTxid,
            originalDocumentHash: $originalDocumentHash,
            correctionType: $correctionType,
            action: 'invalidate',
            reason: $reason,
            correctedBy: $correctedBy,
            userAddress: $userAddress,
            correctedFile: null,
        );
    }
}
