<?php

use App\Services\Procurement\ProcurementFormatterService;

beforeEach(function () {
    $this->formatter = new ProcurementFormatterService;
});

describe('ProcurementFormatterService', function () {
    describe('Stage Formatting', function () {
        it('formats stage name from enum value', function () {
            expect($this->formatter->formatStageName('procurement_initiation'))
                ->toBe('Procurement Initiation');

            expect($this->formatter->formatStageName('bid_opening'))
                ->toBe('Bid Opening');

            expect($this->formatter->formatStageName('notice_of_award'))
                ->toBe('Notice of Award');
        });

        it('returns default stage name for empty input', function () {
            expect($this->formatter->formatStageName(''))
                ->toBe('Procurement Initiation');
        });

        it('formats unknown stage names with title case', function () {
            expect($this->formatter->formatStageName('custom_stage_name'))
                ->toBe('Custom Stage Name');
        });

        it('gets stage order index correctly', function () {
            expect($this->formatter->getStageOrderIndex('procurement_initiation'))
                ->toBe(0);

            expect($this->formatter->getStageOrderIndex('completed'))
                ->toBe(14);
        });

        it('returns 999 for unknown stages', function () {
            expect($this->formatter->getStageOrderIndex('unknown_stage'))
                ->toBe(999);
        });

        it('calculates progress percentage correctly', function () {
            // First stage (1/15) ≈ 6.67%
            $progress = $this->formatter->calculateProgress('procurement_initiation');
            expect($progress)->toBeGreaterThan(6);
            expect($progress)->toBeLessThan(7);

            // Last stage (15/15) = 100%
            expect($this->formatter->calculateProgress('completed'))
                ->toBe(100.0);
        });

        it('returns stage description', function () {
            expect($this->formatter->getStageDescription('procurement_initiation'))
                ->toContain('Initial stage');

            expect($this->formatter->getStageDescription('bid_opening'))
                ->toContain('Public opening');
        });

        it('returns stage phase', function () {
            expect($this->formatter->getStagePhase('procurement_initiation'))
                ->toBe('pre_procurement');

            expect($this->formatter->getStagePhase('bid_opening'))
                ->toBe('procurement');

            expect($this->formatter->getStagePhase('notice_of_award'))
                ->toBe('post_procurement');
        });
    });

    describe('Status Formatting', function () {
        it('formats status from enum value', function () {
            expect($this->formatter->formatStatus('procurement_submitted'))
                ->toBe('Procurement Submitted');

            expect($this->formatter->formatStatus('awarded'))
                ->toBe('Awarded');
        });

        it('returns Unknown Status for empty input', function () {
            expect($this->formatter->formatStatus(''))
                ->toBe('Unknown Status');
        });

        it('gets status info with variant', function () {
            $info = $this->formatter->getStatusInfo('procurement_submitted');

            expect($info)->toHaveKeys(['variant', 'label', 'description']);
            expect($info['variant'])->toBe('default');
            expect($info['label'])->toBe('Procurement Submitted');
        });
    });

    describe('Date/Time Formatting', function () {
        it('formats datetime correctly', function () {
            $result = $this->formatter->formatDateTime('2025-01-15 14:30:00');

            expect($result)->toContain('Jan');
            expect($result)->toContain('15');
            expect($result)->toContain('2025');
        });

        it('formats date only', function () {
            $result = $this->formatter->formatDateOnly('2025-06-20 10:00:00');

            expect($result)->toBe('Jun 20, 2025');
        });

        it('formats time only', function () {
            $result = $this->formatter->formatTimeOnly('2025-01-15 14:30:00');

            expect($result)->toBe('2:30 PM');
        });

        it('handles invalid dates gracefully', function () {
            expect($this->formatter->formatDateTime(null))->toBe('Invalid Date');
            expect($this->formatter->formatDateTime(''))->toBe('Invalid Date');
            expect($this->formatter->formatTimeOnly(null))->toBe('Invalid Time');
        });
    });

    describe('Document Formatting', function () {
        it('formats document type', function () {
            expect($this->formatter->formatDocumentType('purchase_request'))
                ->toBe('Purchase Request (PR)');
        });

        it('formats file size', function () {
            expect($this->formatter->formatFileSize(0))->toBe('0 B');
            expect($this->formatter->formatFileSize(1024))->toBe('1 KB');
            expect($this->formatter->formatFileSize(1048576))->toBe('1.0 MB');
            expect($this->formatter->formatFileSize(null))->toBe('N/A');
        });

        it('shortens hash correctly', function () {
            $hash = 'abcdefghijklmnopqrstuvwxyz1234567890';

            expect($this->formatter->shortenHash($hash))->toBe('abcde...67890');
            expect($this->formatter->shortenHash($hash, 3, 3))->toBe('abc...890');
            expect($this->formatter->shortenHash(null))->toBe('N/A');
            expect($this->formatter->shortenHash(''))->toBe('N/A');
        });
    });

    describe('Event Formatting', function () {
        it('formats event type', function () {
            expect($this->formatter->formatEventType('document_upload'))
                ->toBe('Document Uploaded');

            expect($this->formatter->formatEventType('stage_transition'))
                ->toBe('Stage Transition');

            expect($this->formatter->formatEventType('custom_event'))
                ->toBe('Custom Event');
        });

        it('formats event category', function () {
            expect($this->formatter->formatEventCategory('document'))
                ->toBe('Document');

            expect($this->formatter->formatEventCategory('workflow'))
                ->toBe('Workflow');

            expect($this->formatter->formatEventCategory(''))->toBe('');
        });
    });

    describe('Currency Formatting', function () {
        it('formats currency with peso sign', function () {
            expect($this->formatter->formatCurrency(1000))
                ->toBe('₱ 1,000.00');

            expect($this->formatter->formatCurrency(1234567.89))
                ->toBe('₱ 1,234,567.89');

            expect($this->formatter->formatCurrency('5000'))
                ->toBe('₱ 5,000.00');
        });

        it('handles zero and null values', function () {
            expect($this->formatter->formatCurrency(0))->toBe('₱ 0.00');
            expect($this->formatter->formatCurrency(null))->toBe('₱ 0.00');
            expect($this->formatter->formatCurrency(''))->toBe('₱ 0.00');
        });
    });

    describe('Metadata Formatting', function () {
        it('formats stage metadata with dates', function () {
            $metadata = [
                'meeting_date' => '2025-01-15',
                'submission_date' => '2025-01-20',
            ];

            $result = $this->formatter->formatStageMetadata($metadata);

            expect($result['meeting_date_formatted'])->toBe('Jan 15, 2025');
            expect($result['submission_date_formatted'])->toBe('Jan 20, 2025');
        });

        it('formats stage metadata with currency', function () {
            $metadata = [
                'appropriation' => 100000,
                'bond_amount' => 5000,
            ];

            $result = $this->formatter->formatStageMetadata($metadata);

            expect($result['appropriation_formatted'])->toBe('₱ 100,000.00');
            expect($result['bond_amount_formatted'])->toBe('₱ 5,000.00');
        });

        it('formats validity period dates', function () {
            $metadata = [
                'validity_period' => [
                    'start_date' => '2025-01-01',
                    'end_date' => '2025-12-31',
                ],
            ];

            $result = $this->formatter->formatStageMetadata($metadata);

            expect($result['validity_period']['start_date_formatted'])->toBe('Jan 1, 2025');
            expect($result['validity_period']['end_date_formatted'])->toBe('Dec 31, 2025');
        });
    });

    describe('Correction Formatting', function () {
        it('formats correction types', function () {
            expect($this->formatter->formatCorrectionType('replace'))
                ->toBe('Document Replacement');

            expect($this->formatter->formatCorrectionType('invalidate'))
                ->toBe('Document Invalidation');

            expect($this->formatter->formatCorrectionType('metadata'))
                ->toBe('Metadata Correction');

            expect($this->formatter->formatCorrectionType('custom_type'))
                ->toBe('Custom Type');
        });
    });
});
