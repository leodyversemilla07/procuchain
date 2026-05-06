<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PushNotificationController extends Controller
{
    /**
     * Show the push notification settings page.
     */
    public function edit()
    {
        return Inertia::render('settings/push-notification', [
            'vapidPublicKey' => config('webpush.vapid.public_key'),
        ]);
    }

    /**
     * Subscribe user to push notifications (POST)
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'endpoint' => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        $user = Auth::user();

        if (! $user) {
            return redirect()
                ->route('settings.push-notification.edit')
                ->with('flash', [
                    'message' => 'User not authenticated',
                    'type' => 'error',
                ]);
        }

        // Check if subscription already exists
        $existingSubscription = $user->pushSubscriptions()
            ->where('endpoint', $request->endpoint)
            ->first();

        if ($existingSubscription) {
            return redirect()
                ->route('settings.push-notification.edit')
                ->with('flash', [
                    'message' => 'You are already subscribed to push notifications',
                    'type' => 'info',
                ]);
        }

        // Create new subscription
        $user->pushSubscriptions()->create([
            'endpoint' => $request->endpoint,
            'public_key' => $request->input('keys.p256dh'),
            'auth_token' => $request->input('keys.auth'),
            'content_encoding' => $request->input('contentEncoding', 'aesgcm'),
        ]);

        return redirect()
            ->route('settings.push-notification.edit')
            ->with('flash', [
                'message' => 'Successfully subscribed to push notifications!',
                'type' => 'success',
            ]);
    }

    /**
     * Unsubscribe user from push notifications (DELETE)
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'endpoint' => 'required|string',
        ]);

        $user = Auth::user();

        if (! $user) {
            return redirect()
                ->route('settings.push-notification.edit')
                ->with('flash', [
                    'message' => 'User not authenticated',
                    'type' => 'error',
                ]);
        }

        $deleted = $user->pushSubscriptions()
            ->where('endpoint', $request->endpoint)
            ->delete();

        if ($deleted) {
            return redirect()
                ->route('settings.push-notification.edit')
                ->with('flash', [
                    'message' => 'Successfully unsubscribed from push notifications',
                    'type' => 'success',
                ]);
        }

        return redirect()
            ->route('settings.push-notification.edit')
            ->with('flash', [
                'message' => 'Push subscription not found',
                'type' => 'error',
            ]);
    }

    /**
     * Get user's push subscriptions
     *
     * @return JsonResponse
     */
    public function index()
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $subscriptions = $user->pushSubscriptions;

        return response()->json([
            'subscriptions' => $subscriptions,
            'count' => $subscriptions->count(),
        ]);
    }
}
