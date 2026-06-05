<?php

use App\Jobs\BlockchainWriteJob;
use App\Models\User;
use App\Notifications\BlockchainJobFailedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    Role::firstOrCreate(['name' => 'bac_secretariat']);

    $this->user = User::factory()->create();
    $this->user->assignRole('bac_secretariat');
});

it('notifies the submitting user when a blockchain job permanently fails', function () {
    $job = new BlockchainWriteJob(
        operation: 'upload_document',
        data: ['pr_number' => 'PR-2026-001-0001'],
        jobId: 'test-job-id-123',
        userId: $this->user->id,
    );

    $job->failed(new RuntimeException('Blockchain node unreachable'));

    Notification::assertSentTo($this->user, BlockchainJobFailedNotification::class);
});

it('does not send a notification when no user_id is provided', function () {
    $job = new BlockchainWriteJob(
        operation: 'upload_document',
        data: ['pr_number' => 'PR-2026-001-0001'],
        jobId: 'test-job-id-456',
        userId: null,
    );

    $job->failed(new RuntimeException('Blockchain node unreachable'));

    Notification::assertNothingSent();
});

it('does not throw when user_id does not match any user', function () {
    $job = new BlockchainWriteJob(
        operation: 'upload_document',
        data: ['pr_number' => 'PR-2026-001-0001'],
        jobId: 'test-job-id-789',
        userId: 999999,
    );

    $job->failed(new RuntimeException('Blockchain node unreachable'));

    Notification::assertNothingSent();
});

it('includes the correct pr_number and operation in the notification payload', function () {
    $job = new BlockchainWriteJob(
        operation: 'mark_stage_complete',
        data: ['pr_number' => 'PR-2026-STAGE'],
        jobId: 'test-job-stage',
        userId: $this->user->id,
    );

    $job->failed(new RuntimeException('Timeout'));

    Notification::assertSentTo(
        $this->user,
        BlockchainJobFailedNotification::class,
        function (BlockchainJobFailedNotification $notification) {
            $payload = $notification->toArray($this->user);

            expect($payload['pr_number'])->toBe('PR-2026-STAGE')
                ->and($payload['operation'])->toBe('mark_stage_complete')
                ->and($payload['action_type'])->toBe('failed');

            return true;
        }
    );
});

it('writes a failed status to the cache entry when the job permanently fails', function () {
    $jobId = 'test-cache-job-id';

    $job = new BlockchainWriteJob(
        operation: 'upload_document',
        data: ['pr_number' => 'PR-2026-CACHE'],
        jobId: $jobId,
        userId: $this->user->id,
    );

    $job->failed(new RuntimeException('Node down'));

    $cached = cache("blockchain_job:{$jobId}");

    expect($cached['status'])->toBe('failed')
        ->and($cached['error'])->toBe('Node down');
});

it('always includes the database channel in the notification', function () {
    $notification = new BlockchainJobFailedNotification(
        operation: 'correct_document',
        prNumber: 'PR-2026-CORR',
        jobId: 'test-channel-job',
        errorMessage: 'Failure',
    );

    expect($notification->via($this->user))->toContain('database');
});
