<?php

namespace App\Services;

use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;

/**
 * Mode-Aware Stage Document Requirements Service
 *
 * Extends the base StageDocumentRequirements to provide mode-specific
 * document requirements aligned with NGPA IRR (RA 12009).
 *
 * Optimized for Municipality of Gloria, Oriental Mindoro (4th Class Municipality):
 * - SVP Threshold: ₱200,000 per Section 34.2
 * - Direct Acquisition Threshold: ₱200,000 per Section 32
 *
 * @see https://www.gppb.gov.ph/laws/irr.htm
 */
class ModeAwareDocumentRequirements
{
    public function __construct(
        private readonly StageDocumentRequirements $baseRequirements
    ) {}

    /**
     * Get required documents for a specific stage and procurement mode
     *
     * @return array<DocumentTypeEnums>
     */
    public function getRequiredDocuments(StageEnums $stage, ProcurementModeEnums $mode): array
    {
        // First, check if this stage is valid for the mode
        if (! $stage->existsInModeWorkflow($mode)) {
            return [];
        }

        // Get base requirements, then adjust based on mode
        return match ($mode) {
            // Competitive modes - Full requirements
            ProcurementModeEnums::COMPETITIVE_BIDDING,
            ProcurementModeEnums::LIMITED_SOURCE_BIDDING => $this->baseRequirements->getRequiredDocuments($stage),

            // Competitive Dialogue - Full requirements with dialogue docs
            ProcurementModeEnums::COMPETITIVE_DIALOGUE => $this->getCompetitiveDialogueRequirements($stage),

            // Unsolicited Offer - Requirements for bid matching
            ProcurementModeEnums::UNSOLICITED_OFFER_WITH_BID_MATCHING => $this->getUnsolicitedOfferRequirements($stage),

            // Alternative modes - Simplified requirements
            ProcurementModeEnums::SMALL_VALUE_PROCUREMENT,
            ProcurementModeEnums::DIRECT_CONTRACTING,
            ProcurementModeEnums::DIRECT_ACQUISITION,
            ProcurementModeEnums::REPEAT_ORDER,
            ProcurementModeEnums::DIRECT_SALES,
            ProcurementModeEnums::NEGOTIATED_PROCUREMENT => $this->getAlternativeModeRequirements($stage, $mode),

            // Direct Procurement for STI - Special requirements
            ProcurementModeEnums::DIRECT_PROCUREMENT_FOR_STI => $this->getDirectProcurementSTIRequirements($stage),
        };
    }

    /**
     * Get optional documents for a specific stage and procurement mode
     *
     * @return array<DocumentTypeEnums>
     */
    public function getOptionalDocuments(StageEnums $stage, ProcurementModeEnums $mode): array
    {
        // First, check if this stage is valid for the mode
        if (! $stage->existsInModeWorkflow($mode)) {
            return [];
        }

        // Alternative modes have fewer optional documents
        if ($mode->isAlternativeMode()) {
            return $this->getAlternativeModeOptionalDocuments($stage, $mode);
        }

        return $this->baseRequirements->getOptionalDocuments($stage);
    }

    /**
     * Get document counts for a specific stage and mode
     */
    public function getDocumentCounts(StageEnums $stage, ProcurementModeEnums $mode): array
    {
        $required = $this->getRequiredDocuments($stage, $mode);
        $optional = $this->getOptionalDocuments($stage, $mode);

        return [
            'required_count' => count($required),
            'optional_count' => count($optional),
            'total_count' => count($required) + count($optional),
        ];
    }

