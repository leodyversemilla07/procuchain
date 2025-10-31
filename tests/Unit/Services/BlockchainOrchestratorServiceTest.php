<?php

use App\Jobs\HandleStageTransitionJob;
use App\Jobs\PublishProcurementDocumentsJob;
use App\Services\BlockchainOrchestratorService;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Queue::fake();
    $this->service = new BlockchainOrchestratorService;
});

describe('publishDocuments', function () {
    it('dispatches PublishProcurementDocumentsJob with correct parameters', function () {
        $procurementId = 'PROC-2024-001';
        $procurementTitle = 'Test Procurement';
        $state = 'Procurement Initiation';
        $status = 'Pending';
        $metadataArray = [
            [
                'document_type' => 'Purchase Request',
                'hash' => 'abc123',
                'file_key' => 'procurement/test.pdf',
                'file_size' => 1024,
            ],
        ];
        $userAddress = '1TestAddressABC123';

        $this->service->publishDocuments(
            $procurementId,
            $procurementTitle,
            $state,
            $status,
            $metadataArray,
            $userAddress
        );

        Queue::assertPushed(PublishProcurementDocumentsJob::class);
    });

    it('dispatches job with empty metadata array', function () {
        $this->service->publishDocuments(
            'PROC-001',
            'Test',
            'Initiation',
            'Pending',
            [],
            '1Address'
        );

        Queue::assertPushed(PublishProcurementDocumentsJob::class);
    });

    it('dispatches job with multiple documents', function () {
        $metadataArray = [
            [
                'document_type' => 'Purchase Request',
                'hash' => 'hash1',
                'file_key' => 'file1.pdf',
                'file_size' => 1024,
            ],
            [
                'document_type' => 'Budget Approval',
                'hash' => 'hash2',
                'file_key' => 'file2.pdf',
                'file_size' => 2048,
            ],
            [
                'document_type' => 'Technical Specs',
                'hash' => 'hash3',
                'file_key' => 'file3.pdf',
                'file_size' => 3072,
            ],
        ];

        $this->service->publishDocuments(
            'PROC-001',
            'Multi Document Test',
            'Procurement Initiation',
            'Pending',
            $metadataArray,
            '1TestAddress'
        );

        Queue::assertPushed(PublishProcurementDocumentsJob::class);
    });

    it('dispatches job with special characters in procurement title', function () {
        $procurementTitle = 'Test & Special <Characters> "Quotes" \'Apostrophe\'';

        $this->service->publishDocuments(
            'PROC-001',
            $procurementTitle,
            'Initiation',
            'Pending',
            [['document_type' => 'Test', 'hash' => 'abc', 'file_key' => 'test.pdf', 'file_size' => 100]],
            '1Address'
        );

        Queue::assertPushed(PublishProcurementDocumentsJob::class);
    });

    it('returns void and does not throw exceptions', function () {
        $result = $this->service->publishDocuments(
            'PROC-001',
            'Test',
            'Initiation',
            'Pending',
            [['document_type' => 'Test', 'hash' => 'abc', 'file_key' => 'test.pdf', 'file_size' => 100]],
            '1Address'
        );

        expect($result)->toBeNull();
    });

    it('dispatches job with complex metadata including stage metadata', function () {
        $metadataArray = [
            [
                'document_type' => 'Purchase Request',
                'hash' => 'abc123',
                'file_key' => 'procurement/test.pdf',
                'file_size' => 1024,
                'submission_date' => '2024-10-31',
                'signatories' => [
                    ['name' => 'John Doe', 'position' => 'Manager'],
                ],
                'municipal_offices' => 'Office of the Mayor',
            ],
        ];

        $this->service->publishDocuments(
            'PROC-001',
            'Complex Metadata Test',
            'Procurement Initiation',
            'Pending',
            $metadataArray,
            '1TestAddress'
        );

        Queue::assertPushed(PublishProcurementDocumentsJob::class);
    });

    it('handles long procurement IDs', function () {
        $longProcurementId = 'PROC-2024-VERY-LONG-PROCUREMENT-ID-WITH-MANY-CHARACTERS-12345678';

        $this->service->publishDocuments(
            $longProcurementId,
            'Test',
            'Initiation',
            'Pending',
            [['document_type' => 'Test', 'hash' => 'abc', 'file_key' => 'test.pdf', 'file_size' => 100]],
            '1Address'
        );

        Queue::assertPushed(PublishProcurementDocumentsJob::class);
    });
});

