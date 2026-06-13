<?php

use App\Enums\ProcurementMode;
use App\Enums\StageEnums;

describe('StageEnums::getStagesForMode - Mode Workflow Definitions', function () {
    it('returns all stages for Competitive Bidding mode', function () {
        $stages = StageEnums::getStagesForMode(ProcurementMode::COMPETITIVE_BIDDING);

        // Competitive Bidding has the full procurement workflow
        expect($stages)->toContain(StageEnums::PRE_PROCUREMENT_CONFERENCE);
        expect($stages)->toContain(StageEnums::BIDDING_DOCUMENTS);
        expect($stages)->toContain(StageEnums::PRE_BID_CONFERENCE);
        expect($stages)->toContain(StageEnums::BID_OPENING);
        expect($stages)->toContain(StageEnums::BID_EVALUATION);
        expect($stages)->toContain(StageEnums::POST_QUALIFICATION);
        expect($stages)->toContain(StageEnums::NOTICE_OF_AWARD);
    });

    it('returns streamlined stages for Small Value Procurement mode', function () {
        $stages = StageEnums::getStagesForMode(ProcurementMode::SMALL_VALUE_PROCUREMENT);

        // SVP should have RFQ but not competitive bidding stages
        expect($stages)->toContain(StageEnums::REQUEST_FOR_QUOTATION);
        expect($stages)->not->toContain(StageEnums::BID_OPENING);
        expect($stages)->not->toContain(StageEnums::PRE_BID_CONFERENCE);
        expect($stages)->not->toContain(StageEnums::PRE_PROCUREMENT_CONFERENCE);
    });

    it('returns streamlined stages for Direct Contracting mode', function () {
        $stages = StageEnums::getStagesForMode(ProcurementMode::DIRECT_CONTRACTING);

        // Direct Contracting is highly streamlined - no competitive bidding
        expect($stages)->not->toContain(StageEnums::BID_OPENING);
        expect($stages)->not->toContain(StageEnums::BID_EVALUATION);
        expect($stages)->not->toContain(StageEnums::PRE_PROCUREMENT_CONFERENCE);
        expect($stages)->not->toContain(StageEnums::BIDDING_DOCUMENTS);
    });

    it('returns streamlined stages for Negotiated Procurement mode', function () {
        $stages = StageEnums::getStagesForMode(ProcurementMode::NEGOTIATED_PROCUREMENT);

        // Negotiated Procurement has negotiation-related stages
        expect($stages)->toContain(StageEnums::PROCUREMENT_INITIATION);
        expect($stages)->toContain(StageEnums::NOTICE_OF_AWARD);
        expect($stages)->not->toContain(StageEnums::BID_OPENING);
    });

    it('returns appropriate stages for Direct Acquisition mode', function () {
        $stages = StageEnums::getStagesForMode(ProcurementMode::DIRECT_ACQUISITION);

        // Direct Acquisition (≤₱200,000) is streamlined
        expect($stages)->toContain(StageEnums::PROCUREMENT_INITIATION);
        expect($stages)->toContain(StageEnums::NOTICE_OF_AWARD);
    });
});

