<?php

use App\Models\User;
use App\Notifications\ProcurementStageNotification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;


uses(RefreshDatabase::class);

beforeEach(function () {
    $this->notificationService = app(NotificationService::class);
});

test('notification is sent to bac chairman, hope, and admin', function () {
    \Illuminate\Support\Facades\Notification::fake();

    $bacChairman = User::factory()->create(['role' => 'bac_chairman']);
    $hope = User::factory()->create(['role' => 'hope']);
    $admin = User::factory()->create(['role' => 'admin']);

    \Illuminate\Support\Facades\Log::shouldReceive('info')
        ->once()
        ->withArgs(function ($message, $context) {
            return str_contains($message, 'Procurement stage update notification sent') &&
                $context['procurement_id'] === 'PROC-001' &&
                $context['stage'] === 'Bidding' &&
                $context['recipients_count'] === 3;
        });

    $this->notificationService->notifyStageUpdate(
        'PROC-001',
        'Test Procurement',
        'Bidding',
        'pending',
        now()->toDateTimeString(),
        'uploaded'
    );

    \Illuminate\Support\Facades\Notification::assertSentTo(
        [$bacChairman, $hope, $admin],
        ProcurementStageNotification::class,
        function ($notification) use ($bacChairman) {
            $data = $notification->toArray($bacChairman);

            return $data['procurement_id'] === 'PROC-001' &&
                $data['procurement_title'] === 'Test Procurement' &&
                $data['stage_identifier'] === 'Bidding' &&
                $data['current_status'] === 'pending' &&
                $data['action_type'] === 'uploaded';
        }
    );
});

test('notification includes stage transition data when provided', function () {
    \Illuminate\Support\Facades\Notification::fake();
    \Illuminate\Support\Facades\Log::shouldReceive('info')->once();

    $bacChairman = User::factory()->create(['role' => 'bac_chairman']);
    $hope = User::factory()->create(['role' => 'hope']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->notificationService->notifyStageUpdate(
        'PROC-001',
        'Test Procurement',
        'Bidding',
        'completed',
        now()->toDateTimeString(),
        'completed',
        0, // document count
        true, // stage transition
        'Post-Qualification'
    );

    \Illuminate\Support\Facades\Notification::assertSentTo(
        [$bacChairman, $hope, $admin],
        ProcurementStageNotification::class,
        function ($notification) use ($bacChairman) {
            $data = $notification->toArray($bacChairman);

            return $data['procurement_id'] === 'PROC-001' &&
                $data['next_stage'] === 'Post-Qualification';
        }
    );
});

test('warning is logged when no users found', function () {
    \Illuminate\Support\Facades\Notification::fake();
    // Delete the test users
    User::query()->delete();

    \Illuminate\Support\Facades\Log::shouldReceive('warning')
        ->once()
        ->withArgs(function ($message, $context) {
            return str_contains($message, 'No BAC Chairman, HOPE, or Admin users found to notify for procurement update') &&
                $context['procurement_id'] === 'PROC-001';
        });

    $this->notificationService->notifyStageUpdate(
        'PROC-001',
        'Test Procurement',
        'Bidding',
        'pending',
        now()->toDateTimeString(),
        'uploaded'
    );

    \Illuminate\Support\Facades\Notification::assertNothingSent();
});

test('notification is not sent to other roles', function () {
    \Illuminate\Support\Facades\Notification::fake();
    \Illuminate\Support\Facades\Log::shouldReceive('info')->once();

    $bacChairman = User::factory()->create(['role' => 'bac_chairman']);
    $hope = User::factory()->create(['role' => 'hope']);
    $admin = User::factory()->create(['role' => 'admin']);
    $otherUser = User::factory()->create(['role' => 'bac_secretariat']);

    $this->notificationService->notifyStageUpdate(
        'PROC-001',
        'Test Procurement',
        'Bidding',
        'pending',
        now()->toDateTimeString(),
        'uploaded'
    );

    \Illuminate\Support\Facades\Notification::assertNotSentTo([$otherUser], ProcurementStageNotification::class);
});
