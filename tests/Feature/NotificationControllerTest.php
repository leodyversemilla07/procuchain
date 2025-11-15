<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'bac_chairman', 'guard_name' => 'web', 'guard_name' => 'web']);
    $this->user = User::factory()->create();
    $this->user->assignRole('bac_chairman');
});

test('user can view notifications page with data', function () {
    actingAs($this->user);

    foreach (range(1, 15) as $i) {
        DatabaseNotification::create([
            'id' => Str::uuid(),
            'type' => 'App\Notifications\ProcurementStageNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'data' => [
                'pr_number' => "PROC-{$i}",
                'procurement_title' => "Test Procurement {$i}",
                'stage_identifier' => 'Test Stage',
                'current_status' => 'pending',
                'timestamp' => now()->toDateTimeString(),
            ],
            'created_at' => now(),
        ]);
    }

    $response = get('/notifications');

    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications')
            ->has('notifications', 15) // Cursor pagination shows 15 per page
            ->has('next_cursor') // Cursor-based pagination uses next_cursor
            ->where('has_more', false) // No more pages (only 15 total)
            ->where('unread_count', 15)
        );
});

test('user can mark a notification as read via form submission', function () {
    actingAs($this->user);

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

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('user cannot mark another users notification as read', function () {
    actingAs($this->user);
    Role::firstOrCreate(['name' => 'hope', 'guard_name' => 'web', 'guard_name' => 'web']);
    $otherUser = User::factory()->create();
    $otherUser->assignRole('hope');

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

    expect($notification->fresh()->read_at)->toBeNull();
});

test('user can mark all notifications as read via form submission', function () {
    actingAs($this->user);

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

    expect($this->user->unreadNotifications()->count())
        ->toBe(0, 'All notifications should be marked as read');
});
