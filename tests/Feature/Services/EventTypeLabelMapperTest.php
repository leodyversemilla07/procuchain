<?php

use App\Services\EventTypeLabelMapper;

describe('EventTypeLabelMapper', function () {
    beforeEach(function () {
        $this->mapper = new EventTypeLabelMapper;
    });

    describe('getLabel', function () {
        it('returns mapped label for document_upload', function () {
            $label = $this->mapper->getLabel('document_upload');

            expect($label)->toBe('Uploaded Documents');
        });

        it('returns mapped label for phase_transition', function () {
            $label = $this->mapper->getLabel('phase_transition');

            expect($label)->toBe('Phase Transition');
        });

        it('returns mapped label for publication', function () {
            $label = $this->mapper->getLabel('publication');

            expect($label)->toBe('Published Documents');
        });

        it('returns mapped label for procurement completed', function () {
            $label = $this->mapper->getLabel('procurement completed');

            expect($label)->toBe('Completed Procurement');
        });

        it('returns Pre-Procurement Decision for decision with pre-procurement details', function () {
            $label = $this->mapper->getLabel('decision', 'Pre-Procurement phase started');

            expect($label)->toBe('Pre-Procurement Decision');
        });

        it('returns Decision Made for decision without pre-procurement details', function () {
            $label = $this->mapper->getLabel('decision', 'Regular decision details');

            expect($label)->toBe('Decision Made');
        });

        it('returns Decision Made for decision with empty details', function () {
            $label = $this->mapper->getLabel('decision', '');

            expect($label)->toBe('Decision Made');
        });

        it('is case insensitive for event type', function () {
            $label1 = $this->mapper->getLabel('PUBLICATION');
            $label2 = $this->mapper->getLabel('Publication');
            $label3 = $this->mapper->getLabel('publication');

            expect($label1)->toBe('Published Documents');
            expect($label2)->toBe('Published Documents');
            expect($label3)->toBe('Published Documents');
        });

        it('is case insensitive for pre-procurement check', function () {
            $label1 = $this->mapper->getLabel('decision', 'PRE-PROCUREMENT details');
            $label2 = $this->mapper->getLabel('decision', 'Pre-Procurement details');
            $label3 = $this->mapper->getLabel('decision', 'pre-procurement details');

            expect($label1)->toBe('Pre-Procurement Decision');
            expect($label2)->toBe('Pre-Procurement Decision');
            expect($label3)->toBe('Pre-Procurement Decision');
        });

        it('converts unknown event types to title case', function () {
            $label = $this->mapper->getLabel('custom_event_type');

            expect($label)->toBe('Custom Event Type');
        });

        it('replaces underscores with spaces for unknown types', function () {
            $label = $this->mapper->getLabel('my_custom_event');

            expect($label)->toBe('My Custom Event');
        });

        it('handles single word unknown types', function () {
            $label = $this->mapper->getLabel('error');

            expect($label)->toBe('Error');
        });

        it('handles empty event type', function () {
            $label = $this->mapper->getLabel('');

            expect($label)->toBe('');
        });

        it('handles event type with spaces', function () {
            $label = $this->mapper->getLabel('my event type');

            expect($label)->toBe('My Event Type');
        });

        it('handles event type with multiple underscores', function () {
            $label = $this->mapper->getLabel('very_long_custom_event_type');

            expect($label)->toBe('Very Long Custom Event Type');
        });

        it('handles mixed case unknown event type', function () {
            $label = $this->mapper->getLabel('MiXeD_CaSe_EvEnT');

            expect($label)->toBe('Mixed Case Event');
        });
    });
});
