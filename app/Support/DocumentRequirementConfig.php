<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementCategory;
use App\Enums\StageEnums;

class DocumentRequirementConfig
{
    public static function isRequiredForInitiation(DocumentTypeEnums $docType): bool
    {
        return match ($docType) {
            DocumentTypeEnums::PURCHASE_REQUEST,
            DocumentTypeEnums::TECHNICAL_SPECIFICATIONS,
            DocumentTypeEnums::BUDGET_ESTIMATE => true,
            default => false,
        };
    }

    public static function isRequiredForStage(DocumentTypeEnums $docType, StageEnums $stage): bool
    {
        return match ($docType) {
            DocumentTypeEnums::PURCHASE_REQUEST,
            DocumentTypeEnums::TECHNICAL_SPECIFICATIONS,
            DocumentTypeEnums::BUDGET_ESTIMATE => $stage === StageEnums::PROCUREMENT_INITIATION,

            DocumentTypeEnums::BIDDING_DOCUMENT,
            DocumentTypeEnums::ABSTRACT_OF_BIDS,
            DocumentTypeEnums::BID_EVALUATION_REPORT => in_array($stage, [
                StageEnums::PROCUREMENT_INITIATION,
                StageEnums::BID_OPENING,
            ]),

            DocumentTypeEnums::NOTICE_OF_AWARD,
            DocumentTypeEnums::CONTRACT => $stage === StageEnums::NOTICE_OF_AWARD,

            DocumentTypeEnums::INSPECTION_ACCEPTANCE_REPORT,
            DocumentTypeEnums::CERTIFICATE_OF_COMPLETION => $stage === StageEnums::COMPLETION,

            default => false,
        };
    }

    public static function isMandatory(DocumentTypeEnums $docType): bool
    {
        return match ($docType) {
            DocumentTypeEnums::PURCHASE_REQUEST,
            DocumentTypeEnums::CERTIFICATE_OF_FUNDS,
            DocumentTypeEnums::PPMP_ENTRY => true,
            default => false,
        };
    }

    public static function isMandatoryForCategory(DocumentTypeEnums $docType, ProcurementCategory $category): bool
    {
        return match ($docType) {
            DocumentTypeEnums::PURCHASE_REQUEST,
            DocumentTypeEnums::CERTIFICATE_OF_FUNDS,
            DocumentTypeEnums::PPMP_ENTRY => true,

            DocumentTypeEnums::TECHNICAL_SPECIFICATIONS => in_array($category, [
                ProcurementCategory::GOODS,
                ProcurementCategory::INFRASTRUCTURE_PROJECTS,
            ]),

            DocumentTypeEnums::TERMS_OF_REFERENCE => $category === ProcurementCategory::CONSULTING_SERVICES,

            default => false,
        };
    }

    public static function isApplicableForCategory(DocumentTypeEnums $docType, ProcurementCategory $category): bool
    {
        return match ($docType) {
            DocumentTypeEnums::TECHNICAL_SPECIFICATIONS => in_array($category, [
                ProcurementCategory::GOODS,
                ProcurementCategory::INFRASTRUCTURE_PROJECTS,
            ]),

            DocumentTypeEnums::TERMS_OF_REFERENCE => $category === ProcurementCategory::CONSULTING_SERVICES,

            default => true,
        };
    }
}
