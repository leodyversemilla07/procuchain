<?php

use App\Enums\ProcurementModeEnums;
use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Services\Procurement\StageStatusMapper;

beforeEach(function () {
    $this->mapper = new StageStatusMapper;
});

describe('StageStatusMapper', function () {
    describe('getInitialStatus', function () {
        it('returns correct initial status for procurement initiation', function () {
            $status = $this->mapper->getInitialStatus(StageEnums::PROCUREMENT_INITIATION);

            expect($status)->toBe(StatusEnums::PROCUREMENT_INITIATED);
        });

        it('returns correct initial status for pre-procurement conference', function () {
            $status = $this->mapper->getInitialStatus(StageEnums::PRE_PROCUREMENT_CONFERENCE);

            expect($status)->toBe(StatusEnums::PROCUREMENT_SUBMITTED);
        });

        it('returns correct initial status for bidding documents', function () {
            $status = $this->mapper->getInitialStatus(StageEnums::BIDDING_DOCUMENTS);

            expect($status)->toBe(StatusEnums::PRE_PROCUREMENT_CONFERENCE_COMPLETED);
        });

        it('returns correct initial status for pre-bid conference', function () {
            $status = $this->mapper->getInitialStatus(StageEnums::PRE_BID_CONFERENCE);

            expect($status)->toBe(StatusEnums::BIDDING_DOCUMENTS_PUBLISHED);
        });

        it('returns correct initial status for bid opening', function () {
            $status = $this->mapper->getInitialStatus(StageEnums::BID_OPENING);

            expect($status)->toBe(StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED);
        });

        it('returns correct initial status for bid evaluation', function () {
            $status = $this->mapper->getInitialStatus(StageEnums::BID_EVALUATION);

            expect($status)->toBe(StatusEnums::BIDS_OPENED);
        });

        it('returns correct initial status for post qualification', function () {
            $status = $this->mapper->getInitialStatus(StageEnums::POST_QUALIFICATION);

            expect($status)->toBe(StatusEnums::BIDS_EVALUATED);
        });

        it('returns correct initial status for notice of award', function () {
            $status = $this->mapper->getInitialStatus(StageEnums::NOTICE_OF_AWARD);

            expect($status)->toBe(StatusEnums::RESOLUTION_RECORDED);
        });

        it('returns correct initial status for completed stage', function () {
            $status = $this->mapper->getInitialStatus(StageEnums::COMPLETED);

            expect($status)->toBe(StatusEnums::COMPLETED);
        });
    });

    describe('getInitialStatus with procurement mode', function () {
        it('returns POST_QUALIFICATION_VERIFIED for BAC_RESOLUTION with Competitive Bidding', function () {
            $status = $this->mapper->getInitialStatus(
                StageEnums::BAC_RESOLUTION,
                ProcurementModeEnums::COMPETITIVE_BIDDING
            );

            expect($status)->toBe(StatusEnums::POST_QUALIFICATION_VERIFIED);
        });

        it('returns ABSTRACT_PREPARED for BAC_RESOLUTION with Small Value Procurement', function () {
            $status = $this->mapper->getInitialStatus(
                StageEnums::BAC_RESOLUTION,
                ProcurementModeEnums::SMALL_VALUE_PROCUREMENT
            );

            expect($status)->toBe(StatusEnums::ABSTRACT_PREPARED);
        });

        it('returns ABSTRACT_PREPARED for BAC_RESOLUTION with Direct Contracting', function () {
            $status = $this->mapper->getInitialStatus(
                StageEnums::BAC_RESOLUTION,
                ProcurementModeEnums::DIRECT_CONTRACTING
            );

            expect($status)->toBe(StatusEnums::ABSTRACT_PREPARED);
        });

        it('returns ABSTRACT_PREPARED for BAC_RESOLUTION with Repeat Order', function () {
            $status = $this->mapper->getInitialStatus(
                StageEnums::BAC_RESOLUTION,
                ProcurementModeEnums::REPEAT_ORDER
            );

            expect($status)->toBe(StatusEnums::ABSTRACT_PREPARED);
        });
    });

    describe('getOngoingStatus', function () {
        it('returns PRE_PROCUREMENT_CONFERENCE_HELD for pre-procurement conference', function () {
            $status = $this->mapper->getOngoingStatus(StageEnums::PRE_PROCUREMENT_CONFERENCE);

            expect($status)->toBe(StatusEnums::PRE_PROCUREMENT_CONFERENCE_HELD);
        });

        it('returns PRE_BID_CONFERENCE_HELD for pre-bid conference', function () {
            $status = $this->mapper->getOngoingStatus(StageEnums::PRE_BID_CONFERENCE);

            expect($status)->toBe(StatusEnums::PRE_BID_CONFERENCE_HELD);
        });

        it('returns SUPPLEMENTAL_BULLETINS_ONGOING for supplemental bid bulletin', function () {
            $status = $this->mapper->getOngoingStatus(StageEnums::SUPPLEMENTAL_BID_BULLETIN);

            expect($status)->toBe(StatusEnums::SUPPLEMENTAL_BULLETINS_ONGOING);
        });

        it('returns initial status when no ongoing status defined (bidding documents)', function () {
            $status = $this->mapper->getOngoingStatus(StageEnums::BIDDING_DOCUMENTS);

            expect($status)->toBe(StatusEnums::PRE_PROCUREMENT_CONFERENCE_COMPLETED);
        });

        it('returns initial status when no ongoing status defined (notice of award)', function () {
            $status = $this->mapper->getOngoingStatus(StageEnums::NOTICE_OF_AWARD);

            expect($status)->toBe(StatusEnums::RESOLUTION_RECORDED);
        });
    });

    describe('getCompletionStatus', function () {
        it('returns correct completion status for procurement initiation', function () {
            $status = $this->mapper->getCompletionStatus(StageEnums::PROCUREMENT_INITIATION);

            expect($status)->toBe(StatusEnums::PROCUREMENT_INITIATED);
        });

        it('returns correct completion status for pre-procurement conference', function () {
            $status = $this->mapper->getCompletionStatus(StageEnums::PRE_PROCUREMENT_CONFERENCE);

            expect($status)->toBe(StatusEnums::PRE_PROCUREMENT_CONFERENCE_COMPLETED);
        });

        it('returns correct completion status for bidding documents', function () {
            $status = $this->mapper->getCompletionStatus(StageEnums::BIDDING_DOCUMENTS);

            expect($status)->toBe(StatusEnums::BIDDING_DOCUMENTS_PUBLISHED);
        });

        it('returns correct completion status for pre-bid conference', function () {
            $status = $this->mapper->getCompletionStatus(StageEnums::PRE_BID_CONFERENCE);

            expect($status)->toBe(StatusEnums::PRE_BID_CONFERENCE_COMPLETED);
        });

        it('returns correct completion status for supplemental bid bulletin', function () {
            $status = $this->mapper->getCompletionStatus(StageEnums::SUPPLEMENTAL_BID_BULLETIN);

            expect($status)->toBe(StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED);
        });

        it('returns correct completion status for bid opening', function () {
            $status = $this->mapper->getCompletionStatus(StageEnums::BID_OPENING);

            expect($status)->toBe(StatusEnums::BIDS_OPENED);
        });

        it('returns correct completion status for bid evaluation', function () {
            $status = $this->mapper->getCompletionStatus(StageEnums::BID_EVALUATION);

            expect($status)->toBe(StatusEnums::BIDS_EVALUATED);
        });

        it('returns correct completion status for post qualification', function () {
            $status = $this->mapper->getCompletionStatus(StageEnums::POST_QUALIFICATION);

            expect($status)->toBe(StatusEnums::POST_QUALIFICATION_VERIFIED);
        });

        it('returns correct completion status for BAC resolution', function () {
            $status = $this->mapper->getCompletionStatus(StageEnums::BAC_RESOLUTION);

            expect($status)->toBe(StatusEnums::RESOLUTION_RECORDED);
        });

        it('returns correct completion status for notice of award', function () {
            $status = $this->mapper->getCompletionStatus(StageEnums::NOTICE_OF_AWARD);

            expect($status)->toBe(StatusEnums::AWARDED);
        });

        it('returns correct completion status for performance bond', function () {
            $status = $this->mapper->getCompletionStatus(StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO);

            expect($status)->toBe(StatusEnums::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED);
        });

        it('returns correct completion status for notice to proceed', function () {
            $status = $this->mapper->getCompletionStatus(StageEnums::NOTICE_TO_PROCEED);

            expect($status)->toBe(StatusEnums::NTP_RECORDED);
        });

        it('returns correct completion status for monitoring', function () {
            $status = $this->mapper->getCompletionStatus(StageEnums::MONITORING);

            expect($status)->toBe(StatusEnums::MONITORING_COMPLETED);
        });

        it('returns correct completion status for completion', function () {
            $status = $this->mapper->getCompletionStatus(StageEnums::COMPLETION);

            expect($status)->toBe(StatusEnums::COMPLETION_DOCUMENTS_UPLOADED);
        });

        it('returns correct completion status for completed', function () {
            $status = $this->mapper->getCompletionStatus(StageEnums::COMPLETED);

            expect($status)->toBe(StatusEnums::COMPLETED);
        });
    });

    describe('hasOngoingStatus', function () {
        it('returns true for stages with dedicated ongoing status', function () {
            $stagesWithOngoing = [
                StageEnums::PRE_PROCUREMENT_CONFERENCE,
                StageEnums::PRE_BID_CONFERENCE,
                StageEnums::SUPPLEMENTAL_BID_BULLETIN,
            ];

            foreach ($stagesWithOngoing as $stage) {
                expect($this->mapper->hasOngoingStatus($stage))->toBeTrue(
                    "Expected {$stage->value} to have ongoing status"
                );
            }
        });

        it('returns false for stages without dedicated ongoing status', function () {
            $stagesWithoutOngoing = [
                StageEnums::PROCUREMENT_INITIATION,
                StageEnums::BIDDING_DOCUMENTS,
                StageEnums::NOTICE_OF_AWARD,
                StageEnums::NOTICE_TO_PROCEED,
                StageEnums::MONITORING,
                StageEnums::COMPLETION,
                StageEnums::COMPLETED,
            ];

            foreach ($stagesWithoutOngoing as $stage) {
                expect($this->mapper->hasOngoingStatus($stage))->toBeFalse(
                    "Expected {$stage->value} to not have ongoing status"
                );
            }
        });
    });

    describe('getStatusesForStage', function () {
        it('returns all three status types', function () {
            $statuses = $this->mapper->getStatusesForStage(StageEnums::PRE_PROCUREMENT_CONFERENCE);

            expect($statuses)->toHaveKeys(['initial', 'ongoing', 'completion']);
        });

        it('returns StatusEnums instances', function () {
            $statuses = $this->mapper->getStatusesForStage(StageEnums::PRE_PROCUREMENT_CONFERENCE);

            expect($statuses['initial'])->toBeInstanceOf(StatusEnums::class);
            expect($statuses['ongoing'])->toBeInstanceOf(StatusEnums::class);
            expect($statuses['completion'])->toBeInstanceOf(StatusEnums::class);
        });

        it('returns mode-aware initial status for BAC resolution', function () {
            $statusesCompetitiveBidding = $this->mapper->getStatusesForStage(
                StageEnums::BAC_RESOLUTION,
                ProcurementModeEnums::COMPETITIVE_BIDDING
            );

            $statusesSVP = $this->mapper->getStatusesForStage(
                StageEnums::BAC_RESOLUTION,
                ProcurementModeEnums::SMALL_VALUE_PROCUREMENT
            );

            expect($statusesCompetitiveBidding['initial'])->toBe(StatusEnums::POST_QUALIFICATION_VERIFIED);
            expect($statusesSVP['initial'])->toBe(StatusEnums::ABSTRACT_PREPARED);
        });
    });

    describe('Data Integrity', function () {
        it('returns StatusEnums for all stages initial status', function () {
            foreach (StageEnums::cases() as $stage) {
                $status = $this->mapper->getInitialStatus($stage);
                expect($status)->toBeInstanceOf(StatusEnums::class);
            }
        });

        it('returns StatusEnums for all stages completion status', function () {
            foreach (StageEnums::cases() as $stage) {
                $status = $this->mapper->getCompletionStatus($stage);
                expect($status)->toBeInstanceOf(StatusEnums::class);
            }
        });

        it('returns StatusEnums for all stages ongoing status', function () {
            foreach (StageEnums::cases() as $stage) {
                $status = $this->mapper->getOngoingStatus($stage);
                expect($status)->toBeInstanceOf(StatusEnums::class);
            }
        });
    });

    describe('RFQ-based workflow', function () {
        it('handles request for quotation stage', function () {
            $initial = $this->mapper->getInitialStatus(StageEnums::REQUEST_FOR_QUOTATION);
            $completion = $this->mapper->getCompletionStatus(StageEnums::REQUEST_FOR_QUOTATION);

            expect($initial)->toBe(StatusEnums::PROCUREMENT_SUBMITTED);
            expect($completion)->toBe(StatusEnums::QUOTATIONS_RECEIVED);
        });

        it('handles abstract of quotations stage', function () {
            $initial = $this->mapper->getInitialStatus(StageEnums::ABSTRACT_OF_QUOTATIONS);
            $completion = $this->mapper->getCompletionStatus(StageEnums::ABSTRACT_OF_QUOTATIONS);

            expect($initial)->toBe(StatusEnums::QUOTATIONS_RECEIVED);
            expect($completion)->toBe(StatusEnums::ABSTRACT_PREPARED);
        });
    });
});