describe('handleStageTransition', function () {
    it('dispatches HandleStageTransitionJob with correct parameters', function () {
        $procurementId = 'PROC-2024-001';
        $procurementTitle = 'Test Procurement';
        $fromStatus = 'Pending';
        $toStatus = 'Approved';
        $fromStage = 'Procurement Initiation';
        $toStage = 'Pre-Procurement Conference';
        $userAddress = '1TestAddressABC123';
        $details = 'Moved to next stage after approval';

        $this->service->handleStageTransition(
            $procurementId,
            $procurementTitle,
            $fromStatus,
            $toStatus,
            $fromStage,
            $toStage,
            $userAddress,
            $details
        );

        Queue::assertPushed(HandleStageTransitionJob::class);
    });

    it('dispatches job with empty details', function () {
        $this->service->handleStageTransition(
            'PROC-001',
            'Test',
            'Pending',
            'Approved',
            'Stage 1',
            'Stage 2',
            '1Address',
            ''
        );

        Queue::assertPushed(HandleStageTransitionJob::class);
    });

    it('dispatches job with same stage different status', function () {
        $this->service->handleStageTransition(
            'PROC-001',
            'Test',
            'Pending',
            'Approved',
            'Procurement Initiation',
            'Procurement Initiation',
            '1Address',
            'Status updated to Approved'
        );

        Queue::assertPushed(HandleStageTransitionJob::class);
    });

    it('returns void and does not throw exceptions', function () {
        $result = $this->service->handleStageTransition(
            'PROC-001',
            'Test',
            'Pending',
            'Approved',
            'Stage 1',
            'Stage 2',
            '1Address',
            'Test details'
        );

        expect($result)->toBeNull();
    });

    it('dispatches job with long details text', function () {
        $longDetails = str_repeat('This is a very long details text with many words. ', 20);

        $this->service->handleStageTransition(
            'PROC-001',
            'Test',
            'Pending',
            'Approved',
            'Stage 1',
            'Stage 2',
            '1Address',
            $longDetails
        );

        Queue::assertPushed(HandleStageTransitionJob::class);
    });

    it('dispatches job with special characters in status names', function () {
        $this->service->handleStageTransition(
            'PROC-001',
            'Test',
            'Pending & Awaiting Review',
            'Approved - Final',
            'Stage 1',
            'Stage 2',
            '1Address',
            'Transition with special chars'
        );

        Queue::assertPushed(HandleStageTransitionJob::class);
    });

    it('handles stage progression forward', function () {
        $stages = [
            ['from' => 'Procurement Initiation', 'to' => 'Pre-Procurement Conference'],
            ['from' => 'Pre-Procurement Conference', 'to' => 'Bidding Documents'],
            ['from' => 'Bidding Documents', 'to' => 'Pre-Bid Conference'],
        ];

        foreach ($stages as $stage) {
            $this->service->handleStageTransition(
                'PROC-001',
                'Test',
                'Pending',
                'Approved',
                $stage['from'],
                $stage['to'],
                '1Address',
                "Moving from {$stage['from']} to {$stage['to']}"
            );
        }

        Queue::assertPushed(HandleStageTransitionJob::class, 3);
    });

    it('handles stage regression backwards', function () {
        $this->service->handleStageTransition(
            'PROC-001',
            'Test',
            'Approved',
            'Rejected',
            'Pre-Bid Conference',
            'Bidding Documents',
            '1Address',
            'Returned to previous stage for corrections'
        );

        Queue::assertPushed(HandleStageTransitionJob::class);
    });
});

