<?php

namespace App\Http\Controllers;

class BacChairmanDashboardController extends BaseDashboardController
{
    protected function getRoleName(): string
    {
        return 'bac_chairman';
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
