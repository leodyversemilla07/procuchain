<?php

use App\Http\Controllers\BacChairmanDashboardController;
use App\Http\Controllers\HopeDashboardController;
use App\Http\Controllers\ProcurementListController;
use App\Http\Controllers\SharedLedgerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:bac_chairman'])
    ->prefix('bac-chairman')
    ->name('bac-chairman.')
    ->where(['pr_number' => 'PR-\d{4}-\d{3}(-\d{4})?'])
    ->group(function () {
        Route::get('/dashboard', [BacChairmanDashboardController::class, 'index'])->name('dashboard');

        Route::get('/procurements-list', [ProcurementListController::class, 'index'])
            ->name('procurements.index');
        Route::get('/procurements-list/{pr_number}', [ProcurementListController::class, 'show'])
            ->name('procurements.show');

        // Shared Ledger
        Route::get('/shared-ledger', [SharedLedgerController::class, 'index'])->name('shared-ledger');
    });

Route::middleware(['auth', 'role:hope'])
    ->prefix('hope')
    ->name('hope.')
    ->where(['pr_number' => 'PR-\d{4}-\d{3}(-\d{4})?'])
    ->group(function () {
        Route::get('/dashboard', [HopeDashboardController::class, 'index'])->name('dashboard');

        Route::get('/procurements-list', [ProcurementListController::class, 'index'])
            ->name('procurements.index');
        Route::get('/procurements-list/{pr_number}', [ProcurementListController::class, 'show'])
            ->name('procurements.show');

        // Shared Ledger
        Route::get('/shared-ledger', [SharedLedgerController::class, 'index'])->name('shared-ledger');
    });
