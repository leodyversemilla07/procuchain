<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferenceController extends Controller
{
    public function __construct(
        private readonly NotificationPreferenceService $preferenceService,
    ) {}

    /**
     * Show the notification preferences page.
     */
    public function edit(): Response
    {
        $user = Auth::user();

        return Inertia::render('settings/notification-preferences', [
            ...$this->preferenceService->getPreferencesForFrontend($user),
            'eventTypes' => NotificationPreferenceService::EVENT_TYPES,
            'channels' => NotificationPreferenceService::CHANNELS,
        ]);
    }

    /**
     * Update notification preferences.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email_notifications_enabled' => ['required', 'boolean'],
            'notification_preferences' => ['required', 'array'],
            'notification_preferences.*.email' => ['boolean'],
            'notification_preferences.*.push' => ['boolean'],
        ]);

        $user = Auth::user();

        try {
            $user->update([
                'email_notifications_enabled' => $validated['email_notifications_enabled'],
            ]);

            $this->preferenceService->updatePreferences($user, $validated['notification_preferences']);

            Log::info('User updated notification preferences', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return redirect()
                ->route('settings.notification-preferences.edit')
                ->with('flash', [
                    'message' => 'Notification preferences updated successfully!',
                    'type' => 'success',
                ]);
        } catch (\Exception $e) {
            Log::error('Failed to update notification preferences', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'notification_preferences' => 'Failed to update notification preferences. Please try again.',
            ]);
        }
    }
}