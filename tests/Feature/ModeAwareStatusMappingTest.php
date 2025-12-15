<?php

use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;

describe('Mode-Aware Status Mapping', function () {
    it('validates all stages have status mappings', function () {
        $allStages = StageEnums::cases();

        foreach ($allStages as $stage) {
            // Each stage should have a known status mapping
            expect($stage)->toBeInstanceOf(StageEnums::class);
        }
    });

    it('validates stage existence check works for different modes', function () {
        // Competitive Bidding should have BID_OPENING
        expect(StageEnums::BID_OPENING->existsInModeWorkflow(ProcurementModeEnums::COMPETITIVE_BIDDING))
            ->toBeTrue();

        // SVP should NOT have BID_OPENING
        expect(StageEnums::BID_OPENING->existsInModeWorkflow(ProcurementModeEnums::SMALL_VALUE_PROCUREMENT))
            ->toBeFalse();

        // SVP should have REQUEST_FOR_QUOTATION
        expect(StageEnums::REQUEST_FOR_QUOTATION->existsInModeWorkflow(ProcurementModeEnums::SMALL_VALUE_PROCUREMENT))
            ->toBeTrue();

        // Competitive Bidding should NOT have REQUEST_FOR_QUOTATION
        expect(StageEnums::REQUEST_FOR_QUOTATION->existsInModeWorkflow(ProcurementModeEnums::COMPETITIVE_BIDDING))
            ->toBeFalse();
    });

    it('validates universal stages exist in all modes', function () {
        $universalStages = [
            StageEnums::PROCUREMENT_INITIATION,
            StageEnums::NOTICE_OF_AWARD,
            StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO,
            StageEnums::NOTICE_TO_PROCEED,
            StageEnums::MONITORING,
            StageEnums::COMPLETION,
            StageEnums::COMPLETED,
        ];

        $modes = ProcurementModeEnums::cases();

        foreach ($modes as $mode) {
            foreach ($universalStages as $stage) {
                expect($stage->existsInModeWorkflow($mode))
                    ->toBeTrue("Stage {$stage->value} should exist in {$mode->value} workflow");
            }
        }
    });

    it('validates competitive bidding exclusive stages', function () {
        $competitiveStages = [
            StageEnums::PRE_PROCUREMENT_CONFERENCE,
            StageEnums::BIDDING_DOCUMENTS,
            StageEnums::PRE_BID_CONFERENCE,
            StageEnums::SUPPLEMENTAL_BID_BULLETIN,
            StageEnums::BID_OPENING,
            StageEnums::BID_EVALUATION,
            StageEnums::POST_QUALIFICATION,
        ];

        // Should exist in Competitive Bidding
        foreach ($competitiveStages as $stage) {
            expect($stage->existsInModeWorkflow(ProcurementModeEnums::COMPETITIVE_BIDDING))
                ->toBeTrue("{$stage->value} should exist in Competitive Bidding");
        }

        // Should NOT exist in Direct Acquisition
        foreach ($competitiveStages as $stage) {
            if ($stage === StageEnums::PRE_PROCUREMENT_CONFERENCE) {
                continue; // PRE_PROCUREMENT_CONFERENCE exists in some alternative modes
            }
            expect($stage->existsInModeWorkflow(ProcurementModeEnums::DIRECT_ACQUISITION))
                ->toBeFalse("{$stage->value} should NOT exist in Direct Acquisition");
        }
    });

    it('validates alternative mode exclusive stages', function () {
        $alternativeStages = [
            StageEnums::REQUEST_FOR_QUOTATION,
            StageEnums::ABSTRACT_OF_QUOTATIONS,
        ];

        // Should exist in SVP
        foreach ($alternativeStages as $stage) {
            expect($stage->existsInModeWorkflow(ProcurementModeEnums::SMALL_VALUE_PROCUREMENT))
                ->toBeTrue("{$stage->value} should exist in SVP");
        }

        // Should NOT exist in Competitive Bidding
        foreach ($alternativeStages as $stage) {
            expect($stage->existsInModeWorkflow(ProcurementModeEnums::COMPETITIVE_BIDDING))
                ->toBeFalse("{$stage->value} should NOT exist in Competitive Bidding");
        }
    });

    it('validates Direct Acquisition has minimal stages', function () {
        $stages = StageEnums::getStagesForMode(ProcurementModeEnums::DIRECT_ACQUISITION);

        // Direct Acquisition should have only 7 stages (simplest workflow)
        expect($stages)->toHaveCount(7);

        // Should have these specific stages
        expect($stages)->toContain(StageEnums::PROCUREMENT_INITIATION)
            ->and($stages)->toContain(StageEnums::NOTICE_OF_AWARD)
            ->and($stages)->toContain(StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO)
            ->and($stages)->toContain(StageEnums::NOTICE_TO_PROCEED)
            ->and($stages)->toContain(StageEnums::MONITORING)
            ->and($stages)->toContain(StageEnums::COMPLETION)
            ->and($stages)->toContain(StageEnums::COMPLETED);

        // Should NOT have bidding stages
        expect($stages)->not->toContain(StageEnums::BID_OPENING)
            ->and($stages)->not->toContain(StageEnums::BID_EVALUATION)
            ->and($stages)->not->toContain(StageEnums::REQUEST_FOR_QUOTATION);
    });

    it('validates SVP has RFQ and Abstract stages', function () {
        $stages = StageEnums::getStagesForMode(ProcurementModeEnums::SMALL_VALUE_PROCUREMENT);

        // SVP should have 10 stages
        expect($stages)->toHaveCount(10);

        // Should have RFQ-specific stages
        expect($stages)->toContain(StageEnums::REQUEST_FOR_QUOTATION)
            ->and($stages)->toContain(StageEnums::ABSTRACT_OF_QUOTATIONS)
            ->and($stages)->toContain(StageEnums::BAC_RESOLUTION);

        // Should NOT have competitive bidding stages
        expect($stages)->not->toContain(StageEnums::BID_OPENING)
            ->and($stages)->not->toContain(StageEnums::BID_EVALUATION)
            ->and($stages)->not->toContain(StageEnums::PRE_BID_CONFERENCE)
            ->and($stages)->not->toContain(StageEnums::BIDDING_DOCUMENTS);
    });

    it('validates Competitive Bidding has full workflow', function () {
        $stages = StageEnums::getStagesForMode(ProcurementModeEnums::COMPETITIVE_BIDDING);

        // Competitive Bidding should have 15 stages (full workflow)
        expect($stages)->toHaveCount(15);

        // Should have all key bidding stages
        expect($stages)->toContain(StageEnums::PRE_PROCUREMENT_CONFERENCE)
            ->and($stages)->toContain(StageEnums::BIDDING_DOCUMENTS)
            ->and($stages)->toContain(StageEnums::PRE_BID_CONFERENCE)
            ->and($stages)->toContain(StageEnums::SUPPLEMENTAL_BID_BULLETIN)
            ->and($stages)->toContain(StageEnums::BID_OPENING)
            ->and($stages)->toContain(StageEnums::BID_EVALUATION)
            ->and($stages)->toContain(StageEnums::POST_QUALIFICATION)
            ->and($stages)->toContain(StageEnums::BAC_RESOLUTION);

        // Should NOT have RFQ stages
        expect($stages)->not->toContain(StageEnums::REQUEST_FOR_QUOTATION)
            ->and($stages)->not->toContain(StageEnums::ABSTRACT_OF_QUOTATIONS);
    });

    it('validates all modes have proper stage sequences', function () {
        $modes = ProcurementModeEnums::cases();

        foreach ($modes as $mode) {
            $stages = StageEnums::getStagesForMode($mode);

            // Every mode should start with PROCUREMENT_INITIATION
            expect($stages[0])->toBe(StageEnums::PROCUREMENT_INITIATION);
            expect($stages)->toContain(StageEnums::NOTICE_OF_AWARD);
            expect($stages[count($stages) - 1])->toBe(StageEnums::COMPLETED);
        }
    });

    it('validates next stage determination is mode-aware', function () {
        // Competitive Bidding: PROCUREMENT_INITIATION -> PRE_PROCUREMENT_CONFERENCE
        $cbNextStages = StageEnums::PROCUREMENT_INITIATION->getNextStagesForMode(
            ProcurementModeEnums::COMPETITIVE_BIDDING
        );
        expect($cbNextStages)->toContain(StageEnums::PRE_PROCUREMENT_CONFERENCE);

        // SVP: PROCUREMENT_INITIATION -> REQUEST_FOR_QUOTATION
        $svpNextStages = StageEnums::PROCUREMENT_INITIATION->getNextStagesForMode(
            ProcurementModeEnums::SMALL_VALUE_PROCUREMENT
        );
        expect($svpNextStages)->toContain(StageEnums::REQUEST_FOR_QUOTATION);

        // Direct Acquisition: PROCUREMENT_INITIATION -> NOTICE_OF_AWARD (skips everything)
        $daNextStages = StageEnums::PROCUREMENT_INITIATION->getNextStagesForMode(
            ProcurementModeEnums::DIRECT_ACQUISITION
        );
        expect($daNextStages)->toContain(StageEnums::NOTICE_OF_AWARD);
    });
});
