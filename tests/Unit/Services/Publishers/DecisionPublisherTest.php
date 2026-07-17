<?php

use App\Enums\ProcurementStatus;
use App\Enums\StageEnums;
use App\Services\Publishers\DecisionPublisher;
use App\Services\Publishers\EventPublisher;
use App\Services\Publishers\StatusPublisher;
use Illuminate\Support\Facades\Log;

use function Pest\Laravel\mock;

beforeEach(function () {
    $this->statusPublisher = mock(StatusPublisher::class);
    $this->eventPublisher = mock(EventPublisher::class);

    $this->publisher = new DecisionPublisher(
        $this->statusPublisher,
        $this->eventPublisher,
    );
});

describe('DecisionPublisher', function () {
    describe('publishDecision with pre_procurement_conference', function () {
        it('publishes held decision correctly', function () {
            $this->statusPublisher
                ->shouldReceive('publish')
                ->once()
                ->with(
                    'PR-001',
                    'Test Procurement',
                    StageEnums::PRE_PROCUREMENT_CONFERENCE,
                    ProcurementStatus::PRE_PROCUREMENT_CONFERENCE_HELD,
                    '0x123'
                );

            $this->eventPublisher
                ->shouldReceive('publish')
                ->once()
                ->withArgs(function ($prNumber, $title, $stage, $eventType, $category) {
                    return $prNumber === 'PR-001'
                        && $stage === 'pre_procurement_conference'
                        && $eventType === 'conference_decision'
                        && $category === 'Decision';
                });

            $result = $this->publisher->publishDecision(
                'pre_procurement_conference',
                'PR-001',
                'Test Procurement',
                true,
                '0x123'
            );

            expect($result['success'])->toBeTrue();
            expect($result['held'])->toBeTrue();
            expect($result['stage'])->toBe('pre_procurement_conference');
            expect($result['status'])->toBe('pre_procurement_conference_held');
        });

        it('publishes skipped decision correctly', function () {
            $this->statusPublisher
                ->shouldReceive('publish')
                ->once()
                ->with(
                    'PR-001',
                    'Test Procurement',
                    StageEnums::PRE_PROCUREMENT_CONFERENCE,
                    ProcurementStatus::PRE_PROCUREMENT_CONFERENCE_SKIPPED,
                    '0x123'
                );

            $this->eventPublisher
                ->shouldReceive('publish')
                ->once()
                ->withArgs(function ($prNumber, $title, $stage, $eventType) {
                    return $prNumber === 'PR-001'
                        && $eventType === 'conference_skipped';
                });

            $this->statusPublisher
                ->shouldReceive('publishTransition')
                ->once()
                ->withArgs(function ($prNumber, $title, $fromStage, $toStage, $status) {
                    return $prNumber === 'PR-001'
                        && $fromStage === StageEnums::PRE_PROCUREMENT_CONFERENCE
                        && $toStage === StageEnums::BIDDING_DOCUMENTS
                        && $status === ProcurementStatus::PRE_PROCUREMENT_CONFERENCE_COMPLETED;
                });

            $this->eventPublisher
                ->shouldReceive('publishStageTransition')
                ->once()
                ->withArgs(function ($prNumber, $title, $fromStage, $toStage) {
                    return $prNumber === 'PR-001'
                        && $fromStage === 'pre_procurement_conference'
                        && $toStage === 'bidding_documents';
                });

            $result = $this->publisher->publishDecision(
                'pre_procurement_conference',
                'PR-001',
                'Test Procurement',
                false,
                '0x123'
            );

            expect($result['success'])->toBeTrue();
            expect($result['held'])->toBeFalse();
            expect($result['stage'])->toBe('pre_procurement_conference');
            expect($result['status'])->toBe('pre_procurement_conference_skipped');
            expect($result['next_stage'])->toBe('bidding_documents');
        });
    });

    describe('publishDecision with pre_bid_conference', function () {
        it('publishes held decision correctly', function () {
            $this->statusPublisher
                ->shouldReceive('publish')
                ->once()
                ->with(
                    'PR-002',
                    'Pre-Bid Test',
                    StageEnums::PRE_BID_CONFERENCE,
                    ProcurementStatus::PRE_BID_CONFERENCE_HELD,
                    '0x456'
                );

            $this->eventPublisher
                ->shouldReceive('publish')
                ->once()
                ->withArgs(function ($prNumber, $title, $stage, $eventType) {
                    return $prNumber === 'PR-002'
                        && $stage === 'pre_bid_conference'
                        && $eventType === 'conference_decision';
                });

            $result = $this->publisher->publishDecision(
                'pre_bid_conference',
                'PR-002',
                'Pre-Bid Test',
                true,
                '0x456'
            );

            expect($result['success'])->toBeTrue();
            expect($result['held'])->toBeTrue();
            expect($result['stage'])->toBe('pre_bid_conference');
            expect($result['status'])->toBe('pre_bid_conference_held');
        });

        it('publishes skipped decision and transitions to supplemental bid bulletin', function () {
            $this->statusPublisher
                ->shouldReceive('publish')
                ->once();

            $this->eventPublisher
                ->shouldReceive('publish')
                ->once();

            $this->statusPublisher
                ->shouldReceive('publishTransition')
                ->once()
                ->withArgs(function ($prNumber, $title, $fromStage, $toStage) {
                    return $fromStage === StageEnums::PRE_BID_CONFERENCE
                        && $toStage === StageEnums::SUPPLEMENTAL_BID_BULLETIN;
                });

            $this->eventPublisher
                ->shouldReceive('publishStageTransition')
                ->once();

            $result = $this->publisher->publishDecision(
                'pre_bid_conference',
                'PR-002',
                'Pre-Bid Test',
                false,
                '0x456'
            );

            expect($result['next_stage'])->toBe('supplemental_bid_bulletin');
        });
    });

    describe('publishDecision with supplemental_bid_bulletin', function () {
        it('publishes needed decision correctly', function () {
            $this->statusPublisher
                ->shouldReceive('publish')
                ->once()
                ->with(
                    'PR-003',
                    'SBB Test',
                    StageEnums::SUPPLEMENTAL_BID_BULLETIN,
                    ProcurementStatus::SUPPLEMENTAL_BULLETINS_ONGOING,
                    '0x789'
                );

            $this->eventPublisher
                ->shouldReceive('publish')
                ->once()
                ->withArgs(function ($prNumber, $title, $stage, $eventType) {
                    return $eventType === 'bulletin_decision';
                });

            $result = $this->publisher->publishDecision(
                'supplemental_bid_bulletin',
                'PR-003',
                'SBB Test',
                true,
                '0x789'
            );

            expect($result['success'])->toBeTrue();
            expect($result['held'])->toBeTrue();
            expect($result['stage'])->toBe('supplemental_bid_bulletin');
            expect($result['status'])->toBe('supplemental_bulletins_ongoing');
        });

        it('publishes not needed decision and transitions to bid opening', function () {
            $this->statusPublisher
                ->shouldReceive('publish')
                ->once();

            $this->eventPublisher
                ->shouldReceive('publish')
                ->once()
                ->withArgs(function ($prNumber, $title, $stage, $eventType) {
                    return $eventType === 'bulletin_skipped';
                });

            $this->statusPublisher
                ->shouldReceive('publishTransition')
                ->once()
                ->withArgs(function ($prNumber, $title, $fromStage, $toStage) {
                    return $toStage === StageEnums::BID_OPENING;
                });

            $this->eventPublisher
                ->shouldReceive('publishStageTransition')
                ->once();

            $result = $this->publisher->publishDecision(
                'supplemental_bid_bulletin',
                'PR-003',
                'SBB Test',
                false,
                '0x789'
            );

            expect($result['next_stage'])->toBe('bid_opening');
        });
    });

    describe('publishDecision error handling', function () {
        it('throws exception for unknown decision type', function () {
            $this->publisher->publishDecision(
                'unknown_type',
                'PR-001',
                'Test',
                true,
                '0x123'
            );
        })->throws(InvalidArgumentException::class, 'Unknown decision type: unknown_type');

        it('returns error result when publisher fails', function () {
            // Mock the Log facade for this specific test
            Log::shouldReceive('error')
                ->once()
                ->withArgs(function ($message, $context) {
                    return str_contains($message, 'Failed to publish')
                        && $context['error'] === 'Connection failed';
                });

            $this->statusPublisher
                ->shouldReceive('publish')
                ->once()
                ->andThrow(new Exception('Connection failed'));

            $result = $this->publisher->publishDecision(
                'pre_procurement_conference',
                'PR-001',
                'Test',
                true,
                '0x123'
            );

            expect($result['success'])->toBeFalse();
            expect($result['error'])->toBe('Connection failed');
        });
    });

    describe('getUploadRoute', function () {
        it('returns pre-procurement route for pre-procurement conference', function () {
            $result = $this->publisher->getUploadRoute('pre_procurement_conference', 'PR-001');

            expect($result['route'])->toBe('bac-secretariat.procurement.pre-procurement.show');
            expect($result['params']['pr_number'])->toBe('PR-001');
            expect($result['params']['stage'])->toBe('pre_procurement_conference');
        });

        it('returns bidding route for pre-bid conference', function () {
            $result = $this->publisher->getUploadRoute('pre_bid_conference', 'PR-001');

            expect($result['route'])->toBe('bac-secretariat.procurement.bidding.show');
            expect($result['params']['pr_number'])->toBe('PR-001');
        });

        it('returns bidding route for supplemental bid bulletin', function () {
            $result = $this->publisher->getUploadRoute('supplemental_bid_bulletin', 'PR-001');

            expect($result['route'])->toBe('bac-secretariat.procurement.bidding.show');
            expect($result['params']['pr_number'])->toBe('PR-001');
        });

        it('throws exception for unknown decision type', function () {
            $this->publisher->getUploadRoute('unknown', 'PR-001');
        })->throws(InvalidArgumentException::class);
    });

    describe('getDecisionField', function () {
        it('returns conference_held for pre-procurement conference', function () {
            $field = $this->publisher->getDecisionField('pre_procurement_conference');
            expect($field)->toBe('conference_held');
        });

        it('returns conference_held for pre-bid conference', function () {
            $field = $this->publisher->getDecisionField('pre_bid_conference');
            expect($field)->toBe('conference_held');
        });

        it('returns supplemental_bid_needed for supplemental bid bulletin', function () {
            $field = $this->publisher->getDecisionField('supplemental_bid_bulletin');
            expect($field)->toBe('supplemental_bid_needed');
        });

        it('throws exception for unknown decision type', function () {
            $this->publisher->getDecisionField('unknown');
        })->throws(InvalidArgumentException::class);
    });

    describe('getStage', function () {
        it('returns PRE_PROCUREMENT_CONFERENCE stage', function () {
            $stage = $this->publisher->getStage('pre_procurement_conference');
            expect($stage)->toBe(StageEnums::PRE_PROCUREMENT_CONFERENCE);
        });

        it('returns PRE_BID_CONFERENCE stage', function () {
            $stage = $this->publisher->getStage('pre_bid_conference');
            expect($stage)->toBe(StageEnums::PRE_BID_CONFERENCE);
        });

        it('returns SUPPLEMENTAL_BID_BULLETIN stage', function () {
            $stage = $this->publisher->getStage('supplemental_bid_bulletin');
            expect($stage)->toBe(StageEnums::SUPPLEMENTAL_BID_BULLETIN);
        });

        it('throws exception for unknown decision type', function () {
            $this->publisher->getStage('unknown');
        })->throws(InvalidArgumentException::class);
    });
});
