<?php

namespace App\Services;

use App\Enums\StreamEnums;
use App\Jobs\PublishDocumentCorrectionJob;
use App\Models\ProcurementDocument;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling blockchain immutability corrections.
 *
 * Since blockchain data is immutable, this service implements industry-standard
 * correction patterns:
 * 1. Correction Records - Append new records that reference and supersede originals
 * 2. Status Marking - Mark original as "corrected", "superseded", or "invalid"
 * 3. Audit Trail - Maintain complete history of all changes
 *
 * Benefits:
 * - Maintains blockchain immutability (security feature)
 * - Provides correction mechanism (usability feature)
 * - Complete audit trail (compliance feature)
 * - Transparent error handling (trust feature)
 */
class BlockchainCorrectionService
{
    /**
     * Correct a document that was incorrectly uploaded to blockchain.
     *
     * @param  ProcurementDocument  $document  The document with incorrect data
     * @param  string  $reason  Why the correction is needed
     * @param  array|null  $correctedMetadata  New correct metadata (null to just invalidate)
     * @param  string  $correctedBy  Email/name of person making correction
     * @return string Transaction ID of the correction record
     */
    public function correctDocument(
        ProcurementDocument $document,
        string $reason,
        ?array $correctedMetadata,
        string $correctedBy,
        string $userAddress
    ): string {
        try {
            // Validate the document has been published to blockchain
            if (empty($document->blockchain_txid)) {
                throw new Exception('Document has not been published to blockchain yet');
            }

            Log::info('Creating blockchain correction record', [
                'document_id' => $document->id,
                'original_txid' => $document->blockchain_txid,
                'reason' => $reason,
            ]);

            // Update document status in database to mark as corrected
            $document->update([
                'is_corrected' => true,
                'correction_reason' => $reason,
                'corrected_at' => now(),
                'corrected_by' => $correctedBy,
            ]);

            // Dispatch job to publish correction to blockchain
            $job = new PublishDocumentCorrectionJob(
                procurementId: $document->procurement->id,
                procurementTitle: $document->procurement->title,
                originalTxid: $document->blockchain_txid,
                originalDocumentHash: $document->metadata['hash'] ?? '',
                correctionReason: $reason,
                correctedMetadata: $correctedMetadata,
                correctedBy: $correctedBy,
                userAddress: $userAddress
            );

            dispatch($job);

            return 'Correction record will be published to blockchain';
        } catch (\Exception $e) {
            Log::error('Failed to create correction record', [
                'document_id' => $document->id ?? null,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get all corrections for a procurement.
     *
     * @return array Correction records from blockchain
     */
    public function getCorrections(string $procurementId, MultichainService $multiChain): array
    {
        try {
            // Query blockchain for correction records
            $corrections = $multiChain->listStreamKeyItems(
                StreamEnums::CORRECTIONS->value,
                $procurementId,
                false, // verbose
                10000, // count
                0 // start
            );

            return $corrections ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve corrections', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Check if a document has been corrected/superseded.
     *
     * @param  string  $txid  Original transaction ID
     * @return array|null Correction record if exists
     */
    public function findCorrectionForTransaction(string $txid, MultichainService $multiChain): ?array
    {
        try {
            // Search correction stream for this txid
            $corrections = $multiChain->listStreamKeyItems(
                StreamEnums::CORRECTIONS->value,
                $txid,
                true, // verbose to get full data
                1, // only need one
                0
            );

            return $corrections[0] ?? null;
        } catch (\Exception $e) {
            Log::warning('Could not check for corrections', [
                'txid' => $txid,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
