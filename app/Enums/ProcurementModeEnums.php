<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Philippine Government Procurement Modes (RA 12009 - NGPA)
 *
 * Reference: NGPA IRR Rule IV, Section 26
 * Fully aligned with RA 12009 (New Government Procurement Act)
 *
 * @see ngpa/rule-04-modes-of-procurement.md
 */
enum ProcurementModeEnums: string
{
    // ═══════════════════════════════════════════════════════════════════
    // COMPETITIVE MODES (Require full bidding process)
    // Per NGPA IRR Section 26.4: Cannot be delegated to End-User
    // ═══════════════════════════════════════════════════════════════════
    case COMPETITIVE_BIDDING = 'competitive_bidding';                             // Section 27
    case LIMITED_SOURCE_BIDDING = 'limited_source_bidding';                       // Section 28
    case COMPETITIVE_DIALOGUE = 'competitive_dialogue';                           // Section 29
    case UNSOLICITED_OFFER_WITH_BID_MATCHING = 'unsolicited_offer_with_bid_matching'; // Section 30

    // ═══════════════════════════════════════════════════════════════════
    // ALTERNATIVE MODES (Simplified procedures)
    // Per NGPA IRR Section 26.4: May be delegated to End-User or Procurement Unit
    // ═══════════════════════════════════════════════════════════════════
    case DIRECT_CONTRACTING = 'direct_contracting';                               // Section 31
    case DIRECT_ACQUISITION = 'direct_acquisition';                               // Section 32 (≤₱200,000)
    case REPEAT_ORDER = 'repeat_order';                                           // Section 33
    case SMALL_VALUE_PROCUREMENT = 'small_value_procurement';                     // Section 34 (≤₱400K for 4th class municipality)
    case NEGOTIATED_PROCUREMENT = 'negotiated_procurement';                       // Section 35
    case DIRECT_SALES = 'direct_sales';                                           // Section 36
    case DIRECT_PROCUREMENT_FOR_STI = 'direct_procurement_for_sti';               // Section 37

    /**
     * Get the official name as stated in the NGPA IRR
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::COMPETITIVE_BIDDING => 'Competitive Bidding',
            self::LIMITED_SOURCE_BIDDING => 'Limited Source Bidding',
            self::COMPETITIVE_DIALOGUE => 'Competitive Dialogue',
            self::UNSOLICITED_OFFER_WITH_BID_MATCHING => 'Unsolicited Offer with Bid Matching',
            self::DIRECT_CONTRACTING => 'Direct Contracting',
            self::DIRECT_ACQUISITION => 'Direct Acquisition',
            self::REPEAT_ORDER => 'Repeat Order',
            self::SMALL_VALUE_PROCUREMENT => 'Small Value Procurement',
            self::NEGOTIATED_PROCUREMENT => 'Negotiated Procurement',
            self::DIRECT_SALES => 'Direct Sales',
            self::DIRECT_PROCUREMENT_FOR_STI => 'Direct Procurement for Science, Technology and Innovation',
        };
    }

    /**
     * Alias for getDisplayName() for convenience
     */
    public function label(): string
    {
        return $this->getDisplayName();
    }

