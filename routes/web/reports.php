<?php

use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin|bac_secretariat|bac_chairman|hope'])->group(function () {
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::post('/generate', [ReportController::class, 'generate'])->name('generate');
        Route::post('/export', [ReportController::class, 'export'])->name('export');
    });

    Route::post('/search', [ReportController::class, 'search'])->name('search');
});
