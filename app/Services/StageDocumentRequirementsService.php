<?php

namespace App\Services;

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Support\StageDocumentRequirementsConfig;

/**
 * Stage Document Requirements Service
 *
 * Maps required and optional documents to each procurement stage
 * Based on RA 12009 (NGPA) requirements
 */
class StageDocumentRequirementsService
{
    /**
     * Get required documents for a specific stage
     *
     * @return array<DocumentTypeEnums>
     */
    public function getRequiredDocuments(StageEnums $stage): array
    {
        return match ($stage) {
            StageEnums::PROCUREMENT_INITIATION => StageDocumentRequirementsConfig::getProcurementInitiationRequirements(),
            StageEnums::PRE_PROCUREMENT_CONFERENCE => StageDocumentRequirementsConfig::getPreProcurementConferenceRequirements(),
            StageEnums::BIDDING_DOCUMENTS => StageDocumentRequirementsConfig::getBiddingDocumentsRequirements(),
            StageEnums::PRE_BID_CONFERENCE => StageDocumentRequirementsConfig::getPreBidConferenceRequirements(),
            StageEnums::SUPPLEMENTAL_BID_BULLETIN => StageDocumentRequirementsConfig::getSupplementalBidBulletinRequirements(),
            StageEnums::BID_OPENING => StageDocumentRequirementsConfig::getBidOpeningRequirements(),
            StageEnums::BID_EVALUATION => StageDocumentRequirementsConfig::getBidEvaluationRequirements(),
            StageEnums::POST_QUALIFICATION => StageDocumentRequirementsConfig::getPostQualificationRequirements(),
            StageEnums::BAC_RESOLUTION => StageDocumentRequirementsConfig::getBacResolutionRequirements(),
            StageEnums::NOTICE_OF_AWARD => StageDocumentRequirementsConfig::getNoticeOfAwardRequirements(),
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO => StageDocumentRequirementsConfig::getPerformanceBondRequirements(),
            StageEnums::NOTICE_TO_PROCEED => StageDocumentRequirementsConfig::getNoticeToProceedRequirements(),
            StageEnums::MONITORING => StageDocumentRequirementsConfig::getMonitoringRequirements(),
            StageEnums::COMPLETION => StageDocumentRequirementsConfig::getCompletionRequirements(),
            StageEnums::COMPLETED => [],
            StageEnums::REQUEST_FOR_QUOTATION => StageDocumentRequirementsConfig::getRequestForQuotationRequirements(),
            StageEnums::ABSTRACT_OF_QUOTATIONS => StageDocumentRequirementsConfig::getAbstractOfQuotationsRequirements(),
        };
    }

    /**
     * Get optional documents for a specific stage
     *
     * @return array<DocumentTypeEnums>
     */
    public function getOptionalDocuments(StageEnums $stage): array
    {
        return match ($stage) {
            StageEnums::PROCUREMENT_INITIATION => [],  // All documents combined into single PDF
            StageEnums::PRE_PROCUREMENT_CONFERENCE => [
                DocumentTypeEnums::PRE_PROCUREMENT_PRESENTATION,
                DocumentTypeEnums::PRE_PROCUREMENT_QA_LOG,
            ],
            StageEnums::BIDDING_DOCUMENTS => [
                DocumentTypeEnums::NEWSPAPER_ADVERTISEMENT,
            ],
            StageEnums::PRE_BID_CONFERENCE => [
                DocumentTypeEnums::PRE_BID_RECORDING,
                DocumentTypeEnums::PRE_BID_PRESENTATION,
            ],
            StageEnums::SUPPLEMENTAL_BID_BULLETIN => [],
            StageEnums::BID_OPENING => [
                DocumentTypeEnums::BID_OPENING_RECORDING,
                DocumentTypeEnums::PHILGEPS_PLATINUM_CERTIFICATE,
            ],
            StageEnums::BID_EVALUATION => [],
            StageEnums::POST_QUALIFICATION => [],
            StageEnums::BAC_RESOLUTION => [],
            StageEnums::NOTICE_OF_AWARD => [
                DocumentTypeEnums::LEGAL_OFFICER_CERTIFICATE,
            ],
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO => [
                DocumentTypeEnums::PERFORMANCE_SECURING_DECLARATION,
                DocumentTypeEnums::JOB_ORDER,
                DocumentTypeEnums::CONTRACT_SB_RESOLUTION,
                DocumentTypeEnums::INSURANCE_POLICIES,
                DocumentTypeEnums::CONTRACTORS_ALL_RISK,
                DocumentTypeEnums::WARRANTY_SECURITY,
            ],
            StageEnums::NOTICE_TO_PROCEED => [
                DocumentTypeEnums::EQUIPMENT_LIST,
                DocumentTypeEnums::BARANGAY_ENDORSEMENT,
                DocumentTypeEnums::CONSTRUCTION_PERMIT,
            ],
            StageEnums::MONITORING => [],
            StageEnums::COMPLETION => [],
            StageEnums::COMPLETED => [
                DocumentTypeEnums::POST_IMPLEMENTATION_REVIEW,
                DocumentTypeEnums::COA_AUDIT_DOCUMENTATION,
                DocumentTypeEnums::WARRANTY_CLAIM_RECORDS,
                DocumentTypeEnums::ASSET_MANAGEMENT_RECORDS,
            ],
            // Small Value Procurement & Alternative Methods stages
            StageEnums::REQUEST_FOR_QUOTATION => [
                DocumentTypeEnums::SUPPLIER_CANVASS_FORM,
                DocumentTypeEnums::QUOTATION_COMPARISON_SHEET,
            ],
            StageEnums::ABSTRACT_OF_QUOTATIONS => [
                DocumentTypeEnums::LOWEST_QUOTATION_CERTIFICATION,
            ],
        };
    }

    /**
     * Get document counts for a specific stage
     */
    public function getDocumentCounts(StageEnums $stage): array
    {
        $required = $this->getRequiredDocuments($stage);
        $optional = $this->getOptionalDocuments($stage);

        return [
            'required_count' => count($required),
            'optional_count' => count($optional),
            'total_count' => count($required) + count($optional),
        ];
    }

    /**
     * Check if minimum required documents are uploaded
     */
    public function hasMinimumRequiredDocuments(StageEnums $stage, array $uploadedTypes): bool
    {
        $required = $this->getRequiredDocuments($stage);

        foreach ($required as $requiredDoc) {
            $found = false;
            foreach ($uploadedTypes as $uploadedDoc) {
                if ($uploadedDoc === $requiredDoc) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get missing required documents
     *
     * @return array<DocumentTypeEnums>
     */
    public function getMissingDocuments(StageEnums $stage, array $uploadedTypes): array
    {
        $required = $this->getRequiredDocuments($stage);
        $missing = [];

        foreach ($required as $requiredDoc) {
            $found = false;
            foreach ($uploadedTypes as $uploadedDoc) {
                if ($uploadedDoc === $requiredDoc) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $missing[] = $requiredDoc;
            }
        }

        return $missing;
    }
}
