<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    protected function redirectToDashboard(Request $request, ?User $user = null): string
    {
        $user = $user ?? $request->user();

        if (! $user) {
            return '/';
        }

        if (! $user->relationLoaded('roles')) {
            $user->load('roles');
        }

        if ($user->hasRole(UserRole::BAC_SECRETARIAT->value)) {
            return route('bac-secretariat.dashboard');
        }

        if ($user->hasRole(UserRole::BAC_CHAIRMAN->value)) {
            return route('bac-chairman.dashboard');
        }

        if ($user->hasRole(UserRole::HOPE->value)) {
            return route('hope.dashboard');
        }

        if ($user->hasRole(UserRole::ADMIN->value)) {
            return route('admin.dashboard');
        }

        return '/';
    }
}
