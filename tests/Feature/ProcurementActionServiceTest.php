<?php

declare(strict_types=1);

use App\Enums\ProcurementStatus;
use App\Enums\StageEnums;
use App\Services\Procurement\ProcurementActionService;

beforeEach(function () {
    $this->service = new ProcurementActionService;
});

describe('ProcurementActionService', function () {
    describe('getAvailableActions', function () {
        it('returns empty array for invalid stage', function () {
            $actions = $this->service->getAvailableActions(
                'PR-2025-995-0001',
                'invalid_stage',
                'procurement_initiated',
                'bac_secretariat'
            );

            expect($actions)->toBeArray()
                ->and($actions)->toBeEmpty();
        });

        it('returns empty array for invalid status', function () {
            $actions = $this->service->getAvailableActions(
                'PR-2025-995-0001',
                'procurement_initiation',
                'invalid_status',
                'bac_secretariat'
            );

            expect($actions)->toBeArray()
                ->and($actions)->toBeEmpty();
        });

        it('returns upload action for procurement initiation stage', function () {
            $actions = $this->service->getAvailableActions(
                'PR-2025-995-0001',
                StageEnums::PROCUREMENT_INITIATION->value,
                ProcurementStatus::PROCUREMENT_INITIATED->value,
                'bac_secretariat'
            );

            expect($actions)->toBeArray()
                ->and($actions)->toHaveCount(1)
                ->and($actions[0]['type'])->toBe('upload')
                ->and($actions[0]['label'])->toBe('Upload Procurement Initiation Documents');
        });

        it('returns dialog action for pre-procurement decision', function () {
            // After procurement initiation is complete, the stage transitions to PRE_PROCUREMENT_CONFERENCE
            // with status PROCUREMENT_SUBMITTED for Competitive Bidding mode
            $actions = $this->service->getAvailableActions(
                'PR-2025-995-0001',
                StageEnums::PRE_PROCUREMENT_CONFERENCE->value,
                ProcurementStatus::PROCUREMENT_SUBMITTED->value,
                'bac_secretariat'
            );

            expect($actions)->toBeArray()
                ->and($actions)->toHaveCount(1)
                ->and($actions[0]['type'])->toBe('dialog')
                ->and($actions[0]['action'])->toBe('pre-procurement');
        });

        it('returns upload action for bid evaluation', function () {
            $actions = $this->service->getAvailableActions(
                'PR-2025-995-0001',
                StageEnums::BID_EVALUATION->value,
                ProcurementStatus::BIDS_OPENED->value,
                'bac_secretariat'
            );

            expect($actions)->toBeArray()
                ->and($actions)->toHaveCount(1)
                ->and($actions[0]['type'])->toBe('upload')
                ->and($actions[0]['icon'])->toBe('chart');
        });

        it('returns no workflow actions for non-bac-secretariat role', function () {
            $actions = $this->service->getAvailableActions(
                'PR-2025-995-0001',
                StageEnums::PROCUREMENT_INITIATION->value,
                ProcurementStatus::PROCUREMENT_INITIATED->value,
                'bac_chairman'
            );

            expect($actions)->toBeArray()
                ->and($actions)->toBeEmpty();
        });
    });

    describe('getStaticActions', function () {
        it('returns view details action for all roles', function () {
            $actions = $this->service->getStaticActions('PR-2025-995-0001', 'bac_secretariat');

            $viewAction = collect($actions)->firstWhere('type', 'view');
            expect($viewAction)->not->toBeNull()
                ->and($viewAction['label'])->toBe('View Details')
                ->and($viewAction['icon'])->toBe('eye');
        });

        it('returns verification report action', function () {
            $actions = $this->service->getStaticActions('PR-2025-995-0001', 'bac_secretariat');

            $verifyAction = collect($actions)->firstWhere('type', 'verify');
            expect($verifyAction)->not->toBeNull()
                ->and($verifyAction['label'])->toBe('Verification Report')
                ->and($verifyAction['icon'])->toBe('shield-check');
        });

        it('returns corrections action for bac_secretariat only', function () {
            $bacActions = $this->service->getStaticActions('PR-2025-995-0001', 'bac_secretariat');
            $correctionsAction = collect($bacActions)->firstWhere('type', 'corrections');
            expect($correctionsAction)->not->toBeNull();

            $chairmanActions = $this->service->getStaticActions('PR-2025-995-0001', 'bac_chairman');
            $noCorrectionsAction = collect($chairmanActions)->firstWhere('type', 'corrections');
            expect($noCorrectionsAction)->toBeNull();
        });

        it('uses correct href based on role', function () {
            $bacSecretariatActions = $this->service->getStaticActions('PR-2025-995-0001', 'bac_secretariat');
            $viewAction = collect($bacSecretariatActions)->firstWhere('type', 'view');
            expect($viewAction['href'])->toContain('/bac-secretariat/procurements-list/PR-2025-995-0001');

            $chairmanActions = $this->service->getStaticActions('PR-2025-995-0001', 'bac_chairman');
            $viewAction = collect($chairmanActions)->firstWhere('type', 'view');
            expect($viewAction['href'])->toContain('/bac-chairman/procurements-list/PR-2025-995-0001');

            $hopeActions = $this->service->getStaticActions('PR-2025-995-0001', 'hope');
            $viewAction = collect($hopeActions)->firstWhere('type', 'view');
            expect($viewAction['href'])->toContain('/hope/procurements-list/PR-2025-995-0001');
        });
    });

    describe('action registry coverage', function () {
        it('covers all major workflow stages', function () {
            $stages = [
                [StageEnums::PROCUREMENT_INITIATION, ProcurementStatus::PROCUREMENT_INITIATED],
                [StageEnums::PRE_PROCUREMENT_CONFERENCE, ProcurementStatus::PRE_PROCUREMENT_CONFERENCE_HELD],
                [StageEnums::BIDDING_DOCUMENTS, ProcurementStatus::PRE_PROCUREMENT_CONFERENCE_COMPLETED],
                [StageEnums::PRE_BID_CONFERENCE, ProcurementStatus::BIDDING_DOCUMENTS_PUBLISHED],
                [StageEnums::BID_OPENING, ProcurementStatus::SUPPLEMENTAL_BULLETINS_COMPLETED],
                [StageEnums::BID_EVALUATION, ProcurementStatus::BIDS_OPENED],
                [StageEnums::POST_QUALIFICATION, ProcurementStatus::BIDS_EVALUATED],
                [StageEnums::BAC_RESOLUTION, ProcurementStatus::POST_QUALIFICATION_VERIFIED],
                [StageEnums::NOTICE_OF_AWARD, ProcurementStatus::RESOLUTION_RECORDED],
                [StageEnums::PERFORMANCE_BOND_CONTRACT_AND_PO, ProcurementStatus::AWARDED],
                [StageEnums::NOTICE_TO_PROCEED, ProcurementStatus::PERFORMANCE_BOND_CONTRACT_AND_PO_RECORDED],
                [StageEnums::MONITORING, ProcurementStatus::NTP_RECORDED],
                [StageEnums::COMPLETION, ProcurementStatus::MONITORING_COMPLETED],
            ];

            foreach ($stages as [$stage, $status]) {
                $actions = $this->service->getAvailableActions(
                    'PR-2025-995-0001',
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
            $actions = $this->service->getAvailableActions(
                'PR-2025-995-0001',
                StageEnums::REQUEST_FOR_QUOTATION->value,
                ProcurementStatus::PROCUREMENT_SUBMITTED->value,
                'bac_secretariat'
            );

            expect($actions)->not->toBeEmpty()
                ->and($actions[0]['label'])->toContain('Request for Quotation');
        });

        it('supports abstract of quotations stage', function () {
            $actions = $this->service->getAvailableActions(
                'PR-2025-995-0001',
                StageEnums::ABSTRACT_OF_QUOTATIONS->value,
                ProcurementStatus::QUOTATIONS_RECEIVED->value,
                'bac_secretariat'
            );

            expect($actions)->not->toBeEmpty()
                ->and($actions[0]['label'])->toContain('Abstract of Quotations');
        });
    });
});
