<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\BacSecretariatController;
use App\Http\Controllers\BacChairmanController;
use App\Http\Controllers\HopeController;
use App\Http\Controllers\PrGeneratorController;
use App\Http\Controllers\ProcurementController;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::get('generate-pr-show', [PrGeneratorController::class, 'index'])
    ->name('generate-pr.index');

Route::post('generate-pr-store', [PrGeneratorController::class, 'store'])
    ->name('generate-pr.store');


Route::middleware(['auth', 'verified'])->group(function () {

    Route::middleware(['role:bac_secretariat'])->group(function () {
        Route::get('bac-secretariat/dashboard', [BacSecretariatController::class, 'index'])
            ->name('bac-secretariat.dashboard');

        Route::get('bac-secretariat/procurement/pr-initiation', [BacSecretariatController::class, 'prInitiation'])
            ->name('bac-secretariat.procurement.pr-initiation');

        Route::post('bac-secretariat/publish-pr-initiation', [ProcurementController::class, 'publishPrInitiation'])
            ->name('publish-pr-initiation');

        Route::get('bac-secretariat/procurements-list', [ProcurementController::class, 'index'])
            ->name('bac-secretariat.procurements-list.index');

        Route::get('bac-secretariat/procurements-list/{id}', [ProcurementController::class, 'show'])
            ->name('bac-secretariat.procurements.show');
    });

    Route::middleware(['role:bac_chairman'])->group(function () {
        Route::get('bac-chairman/dashboard', [BacChairmanController::class, 'index'])
            ->name('bac-chairman.dashboard');


    });

    Route::middleware(['role:hope'])->group(function () {
        Route::get('hope/dashboard', [HopeController::class, 'index'])
            ->name('hope.dashboard');


    });

});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
