<?php

use App\Services\StreamKeyService;

describe('StreamKeyService', function () {
    beforeEach(function () {
        $this->service = new StreamKeyService;
    });

    describe('generate', function () {
        it('generates stream key from procurement ID and title', function () {
            $streamKey = $this->service->generate('PROC-2024-001', 'Road Construction Project');

            expect($streamKey)->toBe('PROC-2024-001_road_construction_project');
        });

        it('converts title to lowercase', function () {
            $streamKey = $this->service->generate('PROC-001', 'UPPERCASE TITLE');

            expect($streamKey)->toBe('PROC-001_uppercase_title');
        });

        it('replaces spaces with underscores', function () {
            $streamKey = $this->service->generate('PROC-001', 'Multiple Word Title');

            expect($streamKey)->toBe('PROC-001_multiple_word_title');
        });

        it('removes special characters from ID', function () {
            $streamKey = $this->service->generate('PROC@2024#001', 'Project');

            expect($streamKey)->toBe('PROC2024001_project');
        });

        it('removes special characters from title', function () {
            $streamKey = $this->service->generate('PROC-001', 'Road & Bridge (Phase 1)');

            expect($streamKey)->toBe('PROC-001_road_bridge_phase_1');
        });

        it('collapses multiple underscores into one', function () {
            $streamKey = $this->service->generate('PROC-001', 'Multiple     Spaces');

            expect($streamKey)->toBe('PROC-001_multiple_spaces');
        });

        it('trims leading and trailing underscores', function () {
            $streamKey = $this->service->generate('PROC-001', '  Leading and Trailing  ');

            expect($streamKey)->not->toStartWith('_');
            expect($streamKey)->not->toEndWith('_');
        });

        it('truncates stream key to 64 characters', function () {
            $longTitle = str_repeat('Very Long Title ', 10); // Very long string
            $streamKey = $this->service->generate('PROC-2024-001', $longTitle);

            expect(strlen($streamKey))->toBeLessThanOrEqual(64);
        });

        it('removes trailing underscores after truncation', function () {
            $longTitle = str_repeat('word ', 50);
            $streamKey = $this->service->generate('PROC-001', $longTitle);

            expect($streamKey)->not->toEndWith('_');
            expect(strlen($streamKey))->toBeLessThanOrEqual(64);
        });

        it('handles empty title', function () {
            $streamKey = $this->service->generate('PROC-001', '');

            expect($streamKey)->toStartWith('PROC-001');
        });

        it('handles title with only special characters', function () {
            $streamKey = $this->service->generate('PROC-001', '!@#$%^&*()');

            expect($streamKey)->toStartWith('PROC-001');
        });

        it('preserves hyphens in ID and title', function () {
            $streamKey = $this->service->generate('PROC-2024-001', 'Phase-1-Project');

            expect($streamKey)->toContain('-');
        });

        it('generates consistent output for same input', function () {
            $streamKey1 = $this->service->generate('PROC-001', 'Test Project');
            $streamKey2 = $this->service->generate('PROC-001', 'Test Project');

            expect($streamKey1)->toBe($streamKey2);
        });

        it('generates different keys for different inputs', function () {
            $streamKey1 = $this->service->generate('PROC-001', 'Project A');
            $streamKey2 = $this->service->generate('PROC-002', 'Project B');

            expect($streamKey1)->not->toBe($streamKey2);
        });

        it('handles numeric titles', function () {
            $streamKey = $this->service->generate('PROC-001', '2024 Budget');

            expect($streamKey)->toBe('PROC-001_2024_budget');
        });

        it('handles mixed case with numbers', function () {
            $streamKey = $this->service->generate('PROC-001', 'Project 123 ABC');

            expect($streamKey)->toBe('PROC-001_project_123_abc');
        });
    });
});
