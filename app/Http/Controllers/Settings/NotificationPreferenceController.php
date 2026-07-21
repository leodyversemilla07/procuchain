<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateNotificationPreferencesRequest;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/notification-preferences', [
            ...$this->preferenceService->getPreferencesForFrontend($user),
            'eventTypes' => NotificationPreferenceService::EVENT_TYPES,
            'channels' => NotificationPreferenceService::CHANNELS,
        ]);
    }

    /**
     * Update notification preferences.
     */
    public function update(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = $request->user();

        try {
            $user->update([
                'email_notifications_enabled' => $validated['email_notifications_enabled'],
            ]);

            $this->preferenceService->updatePreferences($user, $validated['notification_preferences']);

            Log::info('User updated notification preferences', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return redirect()->back()->with('success', 'Notification preferences updated successfully!');
        } catch (\Exception $e) {
            report($e);
            Log::error('Failed to update notification preferences', [
                'user_id' => $user->id,
                'error' => 'An error occurred updating notification preferences.',
            ]);

            return redirect()->back()->with('error', 'Failed to update notification preferences. Please try again.');
        }
    }
}
