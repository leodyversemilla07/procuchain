<?php

namespace App\Http\Controllers;

use App\Enums\StreamEnums;
use App\Models\User;
use App\Services\ProcurementServices;
use Exception;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class BacChairmanController extends BaseController
{
    private $services;

    public function __construct(ProcurementServices $services)
    {
        $this->services = $services;
        $this->middleware('auth');
        $this->middleware('role:bac_chairman');
    }

    public function index()
    {
        return Inertia::render('bac-chairman/dashboard');
    }
}
