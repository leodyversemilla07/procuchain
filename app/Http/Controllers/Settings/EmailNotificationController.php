<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'email_notifications_enabled' => 'required|boolean',
        ]);

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
            'email_notifications_enabled' => $request->email_notifications_enabled,
        ]);

        return redirect()
            ->route('email-notification.edit')
            ->with('flash', [
                'message' => 'Email notification settings updated successfully!',
                'type' => 'success',
            ]);
    }
}
