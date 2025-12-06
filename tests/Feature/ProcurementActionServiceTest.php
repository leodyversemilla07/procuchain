<?php

declare(strict_types=1);

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Repositories\ProcurementRepository;
use App\Services\Procurement\ProcurementActionService;

beforeEach(function () {
    $this->mockRepository = Mockery::mock(ProcurementRepository::class);
    $this->service = new ProcurementActionService($this->mockRepository);
});

describe('ProcurementActionService', function () {
    describe('getAvailableActions', function () {
        it('returns empty array for invalid stage', function () {
            $this->mockRepository->shouldReceive('findByProcurement')
                ->with('PR-TEST-001')
                ->andReturn(null);

            $actions = $this->service->getAvailableActions(
                'PR-TEST-001',
                'invalid_stage',
                'procurement_initiated',
                'bac_secretariat'
            );

            expect($actions)->toBeArray()
                ->and($actions)->toBeEmpty();
        });

        it('returns empty array for invalid status', function () {
            $this->mockRepository->shouldReceive('findByProcurement')
                ->with('PR-TEST-001')
                ->andReturn(null);

            $actions = $this->service->getAvailableActions(
                'PR-TEST-001',
                'procurement_initiation',
                'invalid_status',
                'bac_secretariat'
            );

            expect($actions)->toBeArray()
                ->and($actions)->toBeEmpty();
        });

        it('returns upload action for procurement initiation stage', function () {
            $this->mockRepository->shouldReceive('findByProcurement')
                ->with('PR-TEST-001')
                ->andReturn(null);

            $actions = $this->service->getAvailableActions(
                'PR-TEST-001',
                StageEnums::PROCUREMENT_INITIATION->value,
                StatusEnums::PROCUREMENT_INITIATED->value,
                'bac_secretariat'
            );

            expect($actions)->toBeArray()
                ->and($actions)->toHaveCount(1)
                ->and($actions[0]['type'])->toBe('upload')
                ->and($actions[0]['label'])->toBe('Upload Procurement Initiation Documents');
        });

        it('returns dialog action for pre-procurement decision', function () {
            $this->mockRepository->shouldReceive('findByProcurement')
                ->with('PR-TEST-001')
                ->andReturn(null);

            // After procurement initiation is complete, the stage transitions to PRE_PROCUREMENT_CONFERENCE
            // with status PROCUREMENT_SUBMITTED for Competitive Bidding mode
            $actions = $this->service->getAvailableActions(
                'PR-TEST-001',
                StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
                StatusEnums::PROCUREMENT_SUBMITTED->value,
                'bac_secretariat'
            );

            expect($actions)->toBeArray()
                ->and($actions)->toHaveCount(1)
                ->and($actions[0]['type'])->toBe('dialog')
                ->and($actions[0]['action'])->toBe('pre-procurement');
        });

        it('returns upload action for bid evaluation', function () {
            $this->mockRepository->shouldReceive('findByProcurement')
                ->with('PR-TEST-001')
                ->andReturn(null);

            $actions = $this->service->getAvailableActions(
                'PR-TEST-001',
                StageEnums::BID_EVALUATION->value,
                StatusEnums::BIDS_OPENED->value,
                'bac_secretariat'
            );

            expect($actions)->toBeArray()
                ->and($actions)->toHaveCount(1)
                ->and($actions[0]['type'])->toBe('upload')
                ->and($actions[0]['icon'])->toBe('chart');
        });

        it('returns no workflow actions for non-bac-secretariat role', function () {
            $this->mockRepository->shouldReceive('findByProcurement')
                ->with('PR-TEST-001')
                ->andReturn(null);

            $actions = $this->service->getAvailableActions(
                'PR-TEST-001',
                StageEnums::PROCUREMENT_INITIATION->value,
                StatusEnums::PROCUREMENT_INITIATED->value,
                'bac_chairman'
            );

            expect($actions)->toBeArray()
                ->and($actions)->toBeEmpty();
        });
    });

    describe('getStaticActions', function () {
        it('returns view details action for all roles', function () {
            $actions = $this->service->getStaticActions('PR-TEST-001', 'bac_secretariat');

            $viewAction = collect($actions)->firstWhere('type', 'view');
            expect($viewAction)->not->toBeNull()
                ->and($viewAction['label'])->toBe('View Details')
                ->and($viewAction['icon'])->toBe('eye');
        });

        it('returns verification report action', function () {
            $actions = $this->service->getStaticActions('PR-TEST-001', 'bac_secretariat');

            $verifyAction = collect($actions)->firstWhere('type', 'verify');
            expect($verifyAction)->not->toBeNull()
                ->and($verifyAction['label'])->toBe('Verification Report')
                ->and($verifyAction['icon'])->toBe('shield-check');
        });

        it('returns corrections action for bac_secretariat only', function () {
            $bacActions = $this->service->getStaticActions('PR-TEST-001', 'bac_secretariat');
            $correctionsAction = collect($bacActions)->firstWhere('type', 'corrections');
            expect($correctionsAction)->not->toBeNull();

            $chairmanActions = $this->service->getStaticActions('PR-TEST-001', 'bac_chairman');
            $noCorrectionsAction = collect($chairmanActions)->firstWhere('type', 'corrections');
            expect($noCorrectionsAction)->toBeNull();
        });

        it('uses correct href based on role', function () {
            $bacSecretariatActions = $this->service->getStaticActions('PR-TEST-001', 'bac_secretariat');
            $viewAction = collect($bacSecretariatActions)->firstWhere('type', 'view');
            expect($viewAction['href'])->toContain('/bac-secretariat/procurements-list/PR-TEST-001');

            $chairmanActions = $this->service->getStaticActions('PR-TEST-001', 'bac_chairman');
            $viewAction = collect($chairmanActions)->firstWhere('type', 'view');
            expect($viewAction['href'])->toContain('/bac-chairman/procurements-list/PR-TEST-001');

            $hopeActions = $this->service->getStaticActions('PR-TEST-001', 'hope');
            $viewAction = collect($hopeActions)->firstWhere('type', 'view');
            expect($viewAction['href'])->toContain('/hope/procurements-list/PR-TEST-001');
        });
    });

    describe('action registry coverage', function () {
        it('covers all major workflow stages', function () {
            $this->mockRepository->shouldReceive('findByProcurement')
                ->andReturn(null);

            $stages = [
                [StageEnums::PROCUREMENT_INITIATION, StatusEnums::PROCUREMENT_INITIATED],
                [StageEnums::PRE_PROCUREMENT_CONFERENCE, StatusEnums::PRE_PROCUREMENT_CONFERENCE_HELD],
                [StageEnums::BIDDING_DOCUMENTS, StatusEnums::PRE_PROCUREMENT_CONFERENCE_COMPLETED],
                [StageEnums::PRE_BID_CONFERENCE, StatusEnums::BIDDING_DOCUMENTS_PUBLISHED],
                [StageEnums::BID_OPENING, StatusEnums::SUPPLEMENTAL_BULLETINS_COMPLETED],
                [StageEnums::BID_EVALUATION, StatusEnums::BIDS_OPENED],
                [StageEnums::POST_QUALIFICATION, StatusEnums::BIDS_EVALUATED],
                [StageEnums::BAC_RESOLUTION, StatusEnums::POST_QUALIFICATION_VERIFIED],
                [StageEnums::NOTICE_OF_AWARD, StatusEnums::RESOLUTION_RECORDED],
                [StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO, StatusEnums::AWARDED],
                [StageEnums::NOTICE_TO_PROCEED, StatusEnums::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED],
                [StageEnums::MONITORING, StatusEnums::NTP_RECORDED],
                [StageEnums::COMPLETION, StatusEnums::MONITORING_COMPLETED],
            ];

            foreach ($stages as [$stage, $status]) {
                $actions = $this->service->getAvailableActions(
                    'PR-TEST-001',
                    $stage->value,
                    $status->value,
                    'bac_secretariat'
                );

                expect($actions)->not->toBeEmpty(
                    "Expected action for stage {$stage->value} with status {$status->value}"
                );
            }
        });

        it('supports SVP workflow stages', function () {
            $this->mockRepository->shouldReceive('findByProcurement')
                ->andReturn(null);

            $actions = $this->service->getAvailableActions(
                'PR-TEST-001',
                StageEnums::REQUEST_FOR_QUOTATION->value,
                StatusEnums::PROCUREMENT_SUBMITTED->value,
                'bac_secretariat'
            );

            expect($actions)->not->toBeEmpty()
                ->and($actions[0]['label'])->toContain('Request for Quotation');
        });

        it('supports abstract of quotations stage', function () {
            $this->mockRepository->shouldReceive('findByProcurement')
                ->andReturn(null);

            $actions = $this->service->getAvailableActions(
                'PR-TEST-001',
                StageEnums::ABSTRACT_OF_QUOTATIONS->value,
                StatusEnums::QUOTATIONS_RECEIVED->value,
                'bac_secretariat'
            );

            expect($actions)->not->toBeEmpty()
                ->and($actions[0]['label'])->toContain('Abstract of Quotations');
        });
    });
});
