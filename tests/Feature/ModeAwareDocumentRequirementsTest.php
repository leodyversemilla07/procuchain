<?php

use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementMode;
use App\Enums\StageEnums;
use App\Services\ModeAwareDocumentRequirementsService;
use App\Services\ModeAwareDocumentValidationService;
use App\Services\StageDocumentRequirementsService;

beforeEach(function () {
    $this->baseRequirements = app(StageDocumentRequirementsService::class);
    $this->modeAwareRequirements = app(ModeAwareDocumentRequirementsService::class);
    $this->validationService = app(ModeAwareDocumentValidationService::class);
});

describe('ModeAwareDocumentRequirementsService - Competitive Modes', function () {
    it('returns full requirements for Competitive Bidding mode', function () {
        $mode = ProcurementMode::COMPETITIVE_BIDDING;
        $stage = StageEnums::BID_OPENING;

        $required = $this->modeAwareRequirements->getRequiredDocuments($stage, $mode);
        $baseRequired = $this->baseRequirements->getRequiredDocuments($stage);

        // Competitive Bidding should have full requirements
        expect($required)->toBe($baseRequired);
    });

    it('returns full requirements for Limited Source Bidding mode', function () {
        $mode = ProcurementMode::LIMITED_SOURCE_BIDDING;
        $stage = StageEnums::BID_EVALUATION;

        $required = $this->modeAwareRequirements->getRequiredDocuments($stage, $mode);
        $baseRequired = $this->baseRequirements->getRequiredDocuments($stage);

        // Per Section 28.5: observe procedure for Competitive Bidding
        expect($required)->toBe($baseRequired);
    });

    it('includes Pre-Procurement Conference docs for Competitive Bidding', function () {
        $mode = ProcurementMode::COMPETITIVE_BIDDING;
        $stage = StageEnums::PRE_PROCUREMENT_CONFERENCE;

        $required = $this->modeAwareRequirements->getRequiredDocuments($stage, $mode);

        expect($required)->not->toBeEmpty();
    });
});

describe('ModeAwareDocumentRequirementsService - Alternative Modes', function () {
    it('returns simplified requirements for Small Value Procurement', function () {
        $mode = ProcurementMode::SMALL_VALUE_PROCUREMENT;
        $stage = StageEnums::REQUEST_FOR_QUOTATION;

        $required = $this->modeAwareRequirements->getRequiredDocuments($stage, $mode);

        // SVP requires Notice of RFQ, RFQ, and PhilGEPS Bid Notice Abstract per Section 34
        expect($required)->toContain(DocumentTypeEnums::NOTICE_OF_REQUEST_FOR_QUOTATION);
        expect($required)->toContain(DocumentTypeEnums::REQUEST_FOR_QUOTATION);
        expect($required)->toContain(DocumentTypeEnums::PHILGEPS_BID_NOTICE_ABSTRACT);
    });

    it('returns empty for stages not in SVP workflow', function () {
        $mode = ProcurementMode::SMALL_VALUE_PROCUREMENT;
        $stage = StageEnums::BID_OPENING;

        $required = $this->modeAwareRequirements->getRequiredDocuments($stage, $mode);

        // BID_OPENING is not in SVP workflow
        expect($required)->toBeEmpty();
    });

    it('returns minimal requirements for Direct Acquisition (≤₱200,000)', function () {
        $mode = ProcurementMode::DIRECT_ACQUISITION;
        $stage = StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO;

        $required = $this->modeAwareRequirements->getRequiredDocuments($stage, $mode);

        // Direct Acquisition only requires PO per Section 32
        expect($required)->toContain(DocumentTypeEnums::PURCHASE_ORDER);
        expect(count($required))->toBeLessThan(3);
    });

    it('returns RFQ only for Direct Contracting', function () {
        $mode = ProcurementMode::DIRECT_CONTRACTING;
        $stage = StageEnums::REQUEST_FOR_QUOTATION;

        $required = $this->modeAwareRequirements->getRequiredDocuments($stage, $mode);

        // Direct Contracting simplified per Section 31.3
        expect($required)->toContain(DocumentTypeEnums::REQUEST_FOR_QUOTATION);
    });

    it('returns monitoring requirements scaled by mode complexity', function () {
        // Direct Acquisition - Minimal monitoring
        $directAcq = $this->modeAwareRequirements->getRequiredDocuments(
            StageEnums::MONITORING,
            ProcurementMode::DIRECT_ACQUISITION
        );

        // SVP - Standard monitoring
        $svp = $this->modeAwareRequirements->getRequiredDocuments(
            StageEnums::MONITORING,
            ProcurementMode::SMALL_VALUE_PROCUREMENT
        );

        // Direct Acquisition should have fewer monitoring requirements
        expect(count($directAcq))->toBeLessThanOrEqual(count($svp));
    });
});

