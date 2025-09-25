<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireMfa
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // If user has MFA enabled but hasn't completed MFA verification
        if ($user && $user->hasMfaEnabled() && ! session('mfa_verified_'.$user->id)) {
            // Store user ID and remember preference for MFA verification
            session(['mfa_user_id' => $user->id]);
            if ($request->has('remember')) {
                session(['remember_user' => $request->boolean('remember')]);
            }

            // Logout the user temporarily until MFA is verified
            Auth::logout();

            return redirect()->route('mfa.verify.form');
        }

        return $next($request);
    }
}
