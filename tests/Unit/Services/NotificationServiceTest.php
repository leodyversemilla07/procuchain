<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Notifications\ProcurementStageNotification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $notificationService;

    private User $bacChairman;

    private User $hope;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notificationService = new NotificationService;

        // Create test users with specific roles
        $this->bacChairman = User::factory()->create(['role' => 'bac_chairman']);
        $this->hope = User::factory()->create(['role' => 'hope']);
        $this->admin = User::factory()->create(['role' => 'admin']);

        // Fake notifications
        Notification::fake();
    }

    public function test_notification_is_sent_to_bac_chairman_hope_and_admin()
    {
        Log::shouldReceive('info')
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

        Notification::assertSentTo(
            [$this->bacChairman, $this->hope, $this->admin],
            ProcurementStageNotification::class,
            function ($notification) {
                $data = $notification->toArray($this->bacChairman);

                return $data['procurement_id'] === 'PROC-001' &&
                    $data['procurement_title'] === 'Test Procurement' &&
                    $data['stage_identifier'] === 'Bidding' &&
                    $data['current_status'] === 'pending' &&
                    $data['action_type'] === 'uploaded';
            }
        );
    }

    public function test_notification_includes_stage_transition_data_when_provided()
    {
        Log::shouldReceive('info')->once();

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

        Notification::assertSentTo(
            [$this->bacChairman, $this->hope, $this->admin],
            ProcurementStageNotification::class,
            function ($notification) {
                $data = $notification->toArray($this->bacChairman);

                return $data['procurement_id'] === 'PROC-001' &&
                    $data['next_stage'] === 'Post-Qualification';
            }
        );
    }

    public function test_warning_is_logged_when_no_users_found()
    {
        // Delete the test users
        User::query()->delete();

        Log::shouldReceive('warning')
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

        Notification::assertNothingSent();
    }

    public function test_notification_is_not_sent_to_other_roles()
    {
        Log::shouldReceive('info')->once();

        // Create a user with a different role
        $otherUser = User::factory()->create(['role' => 'bac_secretariat']);

        $this->notificationService->notifyStageUpdate(
            'PROC-001',
            'Test Procurement',
            'Bidding',
            'pending',
            now()->toDateTimeString(),
            'uploaded'
        );

        Notification::assertNotSentTo([$otherUser], ProcurementStageNotification::class);
    }
}