    /**
     * Get missing required documents for a stage and mode
     *
     * @return array<DocumentTypeEnums>
     */
    public function getMissingDocuments(StageEnums $stage, ProcurementModeEnums $mode, array $uploadedTypes): array
    {
        $required = $this->getRequiredDocuments($stage, $mode);
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

    /**
     * Check if minimum required documents are uploaded for stage and mode
     */
    public function hasMinimumRequiredDocuments(StageEnums $stage, ProcurementModeEnums $mode, array $uploadedTypes): bool
    {
        return empty($this->getMissingDocuments($stage, $mode, $uploadedTypes));
    }

    /**
     * Get complete document guide for a stage and mode
     */
    public function getStageDocumentGuide(StageEnums $stage, ProcurementModeEnums $mode): array
    {
        $requiredDocs = $this->getRequiredDocuments($stage, $mode);
        $optionalDocs = $this->getOptionalDocuments($stage, $mode);
        $counts = $this->getDocumentCounts($stage, $mode);

        return [
            'stage' => $stage->value,
            'stage_display_name' => $stage->getDisplayName(),
            'mode' => $mode->value,
            'mode_display_name' => $mode->getDisplayName(),
            'phase' => $stage->getPhase(),
            'description' => $stage->getDescription(),
            'ngpa_reference' => $mode->getIrrSection(),
            'is_alternative_mode' => $mode->isAlternativeMode(),
            'required_documents' => array_map(fn ($doc) => [
                'value' => $doc->value,
                'display_name' => $doc->getDisplayName(),
                'description' => $doc->getDescription(),
            ], $requiredDocs),
            'optional_documents' => array_map(fn ($doc) => [
                'value' => $doc->value,
                'display_name' => $doc->getDisplayName(),
                'description' => $doc->getDescription(),
            ], $optionalDocs),
            'counts' => $counts,
        ];
    }

    /**
     * Get ABC-aware required documents for stages with threshold-based requirements
     * Per NGPA Section 38 - Video Recording Requirements:
     * - Goods: Above ₱10,000,000
     * - Infrastructure Projects: Above ₱20,000,000
     * - Consulting Services: Above ₱5,000,000
     *
     * @param  float  $abcAmount  The Approved Budget for the Contract
     * @param  string  $category  'goods', 'infrastructure', or 'consulting'
     * @return array<DocumentTypeEnums>
     */
    public function getAbcAwareRequiredDocuments(
        StageEnums $stage,
        ProcurementModeEnums $mode,
        float $abcAmount,
        string $category = 'goods'
    ): array {
        $baseRequired = $this->getRequiredDocuments($stage, $mode);

        // Check if video recording should be required per NGPA Section 38
        if ($this->requiresVideoRecording($stage, $abcAmount, $category)) {
            // Add video recording documents to required list for applicable stages
            if ($stage === StageEnums::PRE_BID_CONFERENCE) {
                if (! in_array(DocumentTypeEnums::PRE_BID_RECORDING, $baseRequired, true)) {
                    $baseRequired[] = DocumentTypeEnums::PRE_BID_RECORDING;
                }
            } elseif ($stage === StageEnums::BID_OPENING) {
                if (! in_array(DocumentTypeEnums::BID_OPENING_RECORDING, $baseRequired, true)) {
                    $baseRequired[] = DocumentTypeEnums::BID_OPENING_RECORDING;
                }
            }
        }

        return $baseRequired;
    }

    /**
     * Check if video recording is required based on ABC thresholds
     * Per NGPA Section 38.3
     */
    public function requiresVideoRecording(StageEnums $stage, float $abcAmount, string $category = 'goods'): bool
    {
        // Only applicable for Pre-Bid Conference and Bid Opening stages
        if (! in_array($stage, [StageEnums::PRE_BID_CONFERENCE, StageEnums::BID_OPENING], true)) {
            return false;
        }

        // NGPA Section 38.3 Thresholds
        $thresholds = [
            'goods' => 10_000_000.00,           // ₱10,000,000
            'infrastructure' => 20_000_000.00,  // ₱20,000,000
            'consulting' => 5_000_000.00,       // ₱5,000,000
        ];

        $threshold = $thresholds[$category] ?? $thresholds['goods'];

        return $abcAmount > $threshold;
    }

    /**
     * Get ABC-aware optional documents (excludes video recording if it's now required)
     */
    public function getAbcAwareOptionalDocuments(
        StageEnums $stage,
        ProcurementModeEnums $mode,
        float $abcAmount,
        string $category = 'goods'
    ): array {
        $baseOptional = $this->getOptionalDocuments($stage, $mode);

        // If video recording is now required, remove it from optional
        if ($this->requiresVideoRecording($stage, $abcAmount, $category)) {
            $baseOptional = array_filter($baseOptional, function ($doc) use ($stage) {
                if ($stage === StageEnums::PRE_BID_CONFERENCE) {
                    return $doc !== DocumentTypeEnums::PRE_BID_RECORDING;
                } elseif ($stage === StageEnums::BID_OPENING) {
                    return $doc !== DocumentTypeEnums::BID_OPENING_RECORDING;
                }

                return true;
            });
        }

        return array_values($baseOptional);
    }

    /**
     * Get complete document guide for a stage and mode with ABC-awareness
     */
    public function getAbcAwareStageDocumentGuide(
        StageEnums $stage,
        ProcurementModeEnums $mode,
        float $abcAmount,
        string $category = 'goods'
    ): array {
        $requiredDocs = $this->getAbcAwareRequiredDocuments($stage, $mode, $abcAmount, $category);
        $optionalDocs = $this->getAbcAwareOptionalDocuments($stage, $mode, $abcAmount, $category);

        $requiresRecording = $this->requiresVideoRecording($stage, $abcAmount, $category);

        return [
            'stage' => $stage->value,
            'stage_display_name' => $stage->getDisplayName(),
            'mode' => $mode->value,
            'mode_display_name' => $mode->getDisplayName(),
            'phase' => $stage->getPhase(),
            'description' => $stage->getDescription(),
            'ngpa_reference' => $mode->getIrrSection(),
            'is_alternative_mode' => $mode->isAlternativeMode(),
            'abc_amount' => $abcAmount,
            'category' => $category,
            'requires_video_recording' => $requiresRecording,
            'required_documents' => array_map(fn ($doc) => [
                'value' => $doc->value,
                'display_name' => $doc->getDisplayName(),
                'description' => $doc->getDescription(),
            ], $requiredDocs),
            'optional_documents' => array_map(fn ($doc) => [
                'value' => $doc->value,
                'display_name' => $doc->getDisplayName(),
                'description' => $doc->getDescription(),
            ], $optionalDocs),
            'counts' => [
                'required_count' => count($requiredDocs),
                'optional_count' => count($optionalDocs),
                'total_count' => count($requiredDocs) + count($optionalDocs),
            ],
        ];
    }

    // ==================================================================================
    // PRIVATE METHODS: Mode-Specific Requirements
    // ==================================================================================

    /**
     * Get requirements for Competitive Dialogue mode
     * Per NGPA IRR Section 29
     */
    private function getCompetitiveDialogueRequirements(StageEnums $stage): array
    {
        // Competitive Dialogue follows similar requirements to Competitive Bidding
        // but with additional dialogue documentation
        $baseReqs = $this->baseRequirements->getRequiredDocuments($stage);

        if ($stage === StageEnums::PRE_BID_CONFERENCE) {
            // Add dialogue-specific documents
            return array_merge($baseReqs, [
                DocumentTypeEnums::DIALOGUE_MINUTES ?? null,
            ]);
        }

        return array_filter($baseReqs);
    }

    /**
     * Get requirements for Unsolicited Offer with Bid Matching mode
     * Per NGPA IRR Section 30
     */
    private function getUnsolicitedOfferRequirements(StageEnums $stage): array
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
            default => $this->baseRequirements->getRequiredDocuments($stage),
        };
    }

    /**
     * Get requirements for Alternative Procurement Modes
     *
     * Per NGPA IRR Sections 31-36:
     * - Direct Contracting (Sec. 31)
     * - Direct Acquisition (Sec. 32) - ≤₱200,000
     * - Repeat Order (Sec. 33)
     * - Small Value Procurement (Sec. 34) - ₱200,000 for 4th class municipality
     * - Negotiated Procurement (Sec. 35)
     * - Direct Sales (Sec. 36)
     */
    private function getAlternativeModeRequirements(StageEnums $stage, ProcurementModeEnums $mode): array
    {
        return match ($stage) {
            // Stage 1: Procurement Initiation - Same for all modes
            StageEnums::PROCUREMENT_INITIATION => [
                DocumentTypeEnums::PROCUREMENT_INITIATION_DOCUMENT,
            ],

            // RFQ Stage - Core of alternative modes per Section 34.3
            StageEnums::REQUEST_FOR_QUOTATION => $this->getRFQRequirements($mode),

            // Abstract of Quotations - Compilation of received quotations
            StageEnums::ABSTRACT_OF_QUOTATIONS => [
                DocumentTypeEnums::ABSTRACT_OF_QUOTATIONS,
            ],

            // BAC Resolution - Award recommendation
            StageEnums::BAC_RESOLUTION => [
                DocumentTypeEnums::BAC_RESOLUTION,
            ],

            // Post-Award stages - Simplified for alternative modes
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

            default => $this->baseRequirements->getRequiredDocuments($stage),
        };
    }

    /**
     * Get RFQ requirements based on mode
     * Per Section 34.3 - SVP Procedure
     */
    private function getRFQRequirements(ProcurementModeEnums $mode): array
    {
        return match ($mode) {
            // Direct Acquisition (≤₱200,000) - Simplified per Section 32
            ProcurementModeEnums::DIRECT_ACQUISITION => [
                DocumentTypeEnums::REQUEST_FOR_QUOTATION,
                DocumentTypeEnums::PRICE_QUOTATION,
            ],

            // SVP - At least 3 quotations requested, 1 sufficient per Section 34.1
            ProcurementModeEnums::SMALL_VALUE_PROCUREMENT => [
                DocumentTypeEnums::REQUEST_FOR_QUOTATION,
                DocumentTypeEnums::PRICE_QUOTATION,
            ],

            // Direct Contracting - Simplified per Section 31.3
            ProcurementModeEnums::DIRECT_CONTRACTING => [
                DocumentTypeEnums::REQUEST_FOR_QUOTATION,
            ],

            // Repeat Order - Reference to original contract per Section 33
            ProcurementModeEnums::REPEAT_ORDER => [
                DocumentTypeEnums::REQUEST_FOR_QUOTATION,
                DocumentTypeEnums::PRICE_QUOTATION,
            ],

            // Negotiated Procurement - per Section 35
            ProcurementModeEnums::NEGOTIATED_PROCUREMENT => [
                DocumentTypeEnums::REQUEST_FOR_QUOTATION,
                DocumentTypeEnums::PRICE_QUOTATION,
            ],

            // Direct Sales - per Section 36
            ProcurementModeEnums::DIRECT_SALES => [
                DocumentTypeEnums::REQUEST_FOR_QUOTATION,
                DocumentTypeEnums::PRICE_QUOTATION,
            ],

            default => [
                DocumentTypeEnums::REQUEST_FOR_QUOTATION,
                DocumentTypeEnums::PRICE_QUOTATION,
            ],
        };
    }

    /**
     * Get contract requirements for alternative modes
     */
    private function getAlternativeModeContractRequirements(ProcurementModeEnums $mode): array
    {
        return match ($mode) {
            // Direct Acquisition (≤₱200,000) - Minimal requirements per Section 32
            ProcurementModeEnums::DIRECT_ACQUISITION => [
                DocumentTypeEnums::PURCHASE_ORDER,
            ],

            // SVP - Standard PO/Contract per Section 34
            ProcurementModeEnums::SMALL_VALUE_PROCUREMENT => [
                DocumentTypeEnums::PURCHASE_ORDER,
                DocumentTypeEnums::CONTRACT,
            ],

            // Other alternative modes - Standard requirements
            default => [
                DocumentTypeEnums::CONTRACT,
                DocumentTypeEnums::PURCHASE_ORDER,
            ],
        };
    }

    /**
     * Get monitoring requirements for alternative modes
     */
    private function getAlternativeModeMonitoringRequirements(ProcurementModeEnums $mode): array
    {
        return match ($mode) {
            // Direct Acquisition - Simplified monitoring
            ProcurementModeEnums::DIRECT_ACQUISITION => [
                DocumentTypeEnums::INSPECTION_ACCEPTANCE_REPORT,
            ],

            // SVP - Standard monitoring
            ProcurementModeEnums::SMALL_VALUE_PROCUREMENT => [
                DocumentTypeEnums::INSPECTION_ACCEPTANCE_REPORT,
                DocumentTypeEnums::PROGRESS_REPORTS,
            ],

            // Other alternative modes
            default => [
                DocumentTypeEnums::INSPECTION_ACCEPTANCE_REPORT,
                DocumentTypeEnums::PROGRESS_REPORTS,
                DocumentTypeEnums::MONITORING_REPORTS,
            ],
        };
    }

    /**
     * Get completion requirements for alternative modes
     */
    private function getAlternativeModeCompletionRequirements(ProcurementModeEnums $mode): array
    {
        return match ($mode) {
            // Direct Acquisition - Simplified completion
            ProcurementModeEnums::DIRECT_ACQUISITION => [
                DocumentTypeEnums::CERTIFICATE_OF_COMPLETION,
                DocumentTypeEnums::FINAL_IAR,
                DocumentTypeEnums::FINAL_DISBURSEMENT_VOUCHER,
            ],

            // SVP - Standard completion
            ProcurementModeEnums::SMALL_VALUE_PROCUREMENT => [
                DocumentTypeEnums::CERTIFICATE_OF_COMPLETION,
                DocumentTypeEnums::FINAL_IAR,
                DocumentTypeEnums::FINAL_DISBURSEMENT_VOUCHER,
                DocumentTypeEnums::PROJECT_COMPLETION_REPORT,
            ],

            // Other alternative modes
            default => [
                DocumentTypeEnums::CERTIFICATE_OF_COMPLETION,
                DocumentTypeEnums::CERTIFICATE_FINAL_ACCEPTANCE,
                DocumentTypeEnums::FINAL_IAR,
                DocumentTypeEnums::FINAL_DISBURSEMENT_VOUCHER,
                DocumentTypeEnums::PROJECT_COMPLETION_REPORT,
            ],
        };
    }

    /**
     * Get requirements for Direct Procurement for STI
     * Per NGPA IRR Section 37
     */
    private function getDirectProcurementSTIRequirements(StageEnums $stage): array
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
            default => $this->baseRequirements->getRequiredDocuments($stage),
        };
    }

    /**
     * Get optional documents for alternative modes
     */
    private function getAlternativeModeOptionalDocuments(StageEnums $stage, ProcurementModeEnums $mode): array
    {
        return match ($stage) {
            StageEnums::REQUEST_FOR_QUOTATION => [
                DocumentTypeEnums::SUPPLIER_CANVASS_FORM,
                DocumentTypeEnums::QUOTATION_COMPARISON_SHEET,
            ],
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
