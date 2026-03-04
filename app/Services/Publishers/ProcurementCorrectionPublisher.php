<?php

declare(strict_types=1);

namespace App\Services\Publishers;

use App\Contracts\ProcurementCorrectionRepositoryInterface;
use App\DataTransferObjects\ProcurementCorrectionData;
use App\DataTransferObjects\ProcurementData;
use App\Enums\ProcurementCategoryEnums;
use App\Enums\ProcurementModeEnums;
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
        private ProcurementCorrectionRepositoryInterface $procurementCorrections
    ) {}

    /**
     * Publish a procurement metadata correction
     *
     * @param  ProcurementData  $originalProcurement  The original procurement data
     * @param  array  $correctedData  The corrected field values
     * @param  string  $reason  Reason for the correction
     * @param  string  $correctedBy  Who made the correction
     * @param  string  $userAddress  User blockchain address
     * @return array Correction transaction information
     */
    public function publishCorrection(
        ProcurementData $originalProcurement,
        array $correctedData,
        string $reason,
        string $correctedBy,
        string $userAddress
    ): array {
        try {
            Log::info('ProcurementCorrectionPublisher: Publishing correction', [
                'pr_number' => $originalProcurement->prNumber,
                'corrected_fields' => array_keys($correctedData),
            ]);

            // Determine correction type based on fields being corrected
            $correctionType = $this->determineCorrectionType($correctedData);

            // Create correction record
            $correction = new ProcurementCorrectionData(
                prNumber: $originalProcurement->prNumber,
                procurementTitle: $originalProcurement->title,
                correctionType: $correctionType,
                reason: $reason,
                correctedBy: $correctedBy,
                userAddress: $userAddress,
                timestamp: now(),

                // Original values
                originalTitle: $originalProcurement->title,
                originalDescription: $originalProcurement->description,
                originalAbcAmount: $originalProcurement->abcAmount,
                originalFundingSource: $originalProcurement->fundingSource,
                originalCategory: $originalProcurement->category,
                originalProcurementMode: $originalProcurement->procurementMode,
                originalOffice: $originalProcurement->office,
                originalEndUser: $originalProcurement->endUser,
                originalPurpose: $originalProcurement->purpose,
                originalDeliveryLocation: $originalProcurement->deliveryLocation,
                originalDeliveryDate: $originalProcurement->deliveryDate,
                originalDeliveryTermDays: $originalProcurement->deliveryTermDays,
                originalBacResolutionNumber: $originalProcurement->bacResolutionNumber,
                originalBacResolutionDate: $originalProcurement->bacResolutionDate,
                originalPhilgepsReference: $originalProcurement->philgepsReference,
                originalPhilgepsPostingDate: $originalProcurement->philgepsPostingDate,
                originalApprovedBy: $originalProcurement->approvedBy,
                originalApprovalDate: $originalProcurement->approvalDate,

                // Corrected values (merge with original for unchanged fields)
                correctedTitle: $correctedData['title'] ?? $originalProcurement->title,
                correctedDescription: $correctedData['description'] ?? $originalProcurement->description,
                correctedAbcAmount: $correctedData['abc_amount'] ?? $originalProcurement->abcAmount,
                correctedFundingSource: $correctedData['funding_source'] ?? $originalProcurement->fundingSource,
                correctedCategory: isset($correctedData['category']) ? ProcurementCategoryEnums::from($correctedData['category']) : $originalProcurement->category,
                correctedProcurementMode: isset($correctedData['procurement_mode']) ? ProcurementModeEnums::from($correctedData['procurement_mode']) : $originalProcurement->procurementMode,
                correctedOffice: $correctedData['office'] ?? $originalProcurement->office,
                correctedEndUser: $correctedData['end_user'] ?? $originalProcurement->endUser,
                correctedPurpose: $correctedData['purpose'] ?? $originalProcurement->purpose,
                correctedDeliveryLocation: $correctedData['delivery_location'] ?? $originalProcurement->deliveryLocation,
                correctedDeliveryDate: isset($correctedData['delivery_date']) ? Carbon::parse($correctedData['delivery_date']) : $originalProcurement->deliveryDate,
                correctedDeliveryTermDays: $correctedData['delivery_term_days'] ?? $originalProcurement->deliveryTermDays,
                correctedBacResolutionNumber: $correctedData['bac_resolution_number'] ?? $originalProcurement->bacResolutionNumber,
                correctedBacResolutionDate: isset($correctedData['bac_resolution_date']) ? Carbon::parse($correctedData['bac_resolution_date']) : $originalProcurement->bacResolutionDate,
                correctedPhilgepsReference: $correctedData['philgeps_reference'] ?? $originalProcurement->philgepsReference,
                correctedPhilgepsPostingDate: isset($correctedData['philgeps_posting_date']) ? Carbon::parse($correctedData['philgeps_posting_date']) : $originalProcurement->philgepsPostingDate,
                correctedApprovedBy: $correctedData['approved_by'] ?? $originalProcurement->approvedBy,
                correctedApprovalDate: isset($correctedData['approval_date']) ? Carbon::parse($correctedData['approval_date']) : $originalProcurement->approvalDate,
            );

            // Validate that correction actually changes something
            if (! $correction->hasChanges()) {
                throw new Exception('Correction must change at least one field');
            }

            $txid = $this->procurementCorrections->create($correction);

            Log::info('ProcurementCorrectionPublisher: Success', [
                'pr_number' => $originalProcurement->prNumber,
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
                'pr_number' => $originalProcurement->prNumber,
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