describe('ModeAwareDocumentRequirementsService - Municipality of Gloria Thresholds', function () {
    it('confirms SVP threshold of ₱400,000 for 4th class municipality', function () {
        $threshold = ProcurementMode::SMALL_VALUE_PROCUREMENT->thresholdAmount();

        // Municipality of Gloria is a 4th Class Municipality
        expect($threshold)->toBe(400000.00);
    });

    it('confirms Direct Acquisition threshold of ₱200,000', function () {
        $threshold = ProcurementMode::DIRECT_ACQUISITION->thresholdAmount();

        expect($threshold)->toBe(200000.00);
    });

    it('validates amount within SVP threshold is valid', function () {
        $mode = ProcurementMode::SMALL_VALUE_PROCUREMENT;

        // ₱150,000 is within threshold
        expect($mode->isValidAmount(150000))->toBeTrue();

        // ₱400,000 is at threshold
        expect($mode->isValidAmount(400000))->toBeTrue();
    });

    it('validates amount exceeding SVP threshold is invalid', function () {
        $mode = ProcurementMode::SMALL_VALUE_PROCUREMENT;

        // ₱450,000 exceeds threshold
        expect($mode->isValidAmount(450000))->toBeFalse();
    });
});

describe('ModeAwareDocumentValidationService', function () {
    it('validates upload for mode-specific stage', function () {
        $result = $this->validationService->validateUpload(
            StageEnums::REQUEST_FOR_QUOTATION,
            DocumentTypeEnums::REQUEST_FOR_QUOTATION,
            [],
            ProcurementMode::SMALL_VALUE_PROCUREMENT
        );

        expect($result['valid'])->toBeTrue();
        expect($result['errors'])->toBeEmpty();
    });

    it('rejects upload for stage not in mode workflow', function () {
        $result = $this->validationService->validateUpload(
            StageEnums::BID_OPENING,
            DocumentTypeEnums::SEALED_BID_PROPOSALS,
            [],
            ProcurementMode::SMALL_VALUE_PROCUREMENT
        );

        expect($result['valid'])->toBeFalse();
        expect($result['errors'])->not->toBeEmpty();
    });

    it('adds warning for alternative mode simplified requirements', function () {
        $result = $this->validationService->validateUpload(
            StageEnums::REQUEST_FOR_QUOTATION,
            DocumentTypeEnums::REQUEST_FOR_QUOTATION,
            [],
            ProcurementMode::SMALL_VALUE_PROCUREMENT
        );

        // Should include NGPA reference warning
        $hasNgpaWarning = false;
        foreach ($result['warnings'] as $warning) {
            if (str_contains($warning, 'NGPA IRR') || str_contains($warning, 'simplified')) {
                $hasNgpaWarning = true;
                break;
            }
        }
        expect($hasNgpaWarning)->toBeTrue();
    });

    it('validates stage completion with mode awareness', function () {
        $uploadedDocs = [
            DocumentTypeEnums::NOTICE_OF_REQUEST_FOR_QUOTATION,
            DocumentTypeEnums::REQUEST_FOR_QUOTATION,
            DocumentTypeEnums::PHILGEPS_BID_NOTICE_ABSTRACT,
        ];

        $result = $this->validationService->validateStageCompletion(
            StageEnums::REQUEST_FOR_QUOTATION,
            $uploadedDocs,
            ProcurementMode::SMALL_VALUE_PROCUREMENT
        );

        expect($result['can_complete'])->toBeTrue();
        expect($result['completion_percentage'])->toBe(100.0);
        expect($result['is_alternative_mode'])->toBeTrue();
    });

    it('calculates correct completion percentage for alternative modes', function () {
        $uploadedDocs = [
            DocumentTypeEnums::REQUEST_FOR_QUOTATION,
        ];

        $result = $this->validationService->validateStageCompletion(
            StageEnums::REQUEST_FOR_QUOTATION,
            $uploadedDocs,
            ProcurementMode::SMALL_VALUE_PROCUREMENT
        );

        // SVP RFQ stage requires 3 docs, 1 uploaded = 33.33%
        expect($result['completion_percentage'])->toBe(33.33);
    });
});

