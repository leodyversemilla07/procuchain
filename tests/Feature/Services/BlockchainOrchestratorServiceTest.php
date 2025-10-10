<?php

use App\Jobs\HandleStageTransitionJob;
use App\Jobs\PublishProcurementDocumentsJob;
use App\Services\BlockchainOrchestratorService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->service = new BlockchainOrchestratorService;
    $this->procurementId = 'PROC-001';
    $this->procurementTitle = 'Test Procurement';
    $this->userAddress = '1ABC123XYZ';
});

describe('BlockchainOrchestratorService', function () {
    describe('publishDocuments', function () {
        it('dispatches PublishProcurementDocumentsJob with correct parameters', function () {
            Queue::fake();

            $state = 'bidding';
            $status = 'published';
            $metadataArray = [
                ['document_type' => 'bid', 'hash' => 'hash123', 'file_key' => 'file123', 'file_size' => 1024],
            ];

            $this->service->publishDocuments(
                $this->procurementId,
                $this->procurementTitle,
                $state,
                $status,
                $metadataArray,
                $this->userAddress
            );

            Queue::assertPushed(PublishProcurementDocumentsJob::class);
        });

        it('dispatches job with empty metadata array', function () {
            Queue::fake();

            $this->service->publishDocuments(
                $this->procurementId,
                $this->procurementTitle,
                'bidding',
                'published',
                [],
                $this->userAddress
            );

            Queue::assertPushed(PublishProcurementDocumentsJob::class);
        });

        it('dispatches job with multiple documents', function () {
            Queue::fake();

            $metadataArray = [
                ['document_type' => 'bid', 'hash' => 'hash1', 'file_key' => 'file1', 'file_size' => 1024],
                ['document_type' => 'spec', 'hash' => 'hash2', 'file_key' => 'file2', 'file_size' => 2048],
                ['document_type' => 'contract', 'hash' => 'hash3', 'file_key' => 'file3', 'file_size' => 3072],
            ];

            $this->service->publishDocuments(
                $this->procurementId,
                $this->procurementTitle,
                'bidding',
                'published',
                $metadataArray,
                $this->userAddress
            );

            Queue::assertPushed(PublishProcurementDocumentsJob::class);
        });

        it('returns void', function () {
            Queue::fake();

            $result = $this->service->publishDocuments(
                $this->procurementId,
                $this->procurementTitle,
                'bidding',
                'published',
                [],
                $this->userAddress
            );

            expect($result)->toBeNull();
        });
    });

    describe('handleStageTransition', function () {
        it('dispatches HandleStageTransitionJob with correct parameters', function () {
            Queue::fake();

            $fromStatus = 'draft';
            $toStatus = 'published';
            $fromStage = 'preparation';
            $toStage = 'bidding';
            $details = 'Transition details';

            $this->service->handleStageTransition(
                $this->procurementId,
                $this->procurementTitle,
                $fromStatus,
                $toStatus,
                $fromStage,
                $toStage,
                $this->userAddress,
                $details
            );

            Queue::assertPushed(HandleStageTransitionJob::class);
        });

        it('dispatches job for different stage transitions', function () {
            Queue::fake();

            $this->service->handleStageTransition(
                $this->procurementId,
                $this->procurementTitle,
                'pending',
                'approved',
                'evaluation',
                'award',
                $this->userAddress,
                'Moving to award stage'
            );

            Queue::assertPushed(HandleStageTransitionJob::class);
        });

        it('returns void', function () {
            Queue::fake();

            $result = $this->service->handleStageTransition(
                $this->procurementId,
                $this->procurementTitle,
                'draft',
                'published',
                'preparation',
                'bidding',
                $this->userAddress,
                'details'
            );

            expect($result)->toBeNull();
        });
    });

    describe('integration', function () {
        it('can dispatch both jobs sequentially', function () {
            Queue::fake();

            // First publish documents
            $this->service->publishDocuments(
                $this->procurementId,
                $this->procurementTitle,
                'bidding',
                'published',
                [['document_type' => 'bid', 'hash' => 'hash123', 'file_key' => 'file123', 'file_size' => 1024]],
                $this->userAddress
            );

            // Then handle stage transition
            $this->service->handleStageTransition(
                $this->procurementId,
                $this->procurementTitle,
                'draft',
                'published',
                'preparation',
                'bidding',
                $this->userAddress,
                'Moving to bidding'
            );

            Queue::assertPushed(PublishProcurementDocumentsJob::class, 1);
            Queue::assertPushed(HandleStageTransitionJob::class, 1);
        });
    });
});

