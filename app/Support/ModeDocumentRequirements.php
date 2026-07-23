<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementMode;
use App\Enums\StageEnums;

class ModeDocumentRequirements
{
    public function getCompetitiveDialogueRequirements(StageEnums $stage, array $baseReqs): array
    {
        if ($stage === StageEnums::PRE_BID_CONFERENCE) {
            return array_merge($baseReqs, [
                DocumentTypeEnums::PRE_BID_MINUTES,
            ]);
        }

        return array_filter($baseReqs);
    }

    public function getUnsolicitedOfferRequirements(StageEnums $stage, ?array $baseReqs = null): array
    {
        return match ($stage) {
            StageEnums::PROCUREMENT_INITIATION => [
                DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT,
            ],
            StageEnums::REQUEST_FOR_QUOTATION => [
                DocumentTypeEnums::REQUEST_FOR_QUOTATION,
                DocumentTypeEnums::PRICE_QUOTATION,
            ],
            StageEnums::ABSTRACT_OF_QUOTATIONS => [
                DocumentTypeEnums::ABSTRACT_OF_QUOTATIONS,
            ],
            StageEnums::BAC_RESOLUTION => [
                DocumentTypeEnums::BAC_RESOLUTION,
            ],
            default => $baseReqs ?? [],
        };
    }

    public function getAlternativeModeRequirements(StageEnums $stage, ProcurementMode $mode, ?array $baseReqs = null): array
    {
        return match ($stage) {
            StageEnums::PROCUREMENT_INITIATION => [
                DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT,
            ],
            StageEnums::REQUEST_FOR_QUOTATION => $this->getRFQRequirements($mode),
            StageEnums::ABSTRACT_OF_QUOTATIONS => [
                DocumentTypeEnums::ABSTRACT_OF_QUOTATIONS,
                DocumentTypeEnums::CERTIFICATE_OF_ACCEPTANCE_OF_QUOTATION,
                DocumentTypeEnums::PHILGEPS_AWARD_NOTICE_ABSTRACT,
            ],
            StageEnums::BAC_RESOLUTION => [
                DocumentTypeEnums::BAC_RESOLUTION,
            ],
            StageEnums::NOTICE_OF_AWARD => [
                DocumentTypeEnums::NOTICE_OF_AWARD,
            ],
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO => $this->getAlternativeModeContractRequirements($mode),
            StageEnums::NOTICE_TO_PROCEED => [
                DocumentTypeEnums::NOTICE_TO_PROCEED,
            ],
            StageEnums::MONITORING => $this->getAlternativeModeMonitoringRequirements($mode),
            StageEnums::COMPLETION => $this->getAlternativeModeCompletionRequirements($mode),
            StageEnums::COMPLETED => [],
            default => $baseReqs ?? [],
        };
    }

    public function getRFQRequirements(ProcurementMode $mode): array
    {
        return match ($mode) {
            ProcurementMode::DIRECT_ACQUISITION => [
                DocumentTypeEnums::REQUEST_FOR_QUOTATION,
                DocumentTypeEnums::PRICE_QUOTATION,
            ],
            ProcurementMode::SMALL_VALUE_PROCUREMENT => [
                DocumentTypeEnums::NOTICE_OF_REQUEST_FOR_QUOTATION,
                DocumentTypeEnums::REQUEST_FOR_QUOTATION,
                DocumentTypeEnums::PHILGEPS_BID_NOTICE_ABSTRACT,
            ],
            ProcurementMode::DIRECT_CONTRACTING => [
                DocumentTypeEnums::REQUEST_FOR_QUOTATION,
            ],
            ProcurementMode::REPEAT_ORDER => [
                DocumentTypeEnums::REQUEST_FOR_QUOTATION,
                DocumentTypeEnums::PRICE_QUOTATION,
            ],
            ProcurementMode::NEGOTIATED_PROCUREMENT => [
                DocumentTypeEnums::REQUEST_FOR_QUOTATION,
                DocumentTypeEnums::PRICE_QUOTATION,
            ],
            ProcurementMode::DIRECT_SALES => [
                DocumentTypeEnums::REQUEST_FOR_QUOTATION,
                DocumentTypeEnums::PRICE_QUOTATION,
            ],
            default => [
                DocumentTypeEnums::REQUEST_FOR_QUOTATION,
                DocumentTypeEnums::PRICE_QUOTATION,
            ],
        };
    }

