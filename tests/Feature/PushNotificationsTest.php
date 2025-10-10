<?php

use App\Models\User;
use App\Notifications\ProcurementStageNotification;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\PushSubscription;

it('provides the VAPID public key as an Inertia prop', function () {
    config(['webpush.vapid.public_key' => 'test_public_key']);

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/settings/push-notification');
    $response->assertSuccessful();
    $response->assertInertia(
        fn ($page) => $page
            ->component('settings/push-notification')
            ->where('vapidPublicKey', 'test_public_key')
    );
});

it('subscribes the user to push notifications and avoids duplicates', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $payload = [
        'endpoint' => 'https://push.example.com/endpoint/123',
        'keys' => [
            'p256dh' => 'fake_p256dh_key',
            'auth' => 'fake_auth_token',
        ],
        'contentEncoding' => 'aesgcm',
    ];

    // First subscribe should create a record and redirect with success flash
    $first = $this->post('/settings/push/subscribe', $payload);
    $first->assertRedirect(route('push-notification.edit'));
    $first->assertSessionHas('flash.message', 'Successfully subscribed to push notifications!');
    $first->assertSessionHas('flash.type', 'success');

    expect(PushSubscription::query()->where('endpoint', $payload['endpoint'])->count())
        ->toBe(1);

    // Second subscribe with same endpoint should not duplicate and redirect with info flash
    $second = $this->post('/settings/push/subscribe', $payload);
    $second->assertRedirect(route('push-notification.edit'));
    $second->assertSessionHas('flash.message', 'You are already subscribed to push notifications');
    $second->assertSessionHas('flash.type', 'info');

    expect(PushSubscription::query()->where('endpoint', $payload['endpoint'])->count())
        ->toBe(1);
});

it('lists the current user subscriptions', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Seed one subscription via the relation to ensure proper linkage
    $user->pushSubscriptions()->create([
        'endpoint' => 'https://push.example.com/endpoint/xyz',
        'public_key' => 'k',
        'auth_token' => 't',
        'content_encoding' => 'aesgcm',
    ]);

    $response = $this->get('/settings/push/subscriptions');
    $response->assertSuccessful();
    $response->assertJson([
        'count' => 1,
    ]);
});

it('unsubscribes the user from push notifications', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $endpoint = 'https://push.example.com/endpoint/remove-me';

    $user->pushSubscriptions()->create([
        'endpoint' => $endpoint,
        'public_key' => 'k',
        'auth_token' => 't',
        'content_encoding' => 'aesgcm',
    ]);

    expect(PushSubscription::query()->where('endpoint', $endpoint)->exists())->toBeTrue();

    $response = $this->delete('/settings/push/unsubscribe', ['endpoint' => $endpoint]);
    $response->assertRedirect(route('push-notification.edit'));
    $response->assertSessionHas('flash.message', 'Successfully unsubscribed from push notifications');
    $response->assertSessionHas('flash.type', 'success');

    expect(PushSubscription::query()->where('endpoint', $endpoint)->exists())->toBeFalse();
});

it('returns an error flash when unsubscribing a non-existent endpoint', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->delete('/settings/push/unsubscribe', ['endpoint' => 'https://push.example.com/missing']);
    $response->assertRedirect(route('push-notification.edit'));
    $response->assertSessionHas('flash.message', 'Push subscription not found');
    $response->assertSessionHas('flash.type', 'error');
});

it('can dispatch a procurement stage notification to the authenticated user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Notification::fake();

    $data = [
        'procurement_id' => 'TEST-001',
        'procurement_title' => 'Test Procurement for Push Notifications',
        'stage_identifier' => 'Test Stage',
        'current_status' => 'testing',
        'timestamp' => now()->toDateTimeString(),
        'action_type' => 'tested',
        'document_count' => 1,
    ];

    $user->notify(new ProcurementStageNotification($data));

    Notification::assertSentTo($user, ProcurementStageNotification::class);
});

