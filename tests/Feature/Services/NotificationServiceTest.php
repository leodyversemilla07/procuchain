<?php

use App\Models\User;
use App\Notifications\ProcurementStageNotification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

describe('NotificationService', function () {
    beforeEach(function () {
        $this->notificationService = app(NotificationService::class);
        Notification::fake();
    });

    describe('Stage Update Notifications', function () {
        beforeEach(function () {
            $this->bacChairman = createUserWithRole('bac_chairman');
            $this->hope = createUserWithRole('hope');
            $this->admin = createUserWithRole('admin');
            $this->bacSecretariat = createUserWithRole('bac_secretariat');
        });

        it('sends notifications to bac chairman, hope, and admin', function () {
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
                'uploaded',
                0  // documentCount parameter
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
        });

        it('includes stage transition data when provided', function () {
            $this->notificationService->notifyStageUpdate(
                'PROC-002',
                'Test Procurement 2',
                'Evaluation',
                'completed',
                now()->toDateTimeString(),
                'reviewed',
                0, // documentCount parameter
                true, // stageTransition
                'Evaluation' // nextStage
            );

            Notification::assertSentTo(
                [$this->bacChairman, $this->hope, $this->admin],
                ProcurementStageNotification::class,
                function ($notification) {
                    $data = $notification->toArray($this->bacChairman);

                    return isset($data['next_stage']) &&
                        $data['next_stage'] === 'Evaluation';
                }
            );
        });

        it('logs warning when no eligible users found', function () {
            // Remove all users with eligible roles
            User::whereIn('role', ['bac_chairman', 'hope', 'admin'])->delete();

            Log::shouldReceive('warning')
                ->once()
                ->with('No BAC Chairman, HOPE, or Admin users found to notify for procurement update', [
                    'procurement_id' => 'PROC-003',
                ]);

            $this->notificationService->notifyStageUpdate(
                'PROC-003',
                'Test Procurement 3',
                'Bidding',
                'pending',
                now()->toDateTimeString(),
                'uploaded'
            );

            Notification::assertNothingSent();
        });

        it('does not send notifications to bac_secretariat role', function () {
            $this->notificationService->notifyStageUpdate(
                'PROC-004',
                'Test Procurement 4',
                'Bidding',
                'pending',
                now()->toDateTimeString(),
                'uploaded',
                0  // documentCount parameter
            );

            Notification::assertNotSentTo(
                $this->bacSecretariat,
                ProcurementStageNotification::class
            );
        });
    });
});
