<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ConfirmablePasswordController extends Controller
{
    /**
     * Show the confirm password page.
     */
    public function show(): Response
    {
        return Inertia::render('auth/confirm-password');
    }

    /**
     * Store a newly confirmed password.
     */
    public function store(Request $request): RedirectResponse
    {
        if (! Auth::guard('web')->validate([
            'email' => $request->user()->email,
            'password' => $request->password,
        ])) {
            return back()->withErrors([
                'password' => __('auth.password'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        // Redirect to the appropriate dashboard based on user role
        $user = $request->user();
        switch ($user->role) {
            case 'bac_secretariat':
                return redirect()->intended(route('bac-secretariat.dashboard'));
            case 'bac_chairman':
                return redirect()->intended(route('bac-chairman.dashboard'));
            case 'hope':
                return redirect()->intended(route('hope.dashboard'));
            default:
                return redirect()->intended('/'); // Fallback to home page
        }
    }
}