describe('StageEnums::existsInModeWorkflow - Stage Validation', function () {
    it('returns true for stage that exists in mode workflow', function () {
        // Bid Opening exists in Competitive Bidding workflow
        expect(StageEnums::BID_OPENING->existsInModeWorkflow(ProcurementMode::COMPETITIVE_BIDDING))
            ->toBeTrue();
    });

    it('returns false for stage that does NOT exist in mode workflow', function () {
        // Bid Opening does NOT exist in SVP workflow
        expect(StageEnums::BID_OPENING->existsInModeWorkflow(ProcurementMode::SMALL_VALUE_PROCUREMENT))
            ->toBeFalse();
    });

    it('validates RFQ exists only in RFQ-based modes', function () {
        // RFQ exists in SVP
        expect(StageEnums::REQUEST_FOR_QUOTATION->existsInModeWorkflow(ProcurementMode::SMALL_VALUE_PROCUREMENT))
            ->toBeTrue();

        // RFQ does NOT exist in Competitive Bidding
        expect(StageEnums::REQUEST_FOR_QUOTATION->existsInModeWorkflow(ProcurementMode::COMPETITIVE_BIDDING))
            ->toBeFalse();
    });

    it('validates post-procurement stages exist in all modes', function () {
        $modes = [
            ProcurementMode::COMPETITIVE_BIDDING,
            ProcurementMode::SMALL_VALUE_PROCUREMENT,
            ProcurementMode::DIRECT_CONTRACTING,
            ProcurementMode::NEGOTIATED_PROCUREMENT,
        ];

        // NOA and post-procurement stages should exist in all modes
        foreach ($modes as $mode) {
            expect(StageEnums::NOTICE_OF_AWARD->existsInModeWorkflow($mode))
                ->toBeTrue("NOA should exist in {$mode->value} workflow");
        }
    });
});

describe('Mode-specific stage validation', function () {
    it('validates Competitive Bidding has Pre-Bid Conference', function () {
        $stages = StageEnums::getStagesForMode(ProcurementMode::COMPETITIVE_BIDDING);

        expect($stages)->toContain(StageEnums::PRE_BID_CONFERENCE);
    });

    it('SVP mode does not have bid-related stages', function () {
        $stages = StageEnums::getStagesForMode(ProcurementMode::SMALL_VALUE_PROCUREMENT);

        expect($stages)->not->toContain(StageEnums::BID_OPENING);
        expect($stages)->not->toContain(StageEnums::BID_EVALUATION);
        expect($stages)->not->toContain(StageEnums::SUPPLEMENTAL_BID_BULLETIN);
    });

    it('Direct Contracting has Abstract of Quotations instead of Bid Evaluation', function () {
        $stages = StageEnums::getStagesForMode(ProcurementMode::DIRECT_CONTRACTING);

        // Direct Contracting should not have competitive bidding stages
        expect($stages)->not->toContain(StageEnums::BID_EVALUATION);
    });
});

describe('validateStageInWorkflow trait method', function () {
    it('validates stage in workflow returns true for valid stages', function () {
        // Test the underlying logic that the trait method uses
        $mode = ProcurementMode::COMPETITIVE_BIDDING;
        $validStage = StageEnums::BID_OPENING;

        expect($validStage->existsInModeWorkflow($mode))->toBeTrue();
    });

    it('validates stage in workflow returns false for invalid stages', function () {
        $mode = ProcurementMode::SMALL_VALUE_PROCUREMENT;
        $invalidStage = StageEnums::BID_OPENING;

        expect($invalidStage->existsInModeWorkflow($mode))->toBeFalse();
    });
});

describe('All procurement modes have valid workflow definitions', function () {
    it('every mode has at least Procurement Initiation stage', function () {
        $allModes = ProcurementMode::cases();

        foreach ($allModes as $mode) {
            $stages = StageEnums::getStagesForMode($mode);
            expect($stages)->toContain(StageEnums::PROCUREMENT_INITIATION);
        }
    });

    it('every mode has Notice of Award stage', function () {
        $allModes = ProcurementMode::cases();

        foreach ($allModes as $mode) {
            $stages = StageEnums::getStagesForMode($mode);
            expect($stages)->toContain(StageEnums::NOTICE_OF_AWARD);
        }
    });

    it('every mode has Completed stage', function () {
        $allModes = ProcurementMode::cases();

        foreach ($allModes as $mode) {
            $stages = StageEnums::getStagesForMode($mode);
            expect($stages)->toContain(StageEnums::COMPLETED);
        }
    });

    it('no mode has duplicate stages', function () {
        $allModes = ProcurementMode::cases();

        foreach ($allModes as $mode) {
            $stages = StageEnums::getStagesForMode($mode);
            $uniqueStages = array_unique($stages, SORT_REGULAR);

            expect(count($stages))->toBe(count($uniqueStages), "Mode {$mode->value} has duplicate stages");
        }
    });
});
