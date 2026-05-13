<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Get the dashboard route for a given user based on their role.
     */
    protected function redirectToDashboard(Request $request, ?User $user = null): string
    {
        $user = $user ?? $request->user();

        if (! $user) {
            return '/';
        }

        // Load roles once to avoid N+1 queries
        if (! $user->relationLoaded('roles')) {
            $user->load('roles');
        }

        if ($user->hasRole('bac_secretariat')) {
            return route('bac-secretariat.dashboard');
        }

        if ($user->hasRole('bac_chairman')) {
            return route('bac-chairman.dashboard');
        }

        if ($user->hasRole('hope')) {
            return route('hope.dashboard');
        }

        if ($user->hasRole('admin')) {
            return route('admin.dashboard');
        }

        return '/';
    }
}
