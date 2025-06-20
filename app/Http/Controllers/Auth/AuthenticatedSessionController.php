<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\LoginTrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    protected LoginTrackingService $loginTracker;

    public function __construct(LoginTrackingService $loginTracker)
    {
        $this->loginTracker = $loginTracker;
    }

    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Check if MFA verification is required
        if (session('mfa_user_id')) {
            return redirect()->route('mfa.verify.form');
        }

        session()->regenerate();

        $user = Auth::user();

        // Log successful login
        $this->loginTracker->logLogin($user, $request);

        // Mark MFA as verified for this session if user has MFA enabled
        if ($user->hasMfaEnabled()) {
            session(['mfa_verified_' . $user->id => true]);
        }

        switch ($user->role) {
            case 'bac_secretariat':
                return redirect()->intended(route('bac-secretariat.dashboard'));
            case 'bac_chairman':
                return redirect()->intended(route('bac-chairman.dashboard'));
            case 'hope':
                return redirect()->intended(route('hope.dashboard'));
            case 'admin':
                return redirect()->intended(route('admin.dashboard'));
            default:
                return redirect('/');
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();

        // Log logout before destroying session
        if ($user) {
            $this->loginTracker->logLogout($user);
            // Clear MFA verification for this session
            session()->forget('mfa_verified_' . $user->id);
        }

        // Clear any MFA session data
        session()->forget(['mfa_user_id', 'remember_user']);

        Auth::guard('web')->logout();

        session()->invalidate();
        session()->regenerateToken();

        return redirect('/');
    }
}