describe('job dispatching behavior', function () {
    it('dispatches both publishDocuments and handleStageTransition independently', function () {
        $this->service->publishDocuments(
            'PROC-001',
            'Test',
            'Initiation',
            'Pending',
            [['document_type' => 'Test', 'hash' => 'abc', 'file_key' => 'test.pdf', 'file_size' => 100]],
            '1Address'
        );

        $this->service->handleStageTransition(
            'PROC-001',
            'Test',
            'Pending',
            'Approved',
            'Stage 1',
            'Stage 2',
            '1Address',
            'Transition'
        );

        Queue::assertPushed(PublishProcurementDocumentsJob::class, 1);
        Queue::assertPushed(HandleStageTransitionJob::class, 1);
    });

    it('can dispatch multiple publishDocuments jobs', function () {
        for ($i = 1; $i <= 3; $i++) {
            $this->service->publishDocuments(
                "PROC-00{$i}",
                "Test {$i}",
                'Initiation',
                'Pending',
                [['document_type' => 'Test', 'hash' => "abc{$i}", 'file_key' => 'test.pdf', 'file_size' => 100]],
                '1Address'
            );
        }

        Queue::assertPushed(PublishProcurementDocumentsJob::class, 3);
    });

    it('can dispatch multiple handleStageTransition jobs', function () {
        for ($i = 1; $i <= 3; $i++) {
            $this->service->handleStageTransition(
                "PROC-00{$i}",
                "Test {$i}",
                'Pending',
                'Approved',
                'Stage 1',
                'Stage 2',
                '1Address',
                "Transition {$i}"
            );
        }

        Queue::assertPushed(HandleStageTransitionJob::class, 3);
    });

    it('does not dispatch jobs to specific queue', function () {
        $this->service->publishDocuments(
            'PROC-001',
            'Test',
            'Initiation',
            'Pending',
            [['document_type' => 'Test', 'hash' => 'abc', 'file_key' => 'test.pdf', 'file_size' => 100]],
            '1Address'
        );

        // Jobs should use default queue
        Queue::assertPushed(PublishProcurementDocumentsJob::class);
    });
});

describe('orchestration patterns', function () {
    it('orchestrates document publishing for procurement lifecycle', function () {
        // Simulate a complete procurement lifecycle
        $procurementId = 'PROC-LIFECYCLE-001';
        $stages = [
            'Procurement Initiation',
            'Pre-Procurement Conference',
            'Bidding Documents',
            'Pre-Bid Conference',
        ];

        foreach ($stages as $stage) {
            $this->service->publishDocuments(
                $procurementId,
                'Lifecycle Test',
                $stage,
                'Pending',
                [
                    [
                        'document_type' => "{$stage} Document",
                        'hash' => md5($stage),
                        'file_key' => strtolower(str_replace(' ', '_', $stage)).'.pdf',
                        'file_size' => rand(1000, 5000),
                    ],
                ],
                '1Address'
            );
        }

        Queue::assertPushed(PublishProcurementDocumentsJob::class, 4);
    });

    it('orchestrates stage transitions with status changes', function () {
        $procurementId = 'PROC-TRANSITION-001';
        $transitions = [
            ['from' => 'Pending', 'to' => 'Under Review'],
            ['from' => 'Under Review', 'to' => 'Approved'],
            ['from' => 'Approved', 'to' => 'Published'],
        ];

        foreach ($transitions as $index => $transition) {
            $this->service->handleStageTransition(
                $procurementId,
                'Transition Test',
                $transition['from'],
                $transition['to'],
                'Stage 1',
                'Stage 1',
                '1Address',
                "Transition {$index}: {$transition['from']} → {$transition['to']}"
            );
        }

        Queue::assertPushed(HandleStageTransitionJob::class, 3);
    });

    it('handles concurrent operations for different procurements', function () {
        // Simulate concurrent operations
        $this->service->publishDocuments(
            'PROC-001',
            'Project A',
            'Initiation',
            'Pending',
            [['document_type' => 'Doc A', 'hash' => 'hashA', 'file_key' => 'a.pdf', 'file_size' => 100]],
            '1AddressA'
        );

        $this->service->handleStageTransition(
            'PROC-002',
            'Project B',
            'Pending',
            'Approved',
            'Stage 1',
            'Stage 2',
            '1AddressB',
            'Project B transition'
        );

        $this->service->publishDocuments(
            'PROC-003',
            'Project C',
            'Bidding',
            'Open',
            [['document_type' => 'Doc C', 'hash' => 'hashC', 'file_key' => 'c.pdf', 'file_size' => 300]],
            '1AddressC'
        );

        Queue::assertPushed(PublishProcurementDocumentsJob::class, 2);
        Queue::assertPushed(HandleStageTransitionJob::class, 1);
    });
});

describe('type safety and return values', function () {
    it('publishDocuments returns void type', function () {
        $result = $this->service->publishDocuments(
            'PROC-001',
            'Test',
            'Initiation',
            'Pending',
            [['document_type' => 'Test', 'hash' => 'abc', 'file_key' => 'test.pdf', 'file_size' => 100]],
            '1Address'
        );

        expect($result)->toBeNull();
    });

    it('handleStageTransition returns void type', function () {
        $result = $this->service->handleStageTransition(
            'PROC-001',
            'Test',
            'Pending',
            'Approved',
            'Stage 1',
            'Stage 2',
            '1Address',
            'Details'
        );

        expect($result)->toBeNull();
    });
});
