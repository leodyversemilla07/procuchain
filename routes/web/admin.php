<?php

use App\Http\Controllers\AccountLockoutController;
use App\Http\Controllers\Admin\ProcurementWorkflowConfigController;
use App\Http\Controllers\Admin\StageDocumentConfigController;
use App\Http\Controllers\Admin\UserInvitationController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BlockchainExplorerController;
use App\Http\Controllers\LoginLogController;
use App\Http\Controllers\NodeNetworkController;
use App\Http\Controllers\ProcurementListController;
use App\Http\Controllers\RecoverableDataController;
use App\Http\Controllers\SharedLedgerController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->where(['pr_number' => 'PR-\d{4}-\d{3}(-\d{4})?', 'user' => '[0-9]+'])->group(function () {
    Route::get('/dashboard', [AdminController::class, 'index'])->name('dashboard');

    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');

    Route::get('/procurements-list', [ProcurementListController::class, 'index'])
        ->name('procurements.index');
    Route::get('/procurements-list/{pr_number}', [ProcurementListController::class, 'show'])
        ->name('procurements.show');

    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])->name('index');
        Route::post('/', [UserManagementController::class, 'store'])->name('store');
        Route::put('/{user}', [UserManagementController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserManagementController::class, 'destroy'])->name('destroy');
        Route::delete('/', [UserManagementController::class, 'bulkDelete'])->name('bulk-delete');
        Route::post('/{user}/reset-password', [UserManagementController::class, 'resetPassword'])->name('reset-password');
    });

    Route::prefix('invitations')->name('invitations.')->group(function () {
        Route::get('/', [UserInvitationController::class, 'index'])->name('index');
        Route::post('/', [UserInvitationController::class, 'store'])->name('store');
        Route::post('/{invitation}/resend', [UserInvitationController::class, 'resend'])->name('resend');
        Route::delete('/{invitation}', [UserInvitationController::class, 'destroy'])->name('revoke');
    });

    Route::prefix('login-logs')->name('login-logs.')->group(function () {
        Route::get('/', [LoginLogController::class, 'index'])->name('index');
        Route::get('/recent', [LoginLogController::class, 'recent'])->name('recent');
        Route::get('/statistics', [LoginLogController::class, 'statistics'])->name('statistics');
        Route::get('/suspicious', [LoginLogController::class, 'suspicious'])->name('suspicious');
        Route::post('/block-ip', [LoginLogController::class, 'blockIp'])->name('block-ip');
        Route::post('/unblock-ip', [LoginLogController::class, 'unblockIp'])->name('unblock-ip');
        Route::get('/blocked-ips', [LoginLogController::class, 'blockedIps'])->name('blocked-ips');
    });

    Route::prefix('accounts')->name('accounts.')->group(function () {
        Route::get('/locked', [AccountLockoutController::class, 'index'])->name('locked');
        Route::post('/{user}/unlock', [AccountLockoutController::class, 'unlock'])->name('unlock');
        Route::post('/{user}/lock', [AccountLockoutController::class, 'lock'])->name('lock');
        Route::post('/{user}/reset-attempts', [AccountLockoutController::class, 'resetAttempts'])->name('reset-attempts');
        Route::post('/bulk-unlock', [AccountLockoutController::class, 'bulkUnlock'])->name('bulk-unlock');
        Route::post('/bulk-reset-attempts', [AccountLockoutController::class, 'bulkResetAttempts'])->name('bulk-reset-attempts');
    });

    Route::prefix('blockchain-explorer')->name('blockchain.explorer.')->group(function () {
        Route::get('/', [BlockchainExplorerController::class, 'index'])->name('index');
        Route::get('/block', [BlockchainExplorerController::class, 'getBlock'])->name('block');
        Route::get('/transaction', [BlockchainExplorerController::class, 'getTransaction'])->name('transaction');
        Route::get('/stream/{streamName}/items', [BlockchainExplorerController::class, 'getStreamItems'])->name('stream.items');
        Route::get('/address/{address}', [BlockchainExplorerController::class, 'getAddress'])->name('address');
        Route::get('/search', [BlockchainExplorerController::class, 'search'])->name('search');
        Route::post('/reset-circuit-breaker', [BlockchainExplorerController::class, 'resetCircuitBreaker'])->name('reset');
    });

    Route::prefix('network')->name('network.')->group(function () {
        Route::get('/', [NodeNetworkController::class, 'index'])->name('index');
        Route::get('/data', [NodeNetworkController::class, 'data'])->name('data');
    });

    Route::prefix('workflow-config')->name('workflow-config.')->group(function () {
        Route::get('/', [ProcurementWorkflowConfigController::class, 'index'])->name('index');
        Route::get('/{mode}/edit', [ProcurementWorkflowConfigController::class, 'edit'])->name('edit');
        Route::get('/{mode}/preview', [ProcurementWorkflowConfigController::class, 'preview'])->name('preview');
        Route::put('/{mode}', [ProcurementWorkflowConfigController::class, 'update'])->name('update');
        Route::post('/{mode}/reset', [ProcurementWorkflowConfigController::class, 'resetToDefaults'])->name('reset');
    });

    Route::prefix('stage-documents')->name('stage-documents.')->group(function () {
        Route::get('/', [StageDocumentConfigController::class, 'index'])->name('index');
        Route::get('/{mode}/{stage}/edit', [StageDocumentConfigController::class, 'edit'])->name('edit');
        Route::put('/{mode}/{stage}', [StageDocumentConfigController::class, 'update'])->name('update');
        Route::post('/{mode}/{stage}/reset', [StageDocumentConfigController::class, 'resetToDefaults'])->name('reset');
    });

    // Shared Ledger
    Route::get('/shared-ledger', [SharedLedgerController::class, 'index'])->name('shared-ledger');

    // Recoverable Data — blockchain recovery management
    Route::prefix('recoverable-data')->name('recoverable-data.')->group(function () {
        Route::get('/', [RecoverableDataController::class, 'index'])->name('index');
        Route::post('/restore', [RecoverableDataController::class, 'restore'])->name('restore');
        Route::post('/delete-from-node', [RecoverableDataController::class, 'deleteFromNode'])->name('delete-from-node');
        Route::post('/purge-all-from-node', [RecoverableDataController::class, 'purgeAllFromNode'])->name('purge-all-from-node');
        Route::post('/resync-node', [RecoverableDataController::class, 'resyncNode'])->name('resync-node');
    });
});
