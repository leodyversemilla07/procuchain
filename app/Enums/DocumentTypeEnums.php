<?php

namespace App\Enums;

/**
 * Document Type Enum
 *
 * Represents all possible document types in the procurement system.
 * Each document type is associated with a specific procurement stage.
 */
enum DocumentTypeEnums: string
{
    // Procurement Initiation
    case PROCUREMENT_INITIATION_DOCUMENT = 'procurement_initiation_document';

    // Pre-Procurement Conference
    case PRE_PROCUREMENT_MINUTES = 'pre_procurement_minutes';
    case PRE_PROCUREMENT_ATTENDANCE = 'pre_procurement_attendance';

    // Bidding Documents
    case BIDDING_DOCUMENT = 'bidding_document';

    // Pre-Bid Conference
    case PRE_BID_MINUTES = 'pre_bid_minutes';
    case PRE_BID_ATTENDANCE = 'pre_bid_attendance';

    // Supplemental Bid Bulletin
    case SUPPLEMENTAL_BID_BULLETIN = 'supplemental_bid_bulletin';

    // Bid Opening
    case BID_DOCUMENT = 'bid_document';

    // Bid Evaluation
    case EVALUATION_SUMMARY = 'evaluation_summary';
    case ABSTRACT = 'abstract';

    // Post Qualification
    case POST_QUALIFICATION_REPORT = 'post_qualification_report';
    case TWG_CERTIFICATION = 'twg_certification';
    case NOTICE_OF_POST_QUALIFICATION = 'notice_of_post_qualification';

    // BAC Resolution
    case BAC_RESOLUTION = 'bac_resolution';

    // Notice of Award
    case NOTICE_OF_AWARD = 'notice_of_award';

    // Performance Bond, Contract & PO
    case PERFORMANCE_BOND = 'performance_bond';
    case CONTRACT = 'contract';
    case PURCHASE_ORDER = 'purchase_order';

    // Notice to Proceed
    case NOTICE_TO_PROCEED = 'notice_to_proceed';

    // Monitoring
    case COMPLIANCE_REPORT = 'compliance_report';

    // Completion
    case CERTIFICATE_OF_COMPLETION = 'certificate_of_completion';

    // Unknown/Fallback
    case UNKNOWN = 'unknown';