    /**
     * Get detailed description with IRR reference
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::COMPETITIVE_BIDDING => 'Open to participation by any eligible bidder through publication, pre-bid conference, eligibility screening, bid opening, evaluation, post-qualification, and award (NGPA IRR Sec. 27)',
            self::LIMITED_SOURCE_BIDDING => 'Direct invitation to pre-selected suppliers with known experience and proven capability for highly specialized goods or services (NGPA IRR Sec. 28)',
            self::COMPETITIVE_DIALOGUE => 'Two-stage process where Procuring Entity invites dialogue to propose solutions for complex or innovative procurement needs (NGPA IRR Sec. 29)',
            self::UNSOLICITED_OFFER_WITH_BID_MATCHING => 'Consideration of unsolicited offers on negotiated basis with bid matching from other bidders (NGPA IRR Sec. 30)',
            self::DIRECT_CONTRACTING => 'Procurement of proprietary goods, critical components from specific manufacturer, or from exclusive dealer (NGPA IRR Sec. 31)',
            self::DIRECT_ACQUISITION => 'Procurement of CSE not available in PS-DBM, Non-CSE, and services with ABC not exceeding ₱200,000 (NGPA IRR Sec. 32)',
            self::REPEAT_ORDER => 'Procurement from previous winning bidder, ≤25% of original quantity, within 6 months from NTP (NGPA IRR Sec. 33)',
            self::SMALL_VALUE_PROCUREMENT => 'Request for at least 3 price quotations; 1 quotation sufficient if within threshold. Threshold: ₱400,000 for 4th class municipality (NGPA IRR Sec. 34)',
            self::NEGOTIATED_PROCUREMENT => 'Direct negotiation for two failed biddings, emergencies, take-over contracts, agency-to-agency, and other special cases (NGPA IRR Sec. 35)',
            self::DIRECT_SALES => 'Direct purchase from supplier that satisfactorily delivered Non-CSE to another government agency within 6 months (NGPA IRR Sec. 36)',
            self::DIRECT_PROCUREMENT_FOR_STI => 'Procurement of R&D supplies, materials, equipment, and commissioned products for science, technology and innovation (NGPA IRR Sec. 37)',
        };
    }

    /**
     * Get the IRR section reference for this mode
     */
    public function getIrrSection(): string
    {
        return match ($this) {
            self::COMPETITIVE_BIDDING => 'Section 27',
            self::LIMITED_SOURCE_BIDDING => 'Section 28',
            self::COMPETITIVE_DIALOGUE => 'Section 29',
            self::UNSOLICITED_OFFER_WITH_BID_MATCHING => 'Section 30',
            self::DIRECT_CONTRACTING => 'Section 31',
            self::DIRECT_ACQUISITION => 'Section 32',
            self::REPEAT_ORDER => 'Section 33',
            self::SMALL_VALUE_PROCUREMENT => 'Section 34',
            self::NEGOTIATED_PROCUREMENT => 'Section 35',
            self::DIRECT_SALES => 'Section 36',
            self::DIRECT_PROCUREMENT_FOR_STI => 'Section 37',
        };
    }

    /**
     * Get threshold amount for modes with ABC limits
     * Per NGPA IRR (RA 12009)
     *
     * Note: Municipality of Gloria, Oriental Mindoro is a 4th Class Municipality
     * SVP Threshold per Section 34.2: ₱400,000 for 4th class municipalities
     */
    public function thresholdAmount(): ?float
    {
        return match ($this) {
            self::DIRECT_ACQUISITION => 200000.00,       // ₱200,000 per Section 32
            self::SMALL_VALUE_PROCUREMENT => 400000.00,  // ₱400,000 for 4th class municipality per Section 34.2
            default => null,
        };
    }

    /**
     * Check if this mode requires PhilGEPS posting
     * Competitive modes require publication per NGPA IRR
     */
    public function requiresPhilGEPS(): bool
    {
        return match ($this) {
            self::COMPETITIVE_BIDDING,
            self::LIMITED_SOURCE_BIDDING,
            self::COMPETITIVE_DIALOGUE,
            self::UNSOLICITED_OFFER_WITH_BID_MATCHING => true,
            default => false,
        };
    }

    /**
     * Check if this mode requires BAC Resolution
     */
    public function requiresBACResolution(): bool
    {
        return match ($this) {
            self::DIRECT_CONTRACTING,
            self::NEGOTIATED_PROCUREMENT,
            self::DIRECT_SALES,
            self::DIRECT_PROCUREMENT_FOR_STI => true,
            default => false,
        };
    }

    /**
     * Check if this mode can be delegated to End-User per Section 26.4
     *
     * "Except for Competitive Bidding, Limited Source Bidding, Competitive Dialogue,
     * and Unsolicited Offer with Bid Matching, the BAC may delegate the conduct of
     * procurement activities for the other modes of procurement to the End-User or
     * Implementing Unit, or the Procurement Unit of the Procuring Entity."
     */
    public function canBeDelegated(): bool
    {
        return match ($this) {
            // Cannot be delegated (require full BAC process)
            self::COMPETITIVE_BIDDING,
            self::LIMITED_SOURCE_BIDDING,
            self::COMPETITIVE_DIALOGUE,
            self::UNSOLICITED_OFFER_WITH_BID_MATCHING => false,
            // Can be delegated to End-User or Procurement Unit
            default => true,
        };
    }

