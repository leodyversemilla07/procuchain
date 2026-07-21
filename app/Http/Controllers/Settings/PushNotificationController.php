<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\PushNotification\PushSubscribeRequest;
use App\Http\Requests\PushNotification\PushUnsubscribeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PushNotificationController extends Controller
{
    /**
     * List the current user's push subscriptions (JSON API).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $subscriptions = $user->pushSubscriptions;

        return response()->json([
            'count' => $subscriptions->count(),
            'subscriptions' => $subscriptions,
        ]);
    }

    /**
     * Show the push notification settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        return Inertia::render('settings/push-notification', [
            'vapidPublicKey' => config('webpush.vapid.public_key'),
            'subscriptions' => $user->pushSubscriptions,
        ]);
    }

    /**
     * Subscribe user to push notifications (POST)
     */
    public function store(PushSubscribeRequest $request): RedirectResponse
    {
        $request->validated();

        $user = $request->user();
        abort_if($user === null, 401);

        // Check if subscription already exists
        $existingSubscription = $user->pushSubscriptions()
            ->where('endpoint', $request->endpoint)
            ->first();

        if ($existingSubscription) {
            return redirect()->back()->with('success', 'You are already subscribed to push notifications.');
        }

        // Create new subscription
        $user->pushSubscriptions()->create([
            'endpoint' => $request->endpoint,
            'public_key' => $request->input('keys.p256dh'),
            'auth_token' => $request->input('keys.auth'),
            'content_encoding' => $request->input('contentEncoding', 'aesgcm'),
        ]);

        return redirect()->back()->with('success', 'Successfully subscribed to push notifications!');
    }

    /**
     * Unsubscribe user from push notifications (DELETE)
     */
    public function destroy(PushUnsubscribeRequest $request): RedirectResponse
    {
        $request->validated();

        $user = $request->user();
        abort_if($user === null, 401);

        $deleted = $user->pushSubscriptions()
            ->where('endpoint', $request->endpoint)
            ->delete();

        if ($deleted) {
            return redirect()->back()->with('success', 'Successfully unsubscribed from push notifications.');
        }

        return redirect()->back()->with('error', 'Push subscription not found.');
    }
}
