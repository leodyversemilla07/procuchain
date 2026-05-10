<?php

use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// Role-based report routes
foreach (['admin', 'bac_chairman', 'bac_secretariat', 'hope'] as $role) {
    $prefix = match ($role) {
        'admin' => 'admin',
        'bac_chairman' => 'bac-chairman',
        'bac_secretariat' => 'bac-secretariat',
        'hope' => 'hope',
    };

    Route::middleware(['auth', "role:{$role}"])->prefix($prefix)->name("{$role}.")->group(function () {
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [ReportController::class, 'index'])->name('index');
            Route::post('/generate', [ReportController::class, 'generate'])->name('generate');
            Route::post('/export', [ReportController::class, 'export'])->name('export');
        });
    });
}
