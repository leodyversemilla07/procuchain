<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateEmailNotificationsRequest;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailNotificationController extends Controller
{
    public function __construct(private readonly NotificationPreferenceService $preferenceService) {}

    /**
     * Show the email notification settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/email-notification', $this->preferenceService->getPreferencesForFrontend($user));
    }

    /**
     * Update email notification settings.
     */
    public function update(UpdateEmailNotificationsRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $user->update([
            'email_notifications_enabled' => $validated['email_notifications_enabled'],
        ]);

        if (isset($validated['notification_preferences'])) {
            $this->preferenceService->updatePreferences($user, $validated['notification_preferences']);
        }

        return redirect()->back()->with('success', 'Email notification settings updated successfully!');
    }
}
