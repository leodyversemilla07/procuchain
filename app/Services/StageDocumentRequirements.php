<?php

namespace App\Services;

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;

/**
 * Stage Document Requirements Service
 *
 * Maps required and optional documents to each procurement stage
 * Based on RA 9184 & RA 12009 requirements
 */
class StageDocumentRequirements
{
    /**
     * Get required documents for a specific stage
     *
     * @return array<DocumentTypeEnums>
     */
    public function getRequiredDocuments(StageEnums $stage): array
    {
        return match ($stage) {
            StageEnums::PROCUREMENT_INITIATION => $this->getProcurementInitiationRequirements(),
            StageEnums::PRE_PROCUREMENT_CONFERENCE => $this->getPreProcurementConferenceRequirements(),
            StageEnums::BIDDING_DOCUMENTS => $this->getBiddingDocumentsRequirements(),
            StageEnums::PRE_BID_CONFERENCE => $this->getPreBidConferenceRequirements(),
            StageEnums::SUPPLEMENTAL_BID_BULLETIN => $this->getSupplementalBidBulletinRequirements(),
            StageEnums::BID_OPENING => $this->getBidOpeningRequirements(),
            StageEnums::BID_EVALUATION => $this->getBidEvaluationRequirements(),
            StageEnums::POST_QUALIFICATION => $this->getPostQualificationRequirements(),
            StageEnums::BAC_RESOLUTION => $this->getBacResolutionRequirements(),
            StageEnums::NOTICE_OF_AWARD => $this->getNoticeOfAwardRequirements(),
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO => $this->getPerformanceBondRequirements(),
            StageEnums::NOTICE_TO_PROCEED => $this->getNoticeToProceedRequirements(),
            StageEnums::MONITORING => $this->getMonitoringRequirements(),
            StageEnums::COMPLETION => $this->getCompletionRequirements(),
            StageEnums::COMPLETED => [],
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
            StageEnums::PROCUREMENT_INITIATION => [
                DocumentTypeEnums::MARKET_RESEARCH,
                DocumentTypeEnums::SANGGUNIANG_BAYAN_RESOLUTION,
                DocumentTypeEnums::ENVIRONMENTAL_COMPLIANCE_CERTIFICATE,
                DocumentTypeEnums::PROGRAM_OF_WORK,
            ],
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
            ],
            StageEnums::BID_EVALUATION => [],
            StageEnums::POST_QUALIFICATION => [],
            StageEnums::BAC_RESOLUTION => [],
            StageEnums::NOTICE_OF_AWARD => [
                DocumentTypeEnums::LEGAL_OFFICER_CERTIFICATE,
            ],
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO => [
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

    // ==================================================================================
    // PRIVATE METHODS: Per-Stage Requirements
    // ==================================================================================

    /**
     * Stage 1: Procurement Initiation Requirements
     *
     * @return array<DocumentTypeEnums>
     */
    private function getProcurementInitiationRequirements(): array
    {
        return [
            DocumentTypeEnums::PURCHASE_REQUEST,
            DocumentTypeEnums::PPMP,
            DocumentTypeEnums::APP,
            DocumentTypeEnums::CERTIFICATE_OF_FUNDS,
            DocumentTypeEnums::APPROVED_BUDGET_CONTRACT,
            DocumentTypeEnums::TECHNICAL_SPECIFICATIONS,
        ];
    }

    /**
     * Stage 2: Pre-Procurement Conference Requirements
     *
     * @return array<DocumentTypeEnums>
     */
    private function getPreProcurementConferenceRequirements(): array
    {
        return [
            DocumentTypeEnums::PRE_PROCUREMENT_AGENDA,
            DocumentTypeEnums::PRE_PROCUREMENT_ATTENDANCE,
            DocumentTypeEnums::PRE_PROCUREMENT_MINUTES,
        ];
    }

    /**
     * Stage 3: Bidding Documents Requirements
     *
     * @return array<DocumentTypeEnums>
     */
    private function getBiddingDocumentsRequirements(): array
    {
        return [
            DocumentTypeEnums::INVITATION_TO_BID,
            DocumentTypeEnums::BID_DATA_SHEET,
            DocumentTypeEnums::INSTRUCTIONS_TO_BIDDERS,
            DocumentTypeEnums::GENERAL_CONDITIONS_CONTRACT,
            DocumentTypeEnums::SPECIAL_CONDITIONS_CONTRACT,
            DocumentTypeEnums::BIDDING_TECHNICAL_SPECIFICATIONS,
            DocumentTypeEnums::BIDDING_FORMS,
            DocumentTypeEnums::BAC_RESOLUTION_BIDDING_DOCS,
            DocumentTypeEnums::PHILGEPS_POSTING_RECEIPT,
            DocumentTypeEnums::WEBSITE_POSTING_PROOF,
        ];
    }

    /**
     * Stage 4: Pre-Bid Conference Requirements
     *
     * @return array<DocumentTypeEnums>
     */
    private function getPreBidConferenceRequirements(): array
    {
        return [
            DocumentTypeEnums::PRE_BID_AGENDA,
            DocumentTypeEnums::PRE_BID_ATTENDANCE,
            DocumentTypeEnums::PRE_BID_MINUTES,
        ];
    }

    /**
     * Stage 5: Supplemental Bid Bulletin Requirements
     *
     * @return array<DocumentTypeEnums>
     */
    private function getSupplementalBidBulletinRequirements(): array
    {
        return [
            DocumentTypeEnums::SUPPLEMENTAL_BID_BULLETIN,
            DocumentTypeEnums::BAC_RESOLUTION_BID_BULLETIN,
            DocumentTypeEnums::BID_BULLETIN_PHILGEPS,
            DocumentTypeEnums::BID_BULLETIN_NOTICE,
        ];
    }

    /**
     * Stage 6: Bid Opening Requirements
     *
     * @return array<DocumentTypeEnums>
     */
    private function getBidOpeningRequirements(): array
    {
        return [
            DocumentTypeEnums::BID_SUBMISSION_REGISTER,
            DocumentTypeEnums::SEALED_BID_PROPOSALS,
            DocumentTypeEnums::ABSTRACT_OF_BIDS,
            DocumentTypeEnums::BID_OPENING_MINUTES,
            DocumentTypeEnums::BID_OPENING_ATTENDANCE,
            DocumentTypeEnums::BIDDERS_ELIGIBILITY_DOCUMENTS,
            DocumentTypeEnums::BIDDERS_TECHNICAL_PROPOSALS,
            DocumentTypeEnums::BIDDERS_FINANCIAL_PROPOSALS,
            DocumentTypeEnums::BID_SECURITY,
        ];
    }

    /**
     * Stage 7: Bid Evaluation Requirements
     *
     * @return array<DocumentTypeEnums>
     */
    private function getBidEvaluationRequirements(): array
    {
        return [
            DocumentTypeEnums::TWG_RESOLUTION,
            DocumentTypeEnums::PRELIMINARY_EXAMINATION_REPORT,
            DocumentTypeEnums::TECHNICAL_EVALUATION_REPORT,
            DocumentTypeEnums::FINANCIAL_EVALUATION_REPORT,
            DocumentTypeEnums::COMPARATIVE_BID_ANALYSIS,
            DocumentTypeEnums::EVALUATION_MEETING_MINUTES,
            DocumentTypeEnums::BAC_RESOLUTION_EVALUATION,
        ];
    }

    /**
     * Stage 8: Post-Qualification Requirements
     *
     * @return array<DocumentTypeEnums>
     */
    private function getPostQualificationRequirements(): array
    {
        return [
            DocumentTypeEnums::POST_QUALIFICATION_REPORT,
            DocumentTypeEnums::SITE_VISIT_REPORT,
            DocumentTypeEnums::DOCUMENT_VERIFICATION_CHECKLIST,
            DocumentTypeEnums::FINANCIAL_CAPACITY_ASSESSMENT,
            DocumentTypeEnums::TECHNICAL_CAPACITY_ASSESSMENT,
            DocumentTypeEnums::BAC_RESOLUTION_POST_QUALIFICATION,
        ];
    }

    /**
     * Stage 9: BAC Resolution Requirements
     *
     * @return array<DocumentTypeEnums>
     */
    private function getBacResolutionRequirements(): array
    {
        return [
            DocumentTypeEnums::BAC_RESOLUTION_AWARD,
            DocumentTypeEnums::LCRB_NOTICE,
            DocumentTypeEnums::BID_EVALUATION_PACKAGE,
            DocumentTypeEnums::TRANSMITTAL_TO_HOPE,
            DocumentTypeEnums::AWARD_PHILGEPS_POSTING,
        ];
    }

    /**
     * Stage 10: Notice of Award Requirements
     *
     * @return array<DocumentTypeEnums>
     */
    private function getNoticeOfAwardRequirements(): array
    {
        return [
            DocumentTypeEnums::HOPE_APPROVAL,
            DocumentTypeEnums::NOTICE_OF_AWARD,
            DocumentTypeEnums::NOA_RECEIPT_CERTIFICATE,
            DocumentTypeEnums::NOA_PUBLICATION,
            DocumentTypeEnums::NOTICE_TO_UNSUCCESSFUL_BIDDERS,
        ];
    }

    /**
     * Stage 11: Performance Bond, Contract and PO Requirements
     *
     * @return array<DocumentTypeEnums>
     */
    private function getPerformanceBondRequirements(): array
    {
        return [
            DocumentTypeEnums::PERFORMANCE_BOND,
            DocumentTypeEnums::CONTRACT,
            DocumentTypeEnums::PURCHASE_ORDER,
            DocumentTypeEnums::CONTRACT_CAF,
            DocumentTypeEnums::OBLIGATION_REQUEST,
            DocumentTypeEnums::BUSINESS_DOCUMENTS,
            DocumentTypeEnums::CONTRACT_RECEIPT,
        ];
    }

    /**
     * Stage 12: Notice to Proceed Requirements
     *
     * @return array<DocumentTypeEnums>
     */
    private function getNoticeToProceedRequirements(): array
    {
        return [
            DocumentTypeEnums::NOTICE_TO_PROCEED,
            DocumentTypeEnums::NTP_ACKNOWLEDGMENT,
            DocumentTypeEnums::DELIVERY_SCHEDULE,
            DocumentTypeEnums::PERSONNEL_LIST,
            DocumentTypeEnums::PRE_CONSTRUCTION_MINUTES,
            DocumentTypeEnums::SAFETY_PLAN,
        ];
    }

    /**
     * Stage 13: Monitoring Requirements
     *
     * @return array<DocumentTypeEnums>
     */
    private function getMonitoringRequirements(): array
    {
        return [
            DocumentTypeEnums::PROGRESS_REPORTS,
            DocumentTypeEnums::MONITORING_REPORTS,
            DocumentTypeEnums::SITE_INSPECTION_REPORTS,
            DocumentTypeEnums::INSPECTION_ACCEPTANCE_REPORT,
            DocumentTypeEnums::PAYMENT_REQUESTS,
            DocumentTypeEnums::DISBURSEMENT_VOUCHERS,
        ];
    }

    /**
     * Stage 14: Completion Requirements
     *
     * @return array<DocumentTypeEnums>
     */
    private function getCompletionRequirements(): array
    {
        return [
            DocumentTypeEnums::CERTIFICATE_OF_COMPLETION,
            DocumentTypeEnums::CERTIFICATE_FINAL_ACCEPTANCE,
            DocumentTypeEnums::FINAL_INSPECTION_REPORT,
            DocumentTypeEnums::FINAL_IAR,
            DocumentTypeEnums::FINAL_PROGRESS_REPORT,
            DocumentTypeEnums::FINAL_PAYMENT_REQUEST,
            DocumentTypeEnums::FINAL_BILLING_STATEMENT,
            DocumentTypeEnums::CLEARANCE_WAIVER,
            DocumentTypeEnums::FINAL_DISBURSEMENT_VOUCHER,
            DocumentTypeEnums::TURNOVER_DOCUMENTS,
            DocumentTypeEnums::PROJECT_COMPLETION_REPORT,
            DocumentTypeEnums::UPDATED_INVENTORY_RECORDS,
            DocumentTypeEnums::PROCUREMENT_DOCUMENTATION_PACKAGE,
            DocumentTypeEnums::PERFORMANCE_EVALUATION,
        ];
    }
}
