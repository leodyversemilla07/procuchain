<?php

use App\Jobs\BlockchainWriteJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

describe('BlockchainWriteJob failed() method', function () {
    beforeEach(function () {
        Log::spy();
        config(['cache.default' => 'array']);
        Storage::fake('local');
    });

    it('writes permanent failed status to cache when all retries exhausted', function () {
        $jobId = 'job-uuid-perma-fail';

        $job = new BlockchainWriteJob(
            'upload_document',
            ['pr_number' => 'PR-2025-992-0001'],
            $jobId,
        );

        $exception = new Exception('Blockchain RPC timeout after 3 retries');

        $job->failed($exception);

        $cached = Cache::get("blockchain_job:{$jobId}");
        expect($cached)->toBeArray()
            ->and($cached['status'])->toBe('failed')
            ->and($cached['error'])->toBe('Blockchain RPC timeout after 3 retries');
    });

    it('overwrites retrying status with failed when retries exhausted', function () {
        $jobId = 'job-uuid-retry-to-fail';

        Cache::put("blockchain_job:{$jobId}", [
            'status' => 'retrying',
            'attempt' => 3,
            'max_attempts' => 3,
        ], now()->addHour());

        $job = new BlockchainWriteJob(
            'upload_document',
            ['pr_number' => 'PR-2025-992-0002'],
            $jobId,
        );

        $exception = new Exception('Node unreachable');

        $job->failed($exception);

        $cached = Cache::get("blockchain_job:{$jobId}");
        expect($cached['status'])->toBe('failed')
            ->and($cached['error'])->toBe('Node unreachable');
    });

    it('cleans up upload temp File only after permanent failure', function () {
        $jobId = 'job-uuid-cleanup-final-failure';
        Storage::put('temp/final-failure.pdf', 'content');

        $job = new BlockchainWriteJob(
            'upload_document',
            [
                'pr_number' => 'PR-2025-992-0003',
                'temp_file_path' => 'temp/final-failure.pdf',
            ],
            $jobId,
        );

        $job->failed(new Exception('Permanent upload failure'));

        Storage::assertMissing('temp/final-failure.pdf');
    });
});