    public function getAlternativeModeContractRequirements(ProcurementMode $mode): array
    {
        return match ($mode) {
            ProcurementMode::DIRECT_ACQUISITION => [
                DocumentTypeEnums::PURCHASE_ORDER,
            ],
            ProcurementMode::SMALL_VALUE_PROCUREMENT => [
                DocumentTypeEnums::PURCHASE_ORDER,
            ],
            default => [
                DocumentTypeEnums::CONTRACT,
                DocumentTypeEnums::PURCHASE_ORDER,
            ],
        };
    }

    public function getAlternativeModeMonitoringRequirements(ProcurementMode $mode): array
    {
        return match ($mode) {
            ProcurementMode::DIRECT_ACQUISITION => [
                DocumentTypeEnums::INSPECTION_ACCEPTANCE_REPORT,
            ],
            ProcurementMode::SMALL_VALUE_PROCUREMENT => [
                DocumentTypeEnums::DELIVERY_RECEIPTS,
                DocumentTypeEnums::INSPECTION_ACCEPTANCE_REPORT,
                DocumentTypeEnums::PROGRESS_REPORTS,
            ],
            default => [
                DocumentTypeEnums::INSPECTION_ACCEPTANCE_REPORT,
                DocumentTypeEnums::PROGRESS_REPORTS,
                DocumentTypeEnums::MONITORING_REPORTS,
            ],
        };
    }

    public function getAlternativeModeCompletionRequirements(ProcurementMode $mode): array
    {
        return match ($mode) {
            ProcurementMode::DIRECT_ACQUISITION => [
                DocumentTypeEnums::CERTIFICATE_OF_COMPLETION,
                DocumentTypeEnums::FINAL_IAR,
                DocumentTypeEnums::FINAL_DISBURSEMENT_VOUCHER,
            ],
            ProcurementMode::SMALL_VALUE_PROCUREMENT => [
                DocumentTypeEnums::CERTIFICATE_OF_COMPLETION,
                DocumentTypeEnums::FINAL_IAR,
                DocumentTypeEnums::FINAL_DISBURSEMENT_VOUCHER,
                DocumentTypeEnums::PROJECT_COMPLETION_REPORT,
            ],
            default => [
                DocumentTypeEnums::CERTIFICATE_OF_COMPLETION,
                DocumentTypeEnums::CERTIFICATE_FINAL_ACCEPTANCE,
                DocumentTypeEnums::FINAL_IAR,
                DocumentTypeEnums::FINAL_DISBURSEMENT_VOUCHER,
                DocumentTypeEnums::PROJECT_COMPLETION_REPORT,
            ],
        };
    }

    public function getDirectProcurementSTIRequirements(StageEnums $stage, ?array $baseReqs = null): array
    {
        return match ($stage) {
            StageEnums::PROCUREMENT_INITIATION => [
                DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT,
            ],
            StageEnums::REQUEST_FOR_QUOTATION => [
                DocumentTypeEnums::REQUEST_FOR_QUOTATION,
                DocumentTypeEnums::PRICE_QUOTATION,
            ],
            StageEnums::ABSTRACT_OF_QUOTATIONS => [
                DocumentTypeEnums::ABSTRACT_OF_QUOTATIONS,
            ],
            default => $baseReqs ?? [],
        };
    }

    public function getAlternativeModeOptionalDocuments(StageEnums $stage, ProcurementMode $mode): array
    {
        return match ($stage) {
            StageEnums::REQUEST_FOR_QUOTATION => [],
            StageEnums::ABSTRACT_OF_QUOTATIONS => [
                DocumentTypeEnums::LOWEST_QUOTATION_CERTIFICATION,
            ],
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO => [
                DocumentTypeEnums::JOB_ORDER,
            ],
            StageEnums::COMPLETION => [
                DocumentTypeEnums::PERFORMANCE_EVALUATION,
            ],
            default => [],
        };
    }
}
