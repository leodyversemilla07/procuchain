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

test('sends notifications to bac chairman and hope users on stage update', function () {
    Notification::fake();

    // Arrange: Create users with specific roles
    $bacChairman = User::factory()->create(['role' => 'bac_chairman', 'email' => 'bac_chairman@example.com', 'name' => 'BAC Chairman User']);
    $hopeUser = User::factory()->create(['role' => 'hope', 'email' => 'hope@example.com', 'name' => 'HOPE User']);
    $otherUser = User::factory()->create(['role' => 'bac_secretariat', 'email' => 'other@example.com', 'name' => 'Other User']); // Should not receive notification

    $procurementId = 'PROC-2025-001';
    $procurementTitle = 'Test Procurement Project';
    $stageIdentifier = 'Pre-Bid Conference';
    $currentStatus = 'Scheduled';
    $timestamp = now()->toDateTimeString();
    $actionType = 'scheduled'; // This will be formatted as 'has been updated' by default
    $documentCount = 3;
    $stageTransition = true;
    $nextStage = 'Bid Submission';

    // Act: Call the notification service
    $this->notificationService->notifyStageUpdate(
        $procurementId,
        $procurementTitle,
        $stageIdentifier,
        $currentStatus,
        $timestamp,
        $actionType,
        $documentCount,
        $stageTransition,
        $nextStage
    );

    // Assert: Notifications were sent to the correct users
    Notification::assertSentTo(
        [$bacChairman, $hopeUser],
        ProcurementStageNotification::class
    );

    Notification::assertNotSentTo(
        [$otherUser],
        ProcurementStageNotification::class
    );

    // Assert: Notification content for one of the users (e.g., BAC Chairman)
    Notification::assertSentTo($bacChairman, function (ProcurementStageNotification $notification, $channels) use ($procurementId, $procurementTitle, $stageIdentifier, $currentStatus, $actionType, $documentCount, $nextStage, $bacChairman, $stageTransition) {
        expect($channels)->toContain('mail', 'database');

        $mailData = $notification->toMail($bacChairman);
        expect($mailData->subject)->toBe("Procurement Update: {$stageIdentifier} - {$procurementTitle}");

        // Since we use a custom view, check the view data instead of standard properties
        expect($mailData->viewData)->toHaveKey('notifiable')
            ->and($mailData->viewData['notifiable']->name)->toBe('BAC Chairman User');

        // 'scheduled' actionType defaults to 'has been updated' in formatActionType
        $formattedActionText = 'has been updated';
        
        // Check view data for the expected content
        expect($mailData->viewData['stageIdentifier'])->toBe($stageIdentifier)
            ->and($mailData->viewData['formattedAction'])->toBe($formattedActionText)
            ->and($mailData->viewData['documentCount'])->toBe($documentCount)
            ->and($mailData->viewData['procurementTitle'])->toBe($procurementTitle)
            ->and($mailData->viewData['procurementId'])->toBe($procurementId)
            ->and($mailData->viewData['currentStatus'])->toBe($currentStatus)
            ->and($mailData->viewData['actionType'])->toBe($actionType);

        if ($stageTransition) {
            expect($mailData->viewData['nextStage'])->toBe($nextStage);
        }
        expect($mailData->viewData['actionUrl'])->toBe(url("/bac-chairman/procurements-list/{$procurementId}"));

        $databaseData = $notification->toDatabase($bacChairman)->data;
        if ($stageTransition) {
            expect($databaseData['title'])->toBe("Stage Transition: {$stageIdentifier} to {$nextStage}")
                ->and($databaseData['message'])->toContain("The procurement is now moving to the {$nextStage} stage.");
        } else {
            expect($databaseData['title'])->toBe("{$stageIdentifier} Update")
                ->and($databaseData['message'])->not->toContain("The procurement is now moving to the {$nextStage} stage.");
        }

        $expectedMessageStart = "The {$stageIdentifier} stage {$formattedActionText} for \"{$procurementTitle}\". Current status: {$currentStatus}";
        if ($stageTransition) {
            $expectedMessageStart .= '.';
        }
        expect($databaseData['message'])->toStartWith($expectedMessageStart);
        expect($databaseData['procurement_id'])->toBe($procurementId)
            ->and($databaseData['document_count'])->toBe($documentCount)
            ->and($databaseData['action_type'])->toBe($actionType)
            ->and($databaseData['url'])->toBe(url("/bac-chairman/procurements-list/{$procurementId}"));

        return true;
    });
});

test('sends notification without stage transition and documents', function () {
    Notification::fake();

    $bacChairman = User::factory()->create(['role' => 'bac_chairman', 'email' => 'bac_chairman2@example.com', 'name' => 'BAC Chairman Two']);

    $procurementId = 'PROC-2025-002';
    $procurementTitle = 'Simple Update Project';
    $stageIdentifier = 'Monitoring';
    $currentStatus = 'Ongoing';
    $timestamp = now()->toDateTimeString();
    $actionType = 'updated'; // This will be formatted as 'has been updated' by default
    $documentCount = 0; // No documents
    $stageTransition = false; // No stage transition
    $nextStage = '';

    $this->notificationService->notifyStageUpdate(
        $procurementId,
        $procurementTitle,
        $stageIdentifier,
        $currentStatus,
        $timestamp,
        $actionType,
        $documentCount,
        $stageTransition,
        $nextStage
    );

    Notification::assertSentTo($bacChairman, function (ProcurementStageNotification $notification, $channels) use ($stageIdentifier, $procurementTitle, $bacChairman, $actionType, $currentStatus, $procurementId, $documentCount) {
        $mailData = $notification->toMail($bacChairman);
        // 'updated' actionType defaults to 'has been updated' in formatActionType
        $formattedActionText = 'has been updated';
        expect($mailData->subject)->toBe("Procurement Update: {$stageIdentifier} - {$procurementTitle}");

        // Check view data for the expected content
        expect($mailData->viewData['stageIdentifier'])->toBe($stageIdentifier)
            ->and($mailData->viewData['formattedAction'])->toBe($formattedActionText)
            ->and($mailData->viewData['documentCount'])->toBe($documentCount)
            ->and($mailData->viewData['procurementTitle'])->toBe($procurementTitle)
            ->and($mailData->viewData['procurementId'])->toBe($procurementId)
            ->and($mailData->viewData['currentStatus'])->toBe($currentStatus)
            ->and($mailData->viewData['actionType'])->toBe($actionType);

        // Ensure no stage transition data is present
        expect($mailData->viewData['nextStage'])->toBeNull();

        $databaseData = $notification->toDatabase($bacChairman)->data;
        $expectedMessage = "The {$stageIdentifier} stage {$formattedActionText} for \"{$procurementTitle}\". Current status: {$currentStatus}";

        expect($databaseData['title'])->toBe("{$stageIdentifier} Update")
            ->and($databaseData['message'])->not->toContain('The procurement is now moving to the')
            ->and($databaseData['message'])->toBe($expectedMessage)
            ->and($databaseData['document_count'])->toBe(0)
            ->and($databaseData['action_type'])->toBe($actionType);

        return true;
    });
});

test('does not send notification if no relevant users exist', function () {
    Notification::fake();

    User::factory()->create(['role' => 'bac_secretariat']); // A user that should not be notified

    $this->notificationService->notifyStageUpdate(
        'PROC-TEST-003', 'No User Project', 'Initial Stage', 'Pending', now()->toDateTimeString(), 'created', 0, false, ''
    );

    Notification::assertNothingSent();
});
