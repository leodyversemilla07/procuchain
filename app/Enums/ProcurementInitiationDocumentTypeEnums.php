<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Procurement Initiation Document Type Enums
 *
 * Defines required and optional documents for procurement initiation per RA 9184.
 * Based on IRR-A Section 7 requirements for procurement planning and initiation.
 */
enum ProcurementInitiationDocumentTypeEnums: string
{
    // MANDATORY DOCUMENTS per RA 9184 IRR-A
    case PURCHASE_REQUEST = 'purchase_request';
    case TECHNICAL_SPECIFICATIONS = 'technical_specifications';
    case TERMS_OF_REFERENCE = 'terms_of_reference';
    case CERTIFICATE_OF_FUNDS = 'certificate_of_funds';
    case PPMP_ENTRY = 'ppmp_entry';

    // OPTIONAL SUPPORTING DOCUMENTS
    case MARKET_RESEARCH = 'market_research';
    case PRICE_SURVEY = 'price_survey';
    case APPROVAL_DOCUMENTS = 'approval_documents';
    case END_USER_REQUEST = 'end_user_request';
    case DEPARTMENT_ENDORSEMENT = 'department_endorsement';
    case BUDGET_ALLOCATION = 'budget_allocation';
    case PROJECT_PROPOSAL = 'project_proposal';

    /**
     * Get display name for the document type
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::PURCHASE_REQUEST => 'Purchase Request (PR)',
            self::TECHNICAL_SPECIFICATIONS => 'Technical Specifications',
            self::TERMS_OF_REFERENCE => 'Terms of Reference (TOR)',
            self::CERTIFICATE_OF_FUNDS => 'Certificate of Availability of Funds',
            self::PPMP_ENTRY => 'PPMP Entry/Extract',
            self::MARKET_RESEARCH => 'Market Research',
            self::PRICE_SURVEY => 'Price Survey / Abstract of Quotations',
            self::APPROVAL_DOCUMENTS => 'Approval Documents',
            self::END_USER_REQUEST => 'End-User Request Letter',
            self::DEPARTMENT_ENDORSEMENT => 'Department Endorsement',
            self::BUDGET_ALLOCATION => 'Budget Allocation Document',
            self::PROJECT_PROPOSAL => 'Project Proposal',
        };
    }

    /**
     * Get detailed description of the document type
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::PURCHASE_REQUEST => 'Official Purchase Request form with PR number, signed by requesting officer and approved by department head',
            self::TECHNICAL_SPECIFICATIONS => 'Detailed technical specifications for goods or infrastructure projects including quality standards and performance requirements',
            self::TERMS_OF_REFERENCE => 'Terms of Reference for consulting services procurement outlining scope, deliverables, and qualifications',
            self::CERTIFICATE_OF_FUNDS => 'Certificate from Budget Officer confirming fund availability with ORS/Obligation number and fund source',
            self::PPMP_ENTRY => 'Extract from approved Project Procurement Management Plan showing this procurement with estimated cost and schedule',
            self::MARKET_RESEARCH => 'Market research supporting the ABC (Approved Budget for Contract) computation and procurement approach',
            self::PRICE_SURVEY => 'Price survey with at least 3 quotations or canvass results to support ABC amount',
            self::APPROVAL_DOCUMENTS => 'Department head approval, Sanggunian resolution for infrastructure, or higher authority approval for large amounts',
            self::END_USER_REQUEST => 'Request letter from end-user office detailing specific requirements and justification',
            self::DEPARTMENT_ENDORSEMENT => 'Endorsement letter from requesting department head approving the procurement request',
            self::BUDGET_ALLOCATION => 'Budget allocation document showing line item and appropriation for the procurement',
            self::PROJECT_PROPOSAL => 'Detailed project proposal document outlining objectives, scope, and expected outcomes',
        };
    }

    /**
     * Check if document is mandatory for all procurements
     */
    public function isMandatory(): bool
    {
        return match ($this) {
            self::PURCHASE_REQUEST,
            self::CERTIFICATE_OF_FUNDS,
            self::PPMP_ENTRY => true,
            default => false,
        };
    }

    /**
     * Check if document is mandatory for specific procurement category
     */
    public function isMandatoryForCategory(ProcurementCategoryEnums $category): bool
    {
        return match ($this) {
            self::PURCHASE_REQUEST,
            self::CERTIFICATE_OF_FUNDS,
            self::PPMP_ENTRY => true,

            self::TECHNICAL_SPECIFICATIONS => in_array($category, [
                ProcurementCategoryEnums::GOODS,
                ProcurementCategoryEnums::INFRASTRUCTURE_PROJECTS,
            ]),

            self::TERMS_OF_REFERENCE => $category === ProcurementCategoryEnums::CONSULTING_SERVICES,

            default => false,
        };
    }

    /**
     * Check if document is applicable for specific category
     */
    public function isApplicableForCategory(ProcurementCategoryEnums $category): bool
    {
        return match ($this) {
            self::TECHNICAL_SPECIFICATIONS => in_array($category, [
                ProcurementCategoryEnums::GOODS,
                ProcurementCategoryEnums::INFRASTRUCTURE_PROJECTS,
            ]),

            self::TERMS_OF_REFERENCE => $category === ProcurementCategoryEnums::CONSULTING_SERVICES,

            // All other documents apply to all categories
            default => true,
        };
    }

    /**
     * Get all mandatory documents for a specific category
     *
     * @return array<self>
     */
    public static function getMandatoryForCategory(ProcurementCategoryEnums $category): array
    {
        return array_filter(
            self::cases(),
            fn (self $docType) => $docType->isMandatoryForCategory($category)
        );
    }

    /**
     * Get all applicable documents for a specific category
     *
     * @return array<self>
     */
    public static function getApplicableForCategory(ProcurementCategoryEnums $category): array
    {
        return array_filter(
            self::cases(),
            fn (self $docType) => $docType->isApplicableForCategory($category)
        );
    }

    /**
     * Get all mandatory document types (applies to all categories)
     *
     * @return array<self>
     */
    public static function getAllMandatory(): array
    {
        return array_filter(
            self::cases(),
            fn (self $docType) => $docType->isMandatory()
        );
    }

    /**
     * Get all optional document types
     *
     * @return array<self>
     */
    public static function getAllOptional(): array
    {
        return array_filter(
            self::cases(),
            fn (self $docType) => ! $docType->isMandatory()
        );
    }

    /**
     * Get document type requirements summary
     */
    public function getRequirementSummary(): string
    {
        if ($this->isMandatory()) {
            return 'Required for all procurements per RA 9184';
        }

        $categories = [];
        foreach (ProcurementCategoryEnums::cases() as $category) {
            if ($this->isMandatoryForCategory($category)) {
                $categories[] = $category->getDisplayName();
            }
        }

        if (! empty($categories)) {
            return 'Required for: '.implode(', ', $categories);
        }

        return 'Optional supporting document';
    }

    /**
     * Get all cases as array of values
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all cases as array of display names
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
