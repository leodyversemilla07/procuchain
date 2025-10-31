<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Get the dashboard route for a given user based on their role.
     */
    protected function redirectToDashboard(?User $user = null): string
    {
        $user = $user ?? Auth::user();

        if (! $user) {
            return '/';
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