describe('ProcurementMode - Mode Classification', function () {
    it('correctly identifies alternative modes', function () {
        expect(ProcurementMode::SMALL_VALUE_PROCUREMENT->isAlternativeMode())->toBeTrue();
        expect(ProcurementMode::DIRECT_CONTRACTING->isAlternativeMode())->toBeTrue();
        expect(ProcurementMode::DIRECT_ACQUISITION->isAlternativeMode())->toBeTrue();
        expect(ProcurementMode::REPEAT_ORDER->isAlternativeMode())->toBeTrue();
        expect(ProcurementMode::NEGOTIATED_PROCUREMENT->isAlternativeMode())->toBeTrue();
        expect(ProcurementMode::DIRECT_SALES->isAlternativeMode())->toBeTrue();
        expect(ProcurementMode::DIRECT_PROCUREMENT_FOR_STI->isAlternativeMode())->toBeTrue();
    });

    it('correctly identifies competitive modes', function () {
        expect(ProcurementMode::COMPETITIVE_BIDDING->isCompetitiveMode())->toBeTrue();
        expect(ProcurementMode::LIMITED_SOURCE_BIDDING->isCompetitiveMode())->toBeTrue();
        expect(ProcurementMode::COMPETITIVE_DIALOGUE->isCompetitiveMode())->toBeTrue();
        expect(ProcurementMode::UNSOLICITED_OFFER_WITH_BID_MATCHING->isCompetitiveMode())->toBeTrue();
    });

    it('correctly identifies modes that can be delegated', function () {
        // Alternative modes can be delegated per Section 26.4
        expect(ProcurementMode::SMALL_VALUE_PROCUREMENT->canBeDelegated())->toBeTrue();
        expect(ProcurementMode::DIRECT_CONTRACTING->canBeDelegated())->toBeTrue();

        // Competitive modes cannot be delegated
        expect(ProcurementMode::COMPETITIVE_BIDDING->canBeDelegated())->toBeFalse();
        expect(ProcurementMode::LIMITED_SOURCE_BIDDING->canBeDelegated())->toBeFalse();
    });
});

describe('Document Guide with Mode Awareness', function () {
    it('returns mode-specific document guide', function () {
        $guide = $this->validationService->getStageDocumentGuide(
            StageEnums::REQUEST_FOR_QUOTATION,
            ProcurementMode::SMALL_VALUE_PROCUREMENT
        );

        expect($guide)->toHaveKey('mode');
        expect($guide)->toHaveKey('mode_display_name');
        expect($guide)->toHaveKey('ngpa_reference');
        expect($guide)->toHaveKey('is_alternative_mode');
        expect($guide['mode'])->toBe('small_value_procurement');
        expect($guide['is_alternative_mode'])->toBeTrue();
    });

    it('returns base document guide when no mode provided', function () {
        $guide = $this->validationService->getStageDocumentGuide(
            StageEnums::BID_OPENING,
            null
        );

        expect($guide)->not->toHaveKey('mode');
        expect($guide)->toHaveKey('stage');
        expect($guide)->toHaveKey('required_documents');
    });

    it('provides requirements comparison between base and mode', function () {
        $comparison = $this->validationService->getRequirementsComparison(
            StageEnums::MONITORING,
            ProcurementMode::DIRECT_ACQUISITION
        );

        expect($comparison)->toHaveKey('base_required_count');
        expect($comparison)->toHaveKey('mode_required_count');
        expect($comparison)->toHaveKey('is_simplified');
        expect($comparison)->toHaveKey('ngpa_reference');

        // Direct Acquisition should have simplified requirements
        expect($comparison['is_simplified'])->toBeTrue();
    });
});
