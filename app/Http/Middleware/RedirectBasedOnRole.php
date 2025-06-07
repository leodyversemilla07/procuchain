<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectBasedOnRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If the user is authenticated, redirect based on role
        if (Auth::check()) {
            $user = Auth::user();

            switch ($user->role) {
                case 'bac_secretariat':
                    return redirect()->intended(route('bac-secretariat.dashboard'));
                case 'bac_chairman':
                    return redirect()->intended(route('bac-chairman.dashboard'));
                case 'hope':
                    return redirect()->intended(route('hope.dashboard'));
                case 'admin':
                    return redirect()->intended(route('admin.dashboard'));
            }
        }

        return $next($request);
    }
}
