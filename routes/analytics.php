<?php

use App\Http\Controllers\AnalyticsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Analytics Routes
|--------------------------------------------------------------------------
|
| Here are the routes for data analytics functionality within ProcuChain.
| These routes handle analytics dashboards, API endpoints, and data exports.
|
*/

Route::middleware(['auth', 'role:bac_secretariat,bac_chairman,hope,admin'])->group(function () {
    
    // Analytics Dashboard
    Route::get('/analytics', [AnalyticsController::class, 'dashboard'])
        ->name('analytics.dashboard');
    
    // (Pure Inertia) Removed separate JSON endpoints to rely on partial reloads & deferred props.
    
    // Role-specific Analytics Routes
    // Removed duplicate routes pointing to the same dashboard method.
    // The main /analytics route should handle role-based filtering within the controller.
});
