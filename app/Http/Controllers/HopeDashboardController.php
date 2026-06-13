<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Support\Collection;

class HopeDashboardController extends BaseDashboardController
{
    protected function getRoleName(): string
    {
        return UserRole::HOPE->value;
    }

    protected function getRoleLabel(): string
    {
        return 'Head of Procuring Entity';
    }

    protected function getViewName(): string
    {
        return 'hope/dashboard';
    }

    protected function getAdditionalDashboardData(Collection $procurementsByKey, string $roleName): array
    {
        return [];
    }

    protected function getEmptyAdditionalData(): array
    {
        return [];
    }
}
