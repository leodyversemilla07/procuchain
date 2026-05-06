<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ProcurementRepositoryInterface;
use App\DataTransferObjects\ProcurementData;
use App\Enums\ProcurementModeEnums;
use Carbon\Carbon;

/**
 * Repeat Order Validation Service
 *
 * Validates Repeat Order procurements per NGPA IRR Section 33.2:
 * - Must be within 6 months from NTP of original contract
 * - Cannot exceed 25% of quantity from original contract
 * - Unit price must be equal to or lower than original contract
 *
 * @see ProcurementModeEnums::REPEAT_ORDER
 */
final readonly class RepeatOrderValidationService
{
    /**
     * Maximum time limit from NTP date (6 months per Section 33.2.b)
     */
    private const MAX_MONTHS_FROM_NTP = 6;

    /**
     * Maximum quantity percentage of original contract (25% per Section 33.2.c)
     */
    private const MAX_QUANTITY_PERCENTAGE = 25;

    public function __construct(
        private ProcurementRepositoryInterface $procurementRepository
    ) {}

    /**
     * Validate a Repeat Order procurement against NGPA Section 33 requirements
     *
     * @param  string  $originalPrNumber  The PR number of the original contract
     * @param  float  $repeatOrderQuantity  The quantity requested in the repeat order
     * @param  float  $originalQuantity  The quantity from the original contract
     * @param  float  $repeatOrderUnitPrice  The unit price in the repeat order
     * @param  float  $originalUnitPrice  The unit price from the original contract
     * @return array{valid: bool, errors: array<string>}
     */
    public function validate(
        string $originalPrNumber,
        float $repeatOrderQuantity,
        float $originalQuantity,
        float $repeatOrderUnitPrice,
        float $originalUnitPrice
    ): array {
        $errors = [];

        // Get the original procurement
        $originalProcurement = $this->procurementRepository->findByProcurement($originalPrNumber);

        if (! $originalProcurement) {
            return [
                'valid' => false,
                'errors' => ['Original procurement not found. Repeat Order requires reference to a valid original contract.'],
            ];
        }

        // Validate it was awarded through competitive bidding
        if (! $this->wasAwardedThroughBidding($originalProcurement)) {
            $errors[] = sprintf(
                'Original contract must have been awarded through bidding per NGPA Section 33.1. Mode: %s',
                $originalProcurement->procurementMode->getDisplayName()
            );
        }

        // Validate time limit (6 months from NTP)
        $timeLimitValidation = $this->validateTimeLimit($originalProcurement);
        if (! $timeLimitValidation['valid']) {
            $errors[] = $timeLimitValidation['error'];
        }

        // Validate quantity limit (25% max)
        $quantityValidation = $this->validateQuantityLimit($repeatOrderQuantity, $originalQuantity);
        if (! $quantityValidation['valid']) {
            $errors[] = $quantityValidation['error'];
        }

        // Validate unit price (must be equal or lower)
        $priceValidation = $this->validateUnitPrice($repeatOrderUnitPrice, $originalUnitPrice);
        if (! $priceValidation['valid']) {
            $errors[] = $priceValidation['error'];
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Check if procurement was awarded through competitive bidding
     * Repeat Order requires original contract to be from bidding (not alternative mode)
     */
    public function wasAwardedThroughBidding(ProcurementData $procurement): bool
    {
        // Competitive Bidding is the only "bidding" mode
        // All others are alternative modes and cannot be used as basis for Repeat Order
        return $procurement->procurementMode === ProcurementModeEnums::COMPETITIVE_BIDDING;
    }

    /**
     * Validate time limit per NGPA Section 33.2.b
     * Must be availed within 6 months from date of NTP
     *
     * @return array{valid: bool, error?: string, remaining_days?: int}
     */
    public function validateTimeLimit(ProcurementData $originalProcurement): array
    {
        // For now, we use approval date as a proxy for NTP date
        // In a complete implementation, you'd track NTP date separately
        $ntpDate = $originalProcurement->approvalDate;

        if (! $ntpDate) {
            return [
                'valid' => false,
                'error' => 'Cannot determine NTP date of original contract. Original procurement must be fully awarded.',
            ];
        }

        $deadlineDate = $ntpDate->copy()->addMonths(self::MAX_MONTHS_FROM_NTP);
        $now = Carbon::now();

        if ($now->gt($deadlineDate)) {
            $daysOverdue = $now->diffInDays($deadlineDate);

            return [
                'valid' => false,
                'error' => sprintf(
                    'Repeat Order deadline exceeded by %d days. Per NGPA Section 33.2.b, Repeat Orders must be availed within %d months from NTP date (%s). Deadline was: %s',
                    $daysOverdue,
                    self::MAX_MONTHS_FROM_NTP,
                    $ntpDate->format('F j, Y'),
                    $deadlineDate->format('F j, Y')
                ),
            ];
        }

        return [
            'valid' => true,
            'remaining_days' => $now->diffInDays($deadlineDate),
        ];
    }

    /**
     * Validate quantity limit per NGPA Section 33.2.c
     * Cannot exceed 25% of quantity from original contract
     *
     * @return array{valid: bool, error?: string, percentage?: float}
     */
    public function validateQuantityLimit(float $repeatOrderQuantity, float $originalQuantity): array
    {
        if ($originalQuantity <= 0) {
            return [
                'valid' => false,
                'error' => 'Original contract quantity must be greater than zero.',
            ];
        }

        $maxAllowedQuantity = $originalQuantity * (self::MAX_QUANTITY_PERCENTAGE / 100);
        $percentage = ($repeatOrderQuantity / $originalQuantity) * 100;

        if ($repeatOrderQuantity > $maxAllowedQuantity) {
            return [
                'valid' => false,
                'error' => sprintf(
                    'Repeat Order quantity (%.2f) exceeds %.0f%% of original quantity (%.2f). Per NGPA Section 33.2.c, maximum allowed: %.2f units (current: %.2f%%)',
                    $repeatOrderQuantity,
                    self::MAX_QUANTITY_PERCENTAGE,
                    $originalQuantity,
                    $maxAllowedQuantity,
                    $percentage
                ),
                'percentage' => $percentage,
            ];
        }

        return [
            'valid' => true,
            'percentage' => $percentage,
        ];
    }

    /**
     * Validate unit price per NGPA Section 33.2.a
     * Unit price must be equal to or lower than original contract
     *
     * @return array{valid: bool, error?: string}
     */
    public function validateUnitPrice(float $repeatOrderUnitPrice, float $originalUnitPrice): array
    {
        if ($repeatOrderUnitPrice > $originalUnitPrice) {
            return [
                'valid' => false,
                'error' => sprintf(
                    'Repeat Order unit price (₱%s) exceeds original contract price (₱%s). Per NGPA Section 33.2.a, unit price must be equal to or lower than original contract and prevailing market price.',
                    number_format($repeatOrderUnitPrice, 2),
                    number_format($originalUnitPrice, 2)
                ),
            ];
        }

        return ['valid' => true];
    }

    /**
     * Get eligibility status for Repeat Order from an original procurement
     * Useful for UI to show if a procurement can be used as basis for Repeat Order
     *
     * @return array{
     *     eligible: bool,
     *     reason?: string,
     *     deadline?: string,
     *     remaining_days?: int,
     *     max_quantity_percentage: int
     * }
     */
    public function getRepeatOrderEligibility(string $originalPrNumber): array
    {
        $originalProcurement = $this->procurementRepository->findByProcurement($originalPrNumber);

        if (! $originalProcurement) {
            return [
                'eligible' => false,
                'reason' => 'Procurement not found.',
                'max_quantity_percentage' => self::MAX_QUANTITY_PERCENTAGE,
            ];
        }

        if (! $this->wasAwardedThroughBidding($originalProcurement)) {
            return [
                'eligible' => false,
                'reason' => 'Original contract must have been awarded through competitive bidding.',
                'max_quantity_percentage' => self::MAX_QUANTITY_PERCENTAGE,
            ];
        }

        $timeLimitValidation = $this->validateTimeLimit($originalProcurement);

        if (! $timeLimitValidation['valid']) {
            return [
                'eligible' => false,
                'reason' => $timeLimitValidation['error'] ?? 'Time limit exceeded.',
                'max_quantity_percentage' => self::MAX_QUANTITY_PERCENTAGE,
            ];
        }

        return [
            'eligible' => true,
            'deadline' => $originalProcurement->approvalDate
                ?->copy()
                ->addMonths(self::MAX_MONTHS_FROM_NTP)
                ->format('F j, Y'),
            'remaining_days' => $timeLimitValidation['remaining_days'] ?? 0,
            'max_quantity_percentage' => self::MAX_QUANTITY_PERCENTAGE,
        ];
    }
}
