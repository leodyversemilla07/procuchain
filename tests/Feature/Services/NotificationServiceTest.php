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
                        $context['pr_number'] === 'PROC-001' &&
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

                    return $data['pr_number'] === 'PROC-001' &&
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
            User::whereHas('roles', function ($query) {
                $query->whereIn('name', ['bac_chairman', 'hope', 'admin']);
            })->delete();

            Log::shouldReceive('warning')
                ->once()
                ->with('No users found with specified roles to notify for procurement update', [
                    'pr_number' => 'PROC-003',
                    'roles' => ['bac_chairman', 'hope', 'admin'],
                ]);

            // Also expect the Log::info call that would happen if users were found
            Log::shouldReceive('info')->never();

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
            Log::shouldReceive('info')
                ->once()
                ->withArgs(function ($message, $context) {
                    return str_contains($message, 'Procurement stage update notification sent') &&
                        $context['pr_number'] === 'PROC-004';
                });

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

        it('can notify custom roles when specified', function () {
            Log::shouldReceive('info')
                ->once()
                ->withArgs(function ($message, $context) {
                    return str_contains($message, 'Procurement stage update notification sent') &&
                        $context['pr_number'] === 'PROC-005' &&
                        $context['roles_notified'] === ['bac_secretariat', 'admin'] &&
                        $context['recipients_count'] === 2;
                });

            $this->notificationService->notifyStageUpdate(
                pr_number: 'PROC-005',
                procurementTitle: 'Test Procurement 5',
                stageIdentifier: 'Bidding',
                currentStatus: 'pending',
                timestamp: now()->toDateTimeString(),
                actionType: 'uploaded',
                documentCount: 0,
                stageTransition: false,
                nextStage: '',
                rolesToNotify: ['bac_secretariat', 'admin']
            );

            Notification::assertSentTo(
                [$this->bacSecretariat, $this->admin],
                ProcurementStageNotification::class
            );

            Notification::assertNotSentTo(
                [$this->bacChairman, $this->hope],
                ProcurementStageNotification::class
            );
        });

        it('can notify only a single role', function () {
            Log::shouldReceive('info')
                ->once()
                ->withArgs(function ($message, $context) {
                    return str_contains($message, 'Procurement stage update notification sent') &&
                        $context['roles_notified'] === ['hope'] &&
                        $context['recipients_count'] === 1;
                });

            $this->notificationService->notifyStageUpdate(
                pr_number: 'PROC-006',
                procurementTitle: 'Test Procurement 6',
                stageIdentifier: 'Bidding',
                currentStatus: 'pending',
                timestamp: now()->toDateTimeString(),
                actionType: 'uploaded',
                documentCount: 0,
                stageTransition: false,
                nextStage: '',
                rolesToNotify: ['hope']
            );

            Notification::assertSentTo(
                [$this->hope],
                ProcurementStageNotification::class
            );

            Notification::assertNotSentTo(
                [$this->bacChairman, $this->admin, $this->bacSecretariat],
                ProcurementStageNotification::class
            );
        });

        it('logs roles in notification log entry', function () {
            Log::shouldReceive('info')
                ->once()
                ->withArgs(function ($message, $context) {
                    return str_contains($message, 'Procurement stage update notification sent') &&
                        isset($context['roles_notified']) &&
                        $context['roles_notified'] === ['bac_chairman', 'hope', 'admin'];
                });

            $this->notificationService->notifyStageUpdate(
                'PROC-007',
                'Test Procurement 7',
                'Bidding',
                'pending',
                now()->toDateTimeString(),
                'uploaded',
                0
            );
        });
    });
});