    /**
     * Check if this is an alternative (non-competitive) procurement mode per NGPA IRR Sections 31-37.
     */
    public function isAlternativeMode(): bool
    {
        return match ($this) {
            // Competitive modes - Full bidding process
            self::COMPETITIVE_BIDDING,
            self::LIMITED_SOURCE_BIDDING,
            self::COMPETITIVE_DIALOGUE,
            self::UNSOLICITED_OFFER_WITH_BID_MATCHING => false,
            // Alternative modes - Simplified process
            default => true,
        };
    }

    /**
     * Check if this is a competitive procurement mode
     */
    public function isCompetitiveMode(): bool
    {
        return ! $this->isAlternativeMode();
    }

    /**
     * Check if the given ABC amount is valid for this procurement mode
     */
    public function isValidAmount(float $amount): bool
    {
        $threshold = $this->thresholdAmount();

        // No threshold = any amount is valid
        if ($threshold === null) {
            return true;
        }

        // Amount must not exceed threshold for modes with limits
        return $amount <= $threshold;
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

        return '≤ ₱'.number_format($threshold, 2);
    }

    /**
     * Get suggested procurement mode based on ABC amount
     * Per NGPA IRR thresholds for 4th class municipality
     */
    public static function suggestModeForAmount(float $amount): self
    {
        return match (true) {
            $amount <= 400000 => self::SMALL_VALUE_PROCUREMENT, // ₱400,000 for 4th class municipality
            default => self::COMPETITIVE_BIDDING,
        };
    }

    /**
     * Get all competitive modes (require full bidding process)
     *
     * @return array<self>
     */
    public static function competitiveModes(): array
    {
        return [
            self::COMPETITIVE_BIDDING,
            self::LIMITED_SOURCE_BIDDING,
            self::COMPETITIVE_DIALOGUE,
            self::UNSOLICITED_OFFER_WITH_BID_MATCHING,
        ];
    }

    /**
     * Get all alternative modes (simplified procedures)
     *
     * @return array<self>
     */
    public static function alternativeModes(): array
    {
        return [
            self::DIRECT_CONTRACTING,
            self::DIRECT_ACQUISITION,
            self::REPEAT_ORDER,
            self::SMALL_VALUE_PROCUREMENT,
            self::NEGOTIATED_PROCUREMENT,
            self::DIRECT_SALES,
            self::DIRECT_PROCUREMENT_FOR_STI,
        ];
    }

    /**
     * Get Negotiated Procurement sub-types applicable for Municipality of Gloria
     * Per Section 35 of NGPA IRR
     *
     * @return array<string, string>
     */
    public static function negotiatedProcurementSubTypes(): array
    {
        return [
            'two_failed_biddings' => 'Two Failed Biddings',
            'emergency_cases' => 'Emergency Cases',
            'take_over_of_contracts' => 'Take-over of Contracts',
            'agency_to_agency' => 'Agency-to-Agency',
            'lease_of_real_property' => 'Lease of Real Property and Venue',
            'ngo_participation' => 'NGO Participation',
            'community_participation' => 'Community Participation',
            'direct_retail_purchase' => 'Direct Retail Purchase of POL Products and Online Subscriptions',
        ];
    }

    /**
     * Check if video recording is required based on category and ABC amount
     * Per Section 38.3 thresholds
     *
     * @param  string  $category  'goods', 'infrastructure', or 'consulting'
     * @param  float  $abcAmount  The Approved Budget for the Contract
     */
    public static function requiresVideoRecording(string $category, float $abcAmount): bool
    {
        return match ($category) {
            'goods' => $abcAmount > 10000000,           // Above ₱10,000,000
            'infrastructure' => $abcAmount > 20000000, // Above ₱20,000,000
            'consulting' => $abcAmount > 5000000,      // Above ₱5,000,000
            default => false,
        };
    }
}
