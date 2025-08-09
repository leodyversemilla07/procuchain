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
    
    // Analytics Data Endpoints (for AJAX requests from dashboard)
    Route::prefix('analytics')->name('analytics.')->group(function () {
        
        // Procurement Analytics Data
        Route::get('/procurement-data', [AnalyticsController::class, 'procurementAnalytics'])
            ->name('procurement.data');
        
        // Document Analytics Data
        Route::get('/documents-data', [AnalyticsController::class, 'documentAnalytics'])
            ->name('documents.data');
        
        // User Activity Analytics Data
        Route::get('/user-activity-data', [AnalyticsController::class, 'userActivityAnalytics'])
            ->name('user-activity.data');
        
        // Blockchain Analytics Data
        Route::get('/blockchain-data', [AnalyticsController::class, 'blockchainAnalytics'])
            ->name('blockchain.data');
        
        // Export Data
        Route::post('/export', [AnalyticsController::class, 'exportData'])
            ->name('export');
        
        // Download Exported File
        Route::get('/download/{filename}', [AnalyticsController::class, 'downloadExport'])
            ->name('download');
    });
    
    // Role-specific Analytics Routes
    // Removed duplicate routes pointing to the same dashboard method.
    // The main /analytics route should handle role-based filtering within the controller.
});
