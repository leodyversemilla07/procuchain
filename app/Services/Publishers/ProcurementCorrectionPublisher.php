<?php

declare(strict_types=1);

namespace App\Services\Publishers;

use App\Enums\Stream;
use App\Models\Procurement;
use App\Models\ProcurementMetadataCorrection;
use App\Services\BlockchainRpcClient;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Publisher for procurement metadata corrections
 *
 * Handles the atomic publishing of procurement-level corrections to blockchain.
 * Supports corrections to procurement titles, descriptions, ABC amounts, dates, etc.
 */
class ProcurementCorrectionPublisher
{
    public function __construct(
        private readonly BlockchainRpcClient $rpcClient,
    ) {}

    /**
     * Publish a procurement metadata correction
     *
     * @param  Procurement  $originalProcurement  The original procurement data
     * @param  array  $correctedData  The corrected field values
     * @param  string  $reason  Reason for the correction
     * @param  string  $correctedBy  Who made the correction
     * @param  string  $userAddress  User blockchain address
     * @return array Correction transaction information
     */
    public function publishCorrection(
        Procurement $originalProcurement,
        array $correctedData,
        string $reason,
        string $correctedBy,
        string $userAddress
    ): array {
        try {
            Log::info('ProcurementCorrectionPublisher: Publishing correction', [
                'pr_number' => $originalProcurement->pr_number,
                'corrected_fields' => array_keys($correctedData),
            ]);

            // Determine correction type based on fields being corrected
            $correctionType = $this->determineCorrectionType($correctedData);

            // Create correction record
            $correction = new ProcurementMetadataCorrection;
            $correction->correction_type = $correctionType;
            $correction->reason = $reason;
            $correction->corrected_by = $correctedBy;
            $correction->user_address = $userAddress;
            $correction->corrected_at = now();

            // Original values
            $correction->original_title = $originalProcurement->title;
            $correction->original_description = $originalProcurement->description;
            $correction->original_abc_amount = $originalProcurement->abc_amount;
            $correction->original_funding_source = $originalProcurement->fund_source;
            $correction->original_category = $originalProcurement->category;
            $correction->original_procurement_mode = $originalProcurement->procurement_mode;
            $correction->original_office = $originalProcurement->office;
            $correction->original_end_user = $originalProcurement->end_user;
            $correction->original_delivery_location = $originalProcurement->delivery_location;
            $correction->original_delivery_date = $originalProcurement->delivery_date;
            $correction->original_delivery_term_days = $originalProcurement->delivery_term_days;
            $correction->original_bac_resolution_number = $originalProcurement->bac_resolution_number;
            $correction->original_bac_resolution_date = $originalProcurement->bac_resolution_date;
            $correction->original_philgeps_reference = $originalProcurement->philgeps_reference;
            $correction->original_philgeps_posting_date = $originalProcurement->philgeps_posting_date;
            $correction->original_approved_by = $originalProcurement->approved_by;
            $correction->original_approval_date = $originalProcurement->approval_date;

            // Corrected values (merge with original for unchanged fields)
            $correction->corrected_title = $correctedData['title'] ?? $originalProcurement->title;
            $correction->corrected_description = $correctedData['description'] ?? $originalProcurement->description;
            $correction->corrected_abc_amount = $correctedData['abc_amount'] ?? $originalProcurement->abc_amount;
            $correction->corrected_funding_source = $correctedData['funding_source'] ?? $originalProcurement->fund_source;
            $correction->corrected_category = $correctedData['category'] ?? $originalProcurement->category;
            $correction->corrected_procurement_mode = $correctedData['procurement_mode'] ?? $originalProcurement->procurement_mode;
            $correction->corrected_office = $correctedData['office'] ?? $originalProcurement->office;
            $correction->corrected_end_user = $correctedData['end_user'] ?? $originalProcurement->end_user;
            $correction->corrected_delivery_location = $correctedData['delivery_location'] ?? $originalProcurement->delivery_location;
            $correction->corrected_delivery_date = isset($correctedData['delivery_date']) ? Carbon::parse($correctedData['delivery_date']) : $originalProcurement->delivery_date;
            $correction->corrected_delivery_term_days = $correctedData['delivery_term_days'] ?? $originalProcurement->delivery_term_days;
            $correction->corrected_bac_resolution_number = $correctedData['bac_resolution_number'] ?? $originalProcurement->bac_resolution_number;
            $correction->corrected_bac_resolution_date = isset($correctedData['bac_resolution_date']) ? Carbon::parse($correctedData['bac_resolution_date']) : $originalProcurement->bac_resolution_date;
            $correction->corrected_philgeps_reference = $correctedData['philgeps_reference'] ?? $originalProcurement->philgeps_reference;
            $correction->corrected_philgeps_posting_date = isset($correctedData['philgeps_posting_date']) ? Carbon::parse($correctedData['philgeps_posting_date']) : $originalProcurement->philgeps_posting_date;
            $correction->corrected_approved_by = $correctedData['approved_by'] ?? $originalProcurement->approved_by;
            $correction->corrected_approval_date = isset($correctedData['approval_date']) ? Carbon::parse($correctedData['approval_date']) : $originalProcurement->approval_date;

            // Validate that correction actually changes something
            if (! $correction->hasChanges()) {
                throw new Exception('Correction must change at least one field');
            }

            $txid = $this->rpcClient->publish(
                Stream::PROCUREMENTS_CORRECTIONS->value,
                $originalProcurement->pr_number,
                ['json' => $correction->toBlockchainArray()]
            );

            if (! is_string($txid) || $txid === '') {
                throw new Exception('Blockchain metadata correction publish did not return a transaction id.');
            }

            Log::info('ProcurementCorrectionPublisher: Success', [
                'pr_number' => $originalProcurement->pr_number,
                'correction_txid' => $txid,
                'correction_type' => $correctionType,
                'changed_fields' => array_keys($correction->getChangedFields()),
            ]);

            return [
                'success' => true,
                'correction_txid' => $txid,
                'correction_type' => $correctionType,
                'changed_fields' => $correction->getChangedFields(),
            ];
        } catch (Exception $e) {
            Log::error('ProcurementCorrectionPublisher: Failed', [
                'pr_number' => $originalProcurement->pr_number,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Determine the correction type based on the fields being corrected
     */
    private function determineCorrectionType(array $correctedData): string
    {
        // Financial corrections (ABC amount changes)
        if (isset($correctedData['abc_amount'])) {
            return 'financial';
        }

        // Date corrections
        $dateFields = ['bac_resolution_date', 'philgeps_posting_date', 'approval_date'];
        foreach ($dateFields as $field) {
            if (isset($correctedData[$field])) {
                return 'dates';
            }
        }

        // Approval corrections
        if (isset($correctedData['approved_by']) || isset($correctedData['approval_date'])) {
            return 'approval';
        }

        // Default to metadata correction
        return 'metadata';
    }
}
