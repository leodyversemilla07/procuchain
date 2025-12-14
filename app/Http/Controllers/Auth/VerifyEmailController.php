<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        // Verify the signature manually to show custom error page
        if (! URL::hasValidSignature($request)) {
            return response()->view('errors.verification-failed', [], 403)
                ->header('Content-Type', 'text/html');
        }

        // Check if user is authenticated
        if (! $request->user()) {
            return redirect()->route('login');
        }

        $dashboardUrl = $this->redirectToDashboard($request->user());

        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended($dashboardUrl.'?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            /** @var \Illuminate\Contracts\Auth\MustVerifyEmail $user */
            $user = $request->user();

            event(new Verified($user));
        }

        return redirect()->intended($dashboardUrl.'?verified=1');
    }
}
