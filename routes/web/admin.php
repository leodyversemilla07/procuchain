<?php

use App\Http\Controllers\AccountLockoutController;
use App\Http\Controllers\Admin\IntegrityBreachController;
use App\Http\Controllers\Admin\ProcurementWorkflowConfigController;
use App\Http\Controllers\Admin\StageDocumentConfigController;
use App\Http\Controllers\Admin\UserInvitationController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BlockchainExplorerController;
use App\Http\Controllers\BlockchainNodeController;
use App\Http\Controllers\LoginHistoryController;
use App\Http\Controllers\ProcurementListController;
use App\Http\Controllers\SharedLedgerController;
use App\Http\Controllers\UserManagementController;
use App\Services\BlockchainRecordSyncService;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->where(['pr_number' => 'PR-\d{4}-\d{3}(-\d{4})?', 'user' => '[0-9]+'])->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Full blockchain sync
    Route::post('/sync-blockchain', function () {
        $syncService = app(BlockchainRecordSyncService::class);
        $counts = $syncService->syncAll();

        return response()->json(['success' => true, 'synced' => $counts]);
    })->middleware('throttle:5,1')->name('sync-blockchain');

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
        Route::get('/', [LoginHistoryController::class, 'index'])->name('index');
        Route::get('/recent', [LoginHistoryController::class, 'recent'])->name('recent');
        Route::get('/statistics', [LoginHistoryController::class, 'statistics'])->name('statistics');
        Route::get('/suspicious', [LoginHistoryController::class, 'suspicious'])->name('suspicious');
        Route::post('/block-ip', [LoginHistoryController::class, 'blockIp'])->name('block-ip');
        Route::post('/unblock-ip', [LoginHistoryController::class, 'unblockIp'])->name('unblock-ip');
        Route::get('/blocked-ips', [LoginHistoryController::class, 'blockedIps'])->name('blocked-ips');
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
        Route::get('/', [BlockchainNodeController::class, 'index'])->name('index');
        Route::get('/data', [BlockchainNodeController::class, 'data'])->name('data');
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

    // Integrity Breaches — mirror breach management
    Route::prefix('integrity-breaches')->name('integrity-breaches.')->group(function () {
        Route::get('/', [IntegrityBreachController::class, 'index'])->name('index');
        Route::post('/repair-pr', [IntegrityBreachController::class, 'repairPr'])->middleware('throttle:5,1')->name('repair-pr');
        Route::post('/verify', [IntegrityBreachController::class, 'verify'])->middleware('throttle:5,1')->name('verify');
        Route::post('/verify-and-repair', [IntegrityBreachController::class, 'verifyAndRepair'])->middleware('throttle:5,1')->name('verify-and-repair');
        Route::get('/verify-status', [IntegrityBreachController::class, 'verifyStatus'])->name('verify-status');
        Route::get('/mirror-status', [IntegrityBreachController::class, 'mirrorStatus'])->name('mirror-status');
        Route::get('/{id}', [IntegrityBreachController::class, 'show'])->name('show')->whereNumber('id');
        Route::post('/{id}/repair', [IntegrityBreachController::class, 'repair'])->middleware('throttle:10,1')->name('repair');
    });

    // Integrity Audit Logs — permanent forensic record
    Route::prefix('integrity-audit-logs')->name('integrity-audit-logs.')->group(function () {
        Route::get('/', [IntegrityBreachController::class, 'auditLogsPage'])->name('index');
        Route::get('/detail/{id}', [IntegrityBreachController::class, 'auditLogDetailPage'])->name('detail');
        Route::get('/report/{runId}', [IntegrityBreachController::class, 'verificationReportPage'])->name('report');
        Route::post('/{id}/repair', [IntegrityBreachController::class, 'auditLogsRepair'])->middleware('throttle:10,1')->name('repair');
    });

    // Breach Detail Page
    Route::get('/integrity-breaches/detail/{id}', [IntegrityBreachController::class, 'show'])->name('integrity-breaches.detail');

});