    /**
     * Get the user-friendly display name for the document type
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::PROCUREMENT_INITIATION_DOCUMENT => 'Procurement Initiation Document',
            self::PRE_PROCUREMENT_MINUTES => 'Pre-Procurement Conference Minutes',
            self::PRE_PROCUREMENT_ATTENDANCE => 'Pre-Procurement Conference Attendance',
            self::BIDDING_DOCUMENT => 'Bidding Document',
            self::PRE_BID_MINUTES => 'Pre-Bid Conference Minutes',
            self::PRE_BID_ATTENDANCE => 'Pre-Bid Conference Attendance',
            self::SUPPLEMENTAL_BID_BULLETIN => 'Supplemental Bid Bulletin',
            self::BID_DOCUMENT => 'Bid Document',
            self::EVALUATION_SUMMARY => 'Evaluation Summary',
            self::ABSTRACT => 'Abstract',
            self::POST_QUALIFICATION_REPORT => 'Post-Qualification Report',
            self::TWG_CERTIFICATION => 'TWG Certification',
            self::NOTICE_OF_POST_QUALIFICATION => 'Notice of Post-Qualification',
            self::BAC_RESOLUTION => 'BAC Resolution',
            self::NOTICE_OF_AWARD => 'Notice of Award',
            self::PERFORMANCE_BOND => 'Performance Bond',
            self::CONTRACT => 'Contract',
            self::PURCHASE_ORDER => 'Purchase Order',
            self::NOTICE_TO_PROCEED => 'Notice to Proceed',
            self::COMPLIANCE_REPORT => 'Compliance Report',
            self::CERTIFICATE_OF_COMPLETION => 'Certificate of Completion',
            self::UNKNOWN => 'Unknown Document',
        };
    }

    /**
     * Get a short description of the document type
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::PROCUREMENT_INITIATION_DOCUMENT => 'Initial procurement documentation',
            self::PRE_PROCUREMENT_MINUTES => 'Minutes from pre-procurement conference',
            self::PRE_PROCUREMENT_ATTENDANCE => 'Attendance record for pre-procurement conference',
            self::BIDDING_DOCUMENT => 'Official bidding documents',
            self::PRE_BID_MINUTES => 'Minutes from pre-bid conference',
            self::PRE_BID_ATTENDANCE => 'Attendance record for pre-bid conference',
            self::SUPPLEMENTAL_BID_BULLETIN => 'Supplemental or amended bidding information',
            self::BID_DOCUMENT => 'Submitted bid documents',
            self::EVALUATION_SUMMARY => 'Summary of bid evaluation',
            self::ABSTRACT => 'Abstract of bids',
            self::POST_QUALIFICATION_REPORT => 'Post-qualification assessment report',
            self::TWG_CERTIFICATION => 'Technical Working Group certification',
            self::NOTICE_OF_POST_QUALIFICATION => 'Notice of post-qualification results',
            self::BAC_RESOLUTION => 'Bids and Awards Committee resolution',
            self::NOTICE_OF_AWARD => 'Official notice of contract award',
            self::PERFORMANCE_BOND => 'Performance security bond',
            self::CONTRACT => 'Signed contract agreement',
            self::PURCHASE_ORDER => 'Official purchase order',
            self::NOTICE_TO_PROCEED => 'Authorization to begin work',
            self::COMPLIANCE_REPORT => 'Project compliance monitoring report',
            self::CERTIFICATE_OF_COMPLETION => 'Certificate of project completion',
            self::UNKNOWN => 'Unknown or unspecified document type',
        };
    }

    /**
     * Try to match a string value to a DocumentTypeEnums case
     * Handles various formats: snake_case, Title Case, kebab-case, etc.
     *
     * @param  string  $value  The value to match
     * @return self|null The matched enum case or null if no match found
     */
    public static function fromString(string $value): ?self
    {
        // Try direct match first
        $enum = self::tryFrom($value);
        if ($enum) {
            return $enum;
        }

        // Normalize the input value (convert to lowercase and replace spaces/hyphens with underscores)
        $normalized = strtolower(str_replace([' ', '-'], '_', $value));

        // Try matching normalized value
        $enum = self::tryFrom($normalized);
        if ($enum) {
            return $enum;
        }

        // Try matching by display name (case-insensitive)
        foreach (self::cases() as $case) {
            if (strtolower($case->getDisplayName()) === strtolower($value)) {
                return $case;
            }
        }

        // Try matching common variations and legacy values
        $mappings = [
            // Stage names that might be used as document types
            'procurement_initiation' => self::PROCUREMENT_INITIATION_DOCUMENT,

            // Common variations
            'minutes' => self::PRE_BID_MINUTES, // Default to most common
            'attendance' => self::PRE_BID_ATTENDANCE, // Default to most common
            'evaluation summary' => self::EVALUATION_SUMMARY,
            'abstract' => self::ABSTRACT,
            'post qualification report' => self::POST_QUALIFICATION_REPORT,
            'twg certification' => self::TWG_CERTIFICATION,
            'notice of post qualification' => self::NOTICE_OF_POST_QUALIFICATION,
            'bac resolution' => self::BAC_RESOLUTION,
            'notice of award' => self::NOTICE_OF_AWARD,
            'performance bond' => self::PERFORMANCE_BOND,
            'contract' => self::CONTRACT,
            'purchase order' => self::PURCHASE_ORDER,
            'notice to proceed' => self::NOTICE_TO_PROCEED,
            'compliance report' => self::COMPLIANCE_REPORT,
            'certificate of completion' => self::CERTIFICATE_OF_COMPLETION,
            'supplemental bid bulletin' => self::SUPPLEMENTAL_BID_BULLETIN,
            'bid document' => self::BID_DOCUMENT,
            'bidding document' => self::BIDDING_DOCUMENT,
        ];

        $lowerValue = strtolower($value);
        if (isset($mappings[$lowerValue])) {
            return $mappings[$lowerValue];
        }

        return null;
    }

    /**
     * Get all cases as an array of values
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all cases as an array of display names
     *
     * @return array<string, string> [value => display_name]
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getDisplayName();
        }

        return $options;
    }
}
