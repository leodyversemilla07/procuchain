<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;

class BacSecretariatController extends BaseController
{
    public function __construct()
    {
        $this->middleware('role:bac_secretariat');
    }

    public function index()
    {
        return Inertia::render('bac-secretariat/dashboard');
    }

    public function prInitiation()
    {
        return Inertia::render('bac-secretariat/pr-initiation');
    }
}
