<?php

namespace App\Http\Controllers;

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
}
