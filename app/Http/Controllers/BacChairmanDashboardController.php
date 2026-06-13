<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;

class BacChairmanDashboardController extends BaseDashboardController
{
    protected function getRoleName(): string
    {
        return UserRole::BAC_CHAIRMAN->value;
    }

    protected function getRoleLabel(): string
    {
        return 'Bids and Awards Committee Chairman';
    }

    protected function getViewName(): string
    {
        return 'bac-chairman/dashboard';
    }

    protected function getEmptyAdditionalData(): array
    {
        return [
            'priorityActions' => [],
        ];
    }
}
