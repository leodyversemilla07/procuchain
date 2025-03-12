<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;

class HopeController extends BaseController
{
    public function __construct()
    {
        $this->middleware('role:hope');
    }

    public function index()
    {
        return Inertia::render('hope/dashboard');
    }
}
