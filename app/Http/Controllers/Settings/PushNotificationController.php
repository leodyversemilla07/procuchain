<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use NotificationChannels\WebPush\PushSubscription;

class PushNotificationController extends Controller
{
    /**
     * Show the push notification settings page.
     */
    public function edit()
    {
        return Inertia::render('settings/push-notification');
    }

    /**
     * Subscribe user to push notifications
     *
     * @param Request $request
     * @return \Inertia\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'endpoint' => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        $user = Auth::user();
        
        if (!$user) {
            return Inertia::render('settings/push-notification', [
                'error' => 'User not authenticated'
            ]);
        }

        // Check if subscription already exists
        $existingSubscription = $user->pushSubscriptions()
            ->where('endpoint', $request->endpoint)
            ->first();

        if ($existingSubscription) {
            return Inertia::render('settings/push-notification', [
                'flash' => [
                    'message' => 'You are already subscribed to push notifications',
                    'type' => 'info'
                ]
            ]);
        }

        // Create new subscription
        $subscription = $user->pushSubscriptions()->create([
            'endpoint' => $request->endpoint,
            'public_key' => $request->input('keys.p256dh'),
            'auth_token' => $request->input('keys.auth'),
            'content_encoding' => $request->input('contentEncoding', 'aesgcm'),
        ]);

        return Inertia::render('settings/push-notification', [
            'flash' => [
                'message' => 'Successfully subscribed to push notifications!',
                'type' => 'success'
            ]
        ]);
    }

    /**
     * Unsubscribe user from push notifications
     *
     * @param Request $request
     * @return \Inertia\Response
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'endpoint' => 'required|string',
        ]);

        $user = Auth::user();
        
        if (!$user) {
            return Inertia::render('settings/push-notification', [
                'error' => 'User not authenticated'
            ]);
        }

        $deleted = $user->pushSubscriptions()
            ->where('endpoint', $request->endpoint)
            ->delete();

        if ($deleted) {
            return Inertia::render('settings/push-notification', [
                'flash' => [
                    'message' => 'Successfully unsubscribed from push notifications',
                    'type' => 'success'
                ]
            ]);
        }

        return Inertia::render('settings/push-notification', [
            'flash' => [
                'message' => 'Push subscription not found',
                'type' => 'error'
            ]
        ]);
    }

    /**
     * Get user's push subscriptions
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $subscriptions = $user->pushSubscriptions;

        return response()->json([
            'subscriptions' => $subscriptions,
            'count' => $subscriptions->count()
        ]);
    }

    /**
     * Get VAPID public key for client-side subscription
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVapidPublicKey()
    {
        $publicKey = config('webpush.vapid.public_key');
        
        if (!$publicKey) {
            return response()->json(['error' => 'VAPID public key not configured'], 500);
        }

        return response()->json([
            'vapid_public_key' => $publicKey
        ]);
    }
}
