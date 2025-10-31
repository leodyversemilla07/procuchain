<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateEmailNotificationsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class EmailNotificationController extends Controller
{
    /**
     * Show the email notification settings page.
     */
    public function edit()
    {
        $user = Auth::user();

        return Inertia::render('settings/email-notification', [
            'emailNotificationsEnabled' => $user->email_notifications_enabled,
        ]);
    }

    /**
     * Update email notification settings.
     */
    public function update(UpdateEmailNotificationsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = Auth::user();

        if (! $user) {
            return redirect()
                ->route('email-notification.edit')
                ->with('flash', [
                    'message' => 'User not authenticated',
                    'type' => 'error',
                ]);
        }

        $user->update([
            'email_notifications_enabled' => $validated['email_notifications_enabled'],
        ]);

        return redirect()
            ->route('email-notification.edit')
            ->with('flash', [
                'message' => 'Email notification settings updated successfully!',
                'type' => 'success',
            ]);
    }
}
