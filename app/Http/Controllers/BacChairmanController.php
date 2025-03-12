<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;

class BacChairmanController extends BaseController
{
    public function __construct()
    {
        $this->middleware('role:bac_chairman');
    }

    public function index()
    {
        return Inertia::render('bac-chairman/dashboard');
    }
}
