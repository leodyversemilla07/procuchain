<?php

namespace App\Http\Controllers;

use App\Enums\StreamEnums;
use App\Models\User;
use App\Services\ProcurementServices;
use Exception;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class HopeController extends BaseController
{
    private $services;

    public function __construct(ProcurementServices $services)
    {
        $this->services = $services;
        $this->middleware('auth');
        $this->middleware('role:hope');
    }

    public function index()
    {
        return Inertia::render('hope/dashboard');
    }
}
