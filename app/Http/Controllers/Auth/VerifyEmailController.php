<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(Request $request): RedirectResponse|Response
    {
        // Check if user is authenticated
        if (! $request->user()) {
            return redirect()->route('login');
        }

        // Check if the logged-in user matches the user ID in the URL
        if ($request->user()->id != $request->route('id')) {
            return response()->view('errors.verification-failed', [], 403);
        }

        // Verify the signature manually to show custom error page
        if (! URL::hasValidSignature($request)) {
            return response()->view('errors.verification-failed', [], 403);
        }

        // Verify the hash matches the user's email
        if (! hash_equals((string) $request->route('hash'), sha1($request->user()->email))) {
            return response()->view('errors.verification-failed', [], 403);
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
