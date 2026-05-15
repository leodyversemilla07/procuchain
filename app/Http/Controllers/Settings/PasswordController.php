<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdatePasswordRequest;
use App\Services\AuditLogger;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    public function __construct(
        private AuditLogger $auditLogger,
    ) {}

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

        // Update password first
        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        // Refresh the user model so the guard has the updated password hash
        $user->refresh();

        // Invalidate all other sessions.
        // After the password update, the new password is in the DB,
        // so we pass the new password to logoutOtherDevices.
        $this->invalidateOtherSessions($validated['password']);

        Log::info('User password changed - other sessions invalidated', [
            'user_id' => $user->id,
        ]);

        $this->auditLogger->log(
            'settings.password_changed',
            'user',
            (string) $user->id,
        );

        return back()->with('status', 'password-updated');
    }

    /**
     * Invalidate all other sessions except the current one.
     *
     * This ensures that if a password was compromised, the attacker
     * will be logged out from all sessions immediately.
     *
     * Order matters: logoutOtherDevices MUST be called before session()->regenerate().
     * If regenerate() runs first, the session ID changes and logoutOtherDevices
     * cannot identify the current session to preserve it — potentially logging
     * the user out of ALL sessions including the current one.
     *
     * Requires `auth.session` (AuthenticateSession) middleware on the route.
     *
     * @see https://laravel.com/docs/13.x/authentication#invalidating-sessions-on-other-devices
     */
    private function invalidateOtherSessions(string $password): void
    {
        // logoutOtherDevices verifies the given password against the current DB hash,
        // then rehashes it to trigger AuthenticateSession's invalidation of other sessions.
        Auth::logoutOtherDevices($password);

        // Regenerate the current session token for added security
        request()->session()->regenerate();
    }
}
