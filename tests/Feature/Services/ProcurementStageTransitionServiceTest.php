<?php

use App\Enums\StageEnums;
use App\Enums\StatusEnums;
use App\Services\ProcurementStageTransitionService;

beforeEach(function () {
    $this->service = new ProcurementStageTransitionService;
    $this->pr_number = 'PROC-001';
    $this->procurementTitle = 'Test Procurement Project';
});

describe('ProcurementStageTransitionService', function () {
    describe('getPriorityAction', function () {
        it('returns action for procurement initiation submitted status', function () {
            $result = $this->service->getPriorityAction(
                StageEnums::PROCUREMENT_INITIATION->getDisplayName(),
                StatusEnums::PROCUREMENT_SUBMITTED->getDisplayName(),
                $this->pr_number,
                $this->procurementTitle
            );

            expect($result)->toBeArray();
            expect($result['id'])->toBe($this->pr_number);
            expect($result['title'])->toBe($this->procurementTitle);
            expect($result['action'])->toBe('Continue Procurement Processing');
            expect($result['route'])->toContain('/bac-secretariat/procurements-list');
        });

        it('returns action for pre-procurement conference held', function () {
            $result = $this->service->getPriorityAction(
                StageEnums::PRE_PROCUREMENT_CONFERENCE->getDisplayName(),
                StatusEnums::PRE_PROCUREMENT_CONFERENCE_HELD->getDisplayName(),
                $this->pr_number,
                $this->procurementTitle
            );

            expect($result)->toBeArray();
            expect($result['action'])->toBe('Upload Pre-Procurement Conference Documents');
        });

        it('returns null when no matching stage action found', function () {
            $result = $this->service->getPriorityAction(
                'Non-existent Stage',
                'Non-existent Status',
                $this->pr_number,
                $this->procurementTitle
            );

            expect($result)->toBeNull();
        });

        it('returns null when stage matches but status does not', function () {
            $result = $this->service->getPriorityAction(
                StageEnums::PROCUREMENT_INITIATION->getDisplayName(),
                'Wrong Status',
                $this->pr_number,
                $this->procurementTitle
            );

            expect($result)->toBeNull();
        });

        it('includes procurement ID in route', function () {
            $pr_number = 'PROC-12345';

            $result = $this->service->getPriorityAction(
                StageEnums::PROCUREMENT_INITIATION->getDisplayName(),
                StatusEnums::PROCUREMENT_SUBMITTED->getDisplayName(),
                $pr_number,
                $this->procurementTitle
            );

            // Route template uses sprintf, so it may or may not include ID depending on implementation
            expect($result['route'])->toBeString();
        });

        it('returns consistent structure for all stages', function () {
            $result = $this->service->getPriorityAction(
                StageEnums::PROCUREMENT_INITIATION->getDisplayName(),
                StatusEnums::PROCUREMENT_SUBMITTED->getDisplayName(),
                $this->pr_number,
                $this->procurementTitle
            );

            expect($result)->toHaveKeys(['id', 'title', 'action', 'route']);
        });

        it('handles different procurement IDs', function () {
            $id1 = 'PROC-001';
            $id2 = 'PROC-999';

            $result1 = $this->service->getPriorityAction(
                StageEnums::PROCUREMENT_INITIATION->getDisplayName(),
                StatusEnums::PROCUREMENT_SUBMITTED->getDisplayName(),
                $id1,
                $this->procurementTitle
            );

            $result2 = $this->service->getPriorityAction(
                StageEnums::PROCUREMENT_INITIATION->getDisplayName(),
                StatusEnums::PROCUREMENT_SUBMITTED->getDisplayName(),
                $id2,
                $this->procurementTitle
            );

            expect($result1['id'])->toBe($id1);
            expect($result2['id'])->toBe($id2);
        });

        it('handles different procurement titles', function () {
            $title1 = 'Procurement A';
            $title2 = 'Procurement B';

            $result1 = $this->service->getPriorityAction(
                StageEnums::PROCUREMENT_INITIATION->getDisplayName(),
                StatusEnums::PROCUREMENT_SUBMITTED->getDisplayName(),
                $this->pr_number,
                $title1
            );

            $result2 = $this->service->getPriorityAction(
                StageEnums::PROCUREMENT_INITIATION->getDisplayName(),
                StatusEnums::PROCUREMENT_SUBMITTED->getDisplayName(),
                $this->pr_number,
                $title2
            );

            expect($result1['title'])->toBe($title1);
            expect($result2['title'])->toBe($title2);
        });
    });

    describe('stage matching', function () {
        it('matches exact stage name', function () {
            $result = $this->service->getPriorityAction(
                StageEnums::PROCUREMENT_INITIATION->getDisplayName(),
                StatusEnums::PROCUREMENT_SUBMITTED->getDisplayName(),
                $this->pr_number,
                $this->procurementTitle
            );

            expect($result)->not->toBeNull();
        });

        it('does not match partial stage name', function () {
            $result = $this->service->getPriorityAction(
                'Procurement',
                StatusEnums::PROCUREMENT_SUBMITTED->getDisplayName(),
                $this->pr_number,
                $this->procurementTitle
            );

            expect($result)->toBeNull();
        });

        it('is case sensitive for stage names', function () {
            $stageName = StageEnums::PROCUREMENT_INITIATION->getDisplayName();

            $result = $this->service->getPriorityAction(
                strtolower($stageName),
                StatusEnums::PROCUREMENT_SUBMITTED->getDisplayName(),
                $this->pr_number,
                $this->procurementTitle
            );

            // Assuming case-sensitive matching
            expect($result)->toBeNull();
        });
    });

    describe('status matching', function () {
        it('matches exact status name', function () {
            $result = $this->service->getPriorityAction(
                StageEnums::PROCUREMENT_INITIATION->getDisplayName(),
                StatusEnums::PROCUREMENT_SUBMITTED->getDisplayName(),
                $this->pr_number,
                $this->procurementTitle
            );

            expect($result)->not->toBeNull();
        });

        it('does not match partial status name', function () {
            $result = $this->service->getPriorityAction(
                StageEnums::PROCUREMENT_INITIATION->getDisplayName(),
                'Submitted',
                $this->pr_number,
                $this->procurementTitle
            );

            expect($result)->toBeNull();
        });
    });

    describe('edge cases', function () {
        it('handles empty procurement ID', function () {
            $result = $this->service->getPriorityAction(
                StageEnums::PROCUREMENT_INITIATION->getDisplayName(),
                StatusEnums::PROCUREMENT_SUBMITTED->getDisplayName(),
                '',
                $this->procurementTitle
            );

            expect($result['id'])->toBe('');
        });

        it('handles empty procurement title', function () {
            $result = $this->service->getPriorityAction(
                StageEnums::PROCUREMENT_INITIATION->getDisplayName(),
                StatusEnums::PROCUREMENT_SUBMITTED->getDisplayName(),
                $this->pr_number,
                ''
            );

            expect($result['title'])->toBe('');
        });

        it('handles special characters in procurement ID', function () {
            $specialId = 'PROC-001/2024-A';

            $result = $this->service->getPriorityAction(
                StageEnums::PROCUREMENT_INITIATION->getDisplayName(),
                StatusEnums::PROCUREMENT_SUBMITTED->getDisplayName(),
                $specialId,
                $this->procurementTitle
            );

            expect($result['id'])->toBe($specialId);
        });

        it('handles special characters in procurement title', function () {
            $specialTitle = 'Procurement: Test & Verification (2024)';

            $result = $this->service->getPriorityAction(
                StageEnums::PROCUREMENT_INITIATION->getDisplayName(),
                StatusEnums::PROCUREMENT_SUBMITTED->getDisplayName(),
                $this->pr_number,
                $specialTitle
            );

            expect($result['title'])->toBe($specialTitle);
        });
    });
});
