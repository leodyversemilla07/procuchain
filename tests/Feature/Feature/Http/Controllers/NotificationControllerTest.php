<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'bac_chairman']);
});

test('user can view notifications page with data', function () {
    $this->actingAs($this->user);

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

    $response = $this->get('/notifications');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications')
            ->has('notifications', 10) // Should have paginated data
            ->has('pagination', fn (Assert $pagination) => $pagination
                ->where('total', 15)
                ->where('per_page', 10)
                ->where('current_page', 1)
                ->where('last_page', 2)
            )
            ->where('unread_count', 15)
        );
});

test('user can mark a notification as read via form submission', function () {
    $this->actingAs($this->user);

    $notification = DatabaseNotification::create([
        'id' => Str::uuid(),
        'type' => 'App\Notifications\ProcurementStageNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['message' => 'Test notification'],
        'created_at' => now(),
    ]);

    $response = $this->from('/notifications')
        ->post("/notifications/{$notification->id}/mark-as-read");

    $response->assertRedirect('/notifications')
        ->assertSessionHas('success', 'Notification marked as read');

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

    $response = $this->from('/notifications')
        ->post("/notifications/{$notification->id}/mark-as-read");

    $response->assertRedirect('/notifications')
        ->assertSessionHasErrors(['error' => 'Notification not found']);

    $this->assertNull($notification->fresh()->read_at);
});

test('user can mark all notifications as read via form submission', function () {
    $this->actingAs($this->user);

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

    $response = $this->from('/notifications')
        ->post('/notifications/mark-all-as-read');

    $response->assertRedirect('/notifications')
        ->assertSessionHas('success', 'All notifications marked as read');

    $this->assertEquals(
        0,
        $this->user->unreadNotifications()->count(),
        'All notifications should be marked as read'
    );
});
