<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'bac_chairman']);
});

test('user can fetch their notifications with pagination', function () {
    $this->actingAs($this->user);

    // Create some notifications
    foreach (range(1, 15) as $i) {
        DatabaseNotification::create([
            'id' => Str::uuid(),
            'type' => 'App\Notifications\ProcurementStageNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'data' => [
                'procurement_id' => "PROC-{$i}",
                'procurement_title' => "Test Procurement {$i}",
                'stage_identifier' => 'Test Stage',
                'current_status' => 'pending',
                'timestamp' => now()->toDateTimeString(),
            ],
            'created_at' => now(),
        ]);
    }

    $response = $this->getJson('/notifications');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'notifications',
            'pagination' => [
                'total',
                'per_page',
                'current_page',
                'last_page',
            ],
            'unread_count'
        ])
        ->assertJsonCount(10, 'notifications') // Default per_page is 10
        ->assertJson([
            'pagination' => [
                'total' => 15,
                'per_page' => 10,
                'current_page' => 1,
                'last_page' => 2,
            ],
            'unread_count' => 15,
        ]);
});

test('user can mark a notification as read', function () {
    $this->actingAs($this->user);

    $notification = DatabaseNotification::create([
        'id' => Str::uuid(),
        'type' => 'App\Notifications\ProcurementStageNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['message' => 'Test notification'],
        'created_at' => now(),
    ]);

    $response = $this->postJson("/notifications/{$notification->id}/mark-as-read");

    $response->assertStatus(200)
        ->assertJson(['message' => 'Notification marked as read']);

    $this->assertNotNull($notification->fresh()->read_at);
});

test('user cannot mark another users notification as read', function () {
    $this->actingAs($this->user);
    $otherUser = User::factory()->create(['role' => 'hope']);

    $notification = DatabaseNotification::create([
        'id' => Str::uuid(),
        'type' => 'App\Notifications\ProcurementStageNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $otherUser->id,
        'data' => ['message' => 'Test notification'],
        'created_at' => now(),
    ]);

    $response = $this->postJson("/notifications/{$notification->id}/mark-as-read");

    $response->assertStatus(404);
    $this->assertNull($notification->fresh()->read_at);
});

test('user can mark all notifications as read', function () {
    $this->actingAs($this->user);

    // Create 5 unread notifications
    foreach (range(1, 5) as $i) {
        DatabaseNotification::create([
            'id' => Str::uuid(),
            'type' => 'App\Notifications\ProcurementStageNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'data' => ['message' => "Test notification {$i}"],
            'created_at' => now(),
        ]);
    }

    $response = $this->postJson('/notifications/mark-all-as-read');

    $response->assertStatus(200)
        ->assertJson(['message' => 'All notifications marked as read']);

    $this->assertEquals(
        0,
        $this->user->unreadNotifications()->count(),
        'All notifications should be marked as read'
    );
});
