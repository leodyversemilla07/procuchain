<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdatePasswordRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    /**
     * Show the user's password settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/password', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's password.
     *
     * This also invalidates all other sessions for security.
     */
    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // Update password
        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Invalidate all other sessions for security
        // This logs out the user from all other devices
        $this->invalidateOtherSessions($request);

        Log::info('User password changed - other sessions invalidated', [
            'user_id' => $user->id,
        ]);

        return back()->with('status', 'password-updated');
    }

    /**
     * Invalidate all other sessions except the current one.
     *
     * This ensures that if a password was compromised, the attacker
     * will be logged out from all sessions immediately.
     */
    private function invalidateOtherSessions(Request $request): void
    {
        // Get current session ID
        $currentSessionId = $request->session()->getId();

        // Delete all other sessions for this user from the database
        // This works because we're using database session driver
        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        // Regenerate the current session token for added security
        $request->session()->regenerate();

        // Re-authenticate with the new password hash
        // This ensures the remember token is refreshed
        Auth::logoutOtherDevices($request->input('password'));
    }
}
