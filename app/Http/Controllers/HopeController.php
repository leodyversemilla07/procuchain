<?php

namespace App\Http\Controllers;

use Illuminate\Support\Collection;

class HopeController extends BaseDashboardController
{
    protected function getRoleName(): string
    {
        return 'hope';
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
