<?php

use App\Http\Controllers\BacChairmanController;
use App\Http\Controllers\HopeController;
use App\Http\Controllers\ProcurementListController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:bac_chairman'])->prefix('bac-chairman')->name('bac-chairman.')->group(function () {
    Route::get('/dashboard', [BacChairmanController::class, 'index'])->name('dashboard');

    Route::get('/procurements-list', [ProcurementListController::class, 'index'])
        ->name('procurements.index');
    Route::get('/procurements-list/{pr_number}', [ProcurementListController::class, 'show'])
        ->name('procurements.show');
});

Route::middleware(['auth', 'role:hope'])->prefix('hope')->name('hope.')->group(function () {
    Route::get('/dashboard', [HopeController::class, 'index'])->name('dashboard');

    Route::get('/procurements-list', [ProcurementListController::class, 'index'])
        ->name('procurements.index');
    Route::get('/procurements-list/{pr_number}', [ProcurementListController::class, 'show'])
        ->name('procurements.show');
});
