<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    /**
     * Get the dashboard route for a given user based on their role.
     */
    protected function redirectToDashboard(?User $user = null): string
    {
        $user = $user ?? Auth::user();

        if (! $user) {
            return '/';
        }

        return match ($user->role) {
            'bac_secretariat' => route('bac-secretariat.dashboard'),
            'bac_chairman' => route('bac-chairman.dashboard'),
            'hope' => route('hope.dashboard'),
            'admin' => route('admin.dashboard'),
            default => '/',
        };
    }
}
