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

        session()->regenerate();

        $user = Auth::user();

        // Log successful login
        $this->loginTracker->logLogin($user, $request);

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
        }

        Auth::guard('web')->logout();

        session()->invalidate();
        session()->regenerateToken();

        return redirect('/');
    }
}
