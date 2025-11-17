<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Philippine Government Procurement Modes (RA 9184)
 *
 * Reference: GPPB-TSO - Alternative Methods of Procurement
 */
enum ProcurementModeEnums: string
{
    case PUBLIC_BIDDING = 'public_bidding';
    case LIMITED_SOURCE_BIDDING = 'limited_source_bidding';
    case DIRECT_CONTRACTING = 'direct_contracting';
    case REPEAT_ORDER = 'repeat_order';
    case SHOPPING = 'shopping';
    case NEGOTIATED_PROCUREMENT = 'negotiated_procurement';
    case SMALL_VALUE_PROCUREMENT = 'small_value_procurement';
    case EMERGENCY = 'emergency';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::PUBLIC_BIDDING => 'Public Bidding',
            self::LIMITED_SOURCE_BIDDING => 'Limited Source Bidding',
            self::DIRECT_CONTRACTING => 'Direct Contracting',
            self::REPEAT_ORDER => 'Repeat Order',
            self::SHOPPING => 'Shopping',
            self::NEGOTIATED_PROCUREMENT => 'Negotiated Procurement',
            self::SMALL_VALUE_PROCUREMENT => 'Small Value Procurement',
            self::EMERGENCY => 'Emergency Procurement',
        };
    }

    /**
     * Alias for getDisplayName() for convenience
     */
    public function label(): string
    {
        return $this->getDisplayName();
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::PUBLIC_BIDDING => 'Competitive bidding open to all interested parties (RA 9184 Art. XVI)',
            self::LIMITED_SOURCE_BIDDING => 'Bidding limited to pre-qualified suppliers (RA 9184 Sec. 49)',
            self::DIRECT_CONTRACTING => 'Direct engagement with single supplier (RA 9184 Sec. 50)',
            self::REPEAT_ORDER => 'Additional procurement from existing contract (RA 9184 Sec. 51.1)',
            self::SHOPPING => 'Procurement of readily available goods (RA 9184 Sec. 52.1)',
            self::NEGOTIATED_PROCUREMENT => 'Two Failed Biddings or emergency cases (RA 9184 Sec. 53)',
            self::SMALL_VALUE_PROCUREMENT => 'Simplified procurement ≤1M (GPPB Resolution 09-2020)',
            self::EMERGENCY => 'Imminent danger to life/property (RA 9184 Sec. 53.2)',
        };
    }

    public function thresholdAmount(): ?float
    {
        return match ($this) {
            self::SMALL_VALUE_PROCUREMENT => 1000000.00, // ₱1M per GPPB Resolution 09-2020
            self::SHOPPING => 500000.00, // ₱500K for goods, ₱350K for infrastructure
            default => null,
        };
    }

    public function requiresPhilGEPS(): bool
    {
        return match ($this) {
            self::PUBLIC_BIDDING,
            self::LIMITED_SOURCE_BIDDING => true,
            default => false,
        };
    }

    public function requiresBACResolution(): bool
    {
        return match ($this) {
            self::DIRECT_CONTRACTING,
            self::NEGOTIATED_PROCUREMENT,
            self::EMERGENCY => true,
            default => false,
        };
    }

    /**
     * Check if the given ABC amount is valid for this procurement mode (Issue #9 fix)
     */
    public function isValidAmount(float $amount): bool
    {
        $threshold = $this->thresholdAmount();

        // No threshold = any amount is valid
        if ($threshold === null) {
            return true;
        }

        // Amount must not exceed threshold for modes with limits
        return match ($this) {
            self::SHOPPING, self::SMALL_VALUE_PROCUREMENT => $amount <= $threshold,
            default => true,
        };
    }

    /**
     * Get the valid amount range description for this mode
     */
    public function getAmountRange(): string
    {
        $threshold = $this->thresholdAmount();

        if ($threshold === null) {
            return 'No amount limit';
        }

        return match ($this) {
            self::SHOPPING => '≤ ₱'.number_format($threshold, 2),
            self::SMALL_VALUE_PROCUREMENT => '≤ ₱'.number_format($threshold, 2),
            default => 'No specific limit',
        };
    }

    /**
     * Get suggested procurement mode based on ABC amount (Issue #9 enhancement)
     */
    public static function suggestModeForAmount(float $amount): self
    {
        return match (true) {
            $amount <= 500000 => self::SHOPPING,
            $amount <= 1000000 => self::SMALL_VALUE_PROCUREMENT,
            default => self::PUBLIC_BIDDING,
        };
    }
}
