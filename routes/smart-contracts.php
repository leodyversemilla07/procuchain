<?php

use App\Http\Controllers\SmartContractController;
use Illuminate\Support\Facades\Route;

// Document Management Smart Contract Routes
Route::middleware(['auth', 'verified'])->prefix('smart-contracts')->name('smart-contracts.')->group(function () {
    
    // Initialize smart contract system (admin only)
    Route::post('/initialize', [SmartContractController::class, 'initialize'])
        ->middleware('role:admin')
        ->name('initialize');
    
    // Get smart contract status
    Route::get('/status', [SmartContractController::class, 'getStatus'])
        ->name('status');
    
    // Document integrity validation
    Route::post('/validate-integrity', [SmartContractController::class, 'validateDocumentIntegrity'])
        ->middleware('role:bac_secretariat,bac_chairman,hope')
        ->name('validate-integrity');
    
    // Document metadata compliance checking
    Route::post('/check-compliance', [SmartContractController::class, 'checkMetadataCompliance'])
        ->middleware('role:bac_secretariat,bac_chairman')
        ->name('check-compliance');
    
    // Document storage consistency validation
    Route::post('/validate-storage', [SmartContractController::class, 'validateStorageConsistency'])
        ->middleware('role:bac_secretariat,bac_chairman,admin')
        ->name('validate-storage');
    
    // Get document audit trail
    Route::get('/audit-trail/{procurement_id}', [SmartContractController::class, 'getAuditTrail'])
        ->middleware('role:bac_secretariat,bac_chairman,hope,admin')
        ->name('audit-trail');
});
