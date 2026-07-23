<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DocumentTypeEnums;

class StageDocumentRequirementsConfig
{
    public static function getProcurementInitiationRequirements(): array
    {
        return [
            DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT,
        ];
    }

    public static function getPreProcurementConferenceRequirements(): array
    {
        return [
            DocumentTypeEnums::PRE_PROCUREMENT_AGENDA,
            DocumentTypeEnums::PRE_PROCUREMENT_ATTENDANCE,
            DocumentTypeEnums::PRE_PROCUREMENT_MINUTES,
        ];
    }

    public static function getBiddingDocumentsRequirements(): array
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

    public static function getPreBidConferenceRequirements(): array
    {
        return [
            DocumentTypeEnums::PRE_BID_AGENDA,
            DocumentTypeEnums::PRE_BID_ATTENDANCE,
            DocumentTypeEnums::PRE_BID_MINUTES,
        ];
    }

    public static function getSupplementalBidBulletinRequirements(): array
    {
        return [
            DocumentTypeEnums::SUPPLEMENTAL_BID_BULLETIN,
            DocumentTypeEnums::BAC_RESOLUTION_BID_BULLETIN,
            DocumentTypeEnums::BID_BULLETIN_PHILGEPS,
            DocumentTypeEnums::BID_BULLETIN_NOTICE,
        ];
    }

    public static function getBidOpeningRequirements(): array
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

    public static function getBidEvaluationRequirements(): array
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

    public static function getPostQualificationRequirements(): array
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

    public static function getBacResolutionRequirements(): array
    {
        return [
            DocumentTypeEnums::BAC_RESOLUTION,
            DocumentTypeEnums::BAC_RESOLUTION_AWARD,
            DocumentTypeEnums::LCRB_NOTICE,
            DocumentTypeEnums::BID_EVALUATION_PACKAGE,
            DocumentTypeEnums::TRANSMITTAL_TO_HOPE,
            DocumentTypeEnums::AWARD_PHILGEPS_POSTING,
        ];
    }

    public static function getNoticeOfAwardRequirements(): array
    {
        return [
            DocumentTypeEnums::HOPE_APPROVAL,
            DocumentTypeEnums::NOTICE_OF_AWARD,
            DocumentTypeEnums::NOA_RECEIPT_CERTIFICATE,
            DocumentTypeEnums::NOA_PUBLICATION,
            DocumentTypeEnums::NOTICE_TO_UNSUCCESSFUL_BIDDERS,
        ];
    }

    public static function getPerformanceBondRequirements(): array
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

    public static function getNoticeToProceedRequirements(): array
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

    public static function getMonitoringRequirements(): array
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

    public static function getCompletionRequirements(): array
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

    public static function getRequestForQuotationRequirements(): array
    {
        return [
            DocumentTypeEnums::REQUEST_FOR_QUOTATION,
            DocumentTypeEnums::PRICE_QUOTATION,
        ];
    }

    public static function getAbstractOfQuotationsRequirements(): array
    {
        return [
            DocumentTypeEnums::ABSTRACT_OF_QUOTATIONS,
        ];
    }
}
