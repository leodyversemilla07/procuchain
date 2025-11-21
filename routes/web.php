<?php

use App\Http\Controllers\AccountLockoutController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BacChairmanController;
use App\Http\Controllers\BacSecretariatController;
use App\Http\Controllers\BlockchainExplorerController;
use App\Http\Controllers\DocumentCorrectionController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\HopeController;
use App\Http\Controllers\LoginLogController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PdfViewerController;
use App\Http\Controllers\Procurement\PostProcurementController;
use App\Http\Controllers\Procurement\PreProcurementController;
use App\Http\Controllers\Procurement\ProcurementController;
use App\Http\Controllers\Procurement\ProcurementInitiationController;
use App\Http\Controllers\ProcurementListController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
|
| Routes accessible without authentication
|
*/

// Marketing Pages
Route::get('/', fn () => Inertia::render('home'))->name('home');
Route::inertia('/about', 'about')->name('about');
Route::inertia('/team', 'team')->name('team');
Route::inertia('/contact', 'contact')->name('contact');

// Public Document Downloads
Route::get('/privacy.pdf', fn () => response()->file(public_path('docs/privacy.pdf')))->name('privacy.policy');
Route::get('/terms.pdf', fn () => response()->file(public_path('docs/terms.pdf')))->name('terms.service');

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
|
| All routes below require authentication
|
*/

Route::middleware(['auth'])->group(function () {
    /*
    |----------------------------------------------------------------------
    | Shared Authenticated Routes
    |----------------------------------------------------------------------
    */

    // Notifications (All Authenticated Users)
    Route::prefix('notifications')->name('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'page']);
        Route::post('/{id}/mark-as-read', [NotificationController::class, 'markAsRead'])->name('.mark-as-read');
        Route::post('/mark-all-as-read', [NotificationController::class, 'markAllAsRead'])->name('.mark-all-as-read');
    });

    // File Downloads (All Authenticated Users)
    Route::get('/files/{fileKey}', [DocumentDownloadController::class, 'downloadFile'])
        ->where('fileKey', '.*')
        ->name('files.download');

    // PDF Viewer (All Authenticated Users)
    Route::get('/pdf-viewer/{fileKey}', [PdfViewerController::class, 'showPdfViewer'])
        ->where('fileKey', '.*')
        ->name('pdf.viewer');

    // Blockchain Status Polling (All Authenticated Users)
    Route::get('/procurements/{pr_number}/blockchain-status', [ProcurementListController::class, 'getBlockchainStatus'])
        ->name('procurements.blockchain-status');

    // Document Corrections - View Only (All Authenticated Users)
    Route::get('/procurements/{pr_number}/corrections', [DocumentCorrectionController::class, 'showCorrectionsPage'])
        ->name('procurements.corrections.page');
    Route::get('/procurements/{procurement}/corrections-history', [DocumentCorrectionController::class, 'getCorrectionHistory'])
        ->name('procurements.corrections');
    Route::get('/corrections/check/{txid}', [DocumentCorrectionController::class, 'checkCorrection'])
        ->name('corrections.check');

    // Document Corrections - Management (Admin, BAC Chairman, BAC Secretariat)
    Route::middleware(['role:admin,bac_chairman,bac_secretariat'])->group(function () {
        Route::post('/documents/{document}/correct', [DocumentCorrectionController::class, 'correctDocument'])
            ->name('documents.correct');
    });

    // Procurement Publishing & Upload Actions (BAC Secretariat only)
    // Rate limited to prevent blockchain node abuse (Issue #18 fix)
    Route::middleware(['role:bac_secretariat', 'throttle:blockchain_writes'])
        ->prefix('bac-secretariat')
        ->name('bac-secretariat.')
        ->group(function () {
            // Procurement Initiation (Stage 1)
            Route::post('/initiate-procurement', [ProcurementInitiationController::class, 'initiate'])
                ->name('procurement.initiate');
            Route::post('/procurement-initiation/{pr_number}/upload-document', [ProcurementInitiationController::class, 'uploadSingleDocument'])
                ->name('procurement.initiation.upload-document');
            Route::post('/procurement-initiation/{pr_number}/complete', [ProcurementInitiationController::class, 'markStageComplete'])
                ->name('procurement.initiation.complete');
            Route::get('/procurement-initiation/{pr_number}/document-guide', [ProcurementInitiationController::class, 'documentGuide'])
                ->name('procurement.initiation.document-guide');
            Route::post('/procurement-initiation/{pr_number}/validate-upload', [ProcurementInitiationController::class, 'validateUpload'])
                ->name('procurement.initiation.validate-upload');

            // Pre-Procurement Phase (Stages 1-3)
            Route::post('/pre-procurement/{pr_number}/{stage}/upload', [PreProcurementController::class, 'uploadDocuments'])
                ->name('procurement.pre-procurement.upload');
            Route::post('/pre-procurement/{pr_number}/{stage}/upload-document', [PreProcurementController::class, 'uploadSingleDocument'])
                ->name('procurement.pre-procurement.upload-document');
            Route::post('/pre-procurement/{pr_number}/{stage}/complete', [PreProcurementController::class, 'markStageComplete'])
                ->name('procurement.pre-procurement.complete');
            Route::post('/publish-pre-procurement-conference-decision', [PreProcurementController::class, 'publishDecision'])
                ->name('publish-pre-procurement-conference-decision');
            Route::post('/publish-pre-bid-conference-decision', [PreProcurementController::class, 'publishPreBidDecision'])
                ->name('publish-pre-bid-conference-decision');
            Route::post('/publish-supplemental-bid-bulletin-decision', [PreProcurementController::class, 'publishSupplementalBidBulletinDecision'])
                ->name('publish-supplemental-bid-bulletin-decision');

            // Procurement Phase (Stages 4-9)
            Route::post('/procurement/{pr_number}/{stage}/upload', [ProcurementController::class, 'uploadDocuments'])
                ->name('procurement.bidding.upload');
            Route::post('/procurement/{pr_number}/{stage}/upload-document', [ProcurementController::class, 'uploadSingleDocument'])
                ->name('procurement.bidding.upload-document');
            Route::post('/procurement/{pr_number}/{stage}/complete', [ProcurementController::class, 'markStageComplete'])
                ->name('procurement.bidding.complete');

            // Post-Procurement Phase (Stages 10-15)
            Route::post('/post-procurement/{pr_number}/{stage}/upload', [PostProcurementController::class, 'uploadDocuments'])
                ->name('procurement.post-procurement.upload');
            Route::post('/post-procurement/{pr_number}/{stage}/upload-document', [PostProcurementController::class, 'uploadSingleDocument'])
                ->name('procurement.post-procurement.upload-document');
            Route::post('/post-procurement/{pr_number}/{stage}/complete', [PostProcurementController::class, 'markStageComplete'])
                ->name('procurement.post-procurement.complete');

            // Legacy route support (for backward compatibility)
            Route::post('/upload-pre-procurement-conference-documents', [PreProcurementController::class, 'uploadDocuments'])
                ->name('upload-pre-procurement-conference-documents');
            Route::post('/upload-bidding-documents', function () {
                abort(410, 'This route is deprecated. Please use the new phase-based routes.');
            })->name('upload-bidding-documents');
        });

    /*
    |----------------------------------------------------------------------
    | BAC Secretariat Routes
    |----------------------------------------------------------------------
    */

    Route::middleware(['role:bac_secretariat'])->prefix('bac-secretariat')->name('bac-secretariat.')->group(function () {
        // Dashboard
        Route::get('/dashboard', [BacSecretariatController::class, 'dashboard'])->name('dashboard');

        // Procurement List Views
        Route::get('/procurements-list', [ProcurementListController::class, 'index'])
            ->name('procurements.index');
        Route::get('/procurements-list/{pr_number}', [ProcurementListController::class, 'show'])
            ->name('procurements.show');

        // Procurement Stage Upload Forms
        Route::get('/procurement-initiation', [ProcurementInitiationController::class, 'index'])
            ->name('procurement.initiation');
        Route::get('/procurement-initiation/create', [ProcurementInitiationController::class, 'show'])
            ->name('procurement.initiation.create');
        Route::get('/procurement-initiation/{pr_number}', [ProcurementInitiationController::class, 'show'])
            ->name('procurement.initiation.show');

        // Pre-Procurement Phase Routes (Stages 1-3)
        Route::get('/pre-procurement/{pr_number}/{stage}', [PreProcurementController::class, 'show'])
            ->name('procurement.pre-procurement.show');
        Route::get('/pre-procurement/{pr_number}/{stage}/document-guide', [PreProcurementController::class, 'documentGuide'])
            ->name('procurement.pre-procurement.document-guide');
        Route::get('/pre-procurement/{pr_number}/{stage}/check-completion', [PreProcurementController::class, 'checkCompletion'])
            ->name('procurement.pre-procurement.check-completion');
        Route::post('/pre-procurement/{pr_number}/{stage}/validate-upload', [PreProcurementController::class, 'validateUpload'])
            ->name('procurement.pre-procurement.validate-upload');

        // Procurement Phase Routes (Stages 4-9)
        Route::get('/procurement/{pr_number}/{stage}', [ProcurementController::class, 'show'])
            ->name('procurement.bidding.show');
        Route::get('/procurement/{pr_number}/{stage}/document-guide', [ProcurementController::class, 'documentGuide'])
            ->name('procurement.bidding.document-guide');
        Route::get('/procurement/{pr_number}/{stage}/check-completion', [ProcurementController::class, 'checkCompletion'])
            ->name('procurement.bidding.check-completion');
        Route::post('/procurement/{pr_number}/{stage}/validate-upload', [ProcurementController::class, 'validateUpload'])
            ->name('procurement.bidding.validate-upload');

        // Post-Procurement Phase Routes (Stages 10-15)
        Route::get('/post-procurement/{pr_number}/{stage}', [PostProcurementController::class, 'show'])
            ->name('procurement.post-procurement.show');
        Route::get('/post-procurement/{pr_number}/{stage}/document-guide', [PostProcurementController::class, 'documentGuide'])
            ->name('procurement.post-procurement.document-guide');
        Route::get('/post-procurement/{pr_number}/{stage}/check-completion', [PostProcurementController::class, 'checkCompletion'])
            ->name('procurement.post-procurement.check-completion');
        Route::post('/post-procurement/{pr_number}/{stage}/validate-upload', [PostProcurementController::class, 'validateUpload'])
            ->name('procurement.post-procurement.validate-upload');
    });

    /*
    |----------------------------------------------------------------------
    | BAC Chairman Routes
    |----------------------------------------------------------------------
    */

    Route::middleware(['role:bac_chairman'])->prefix('bac-chairman')->name('bac-chairman.')->group(function () {
        // Dashboard
        Route::get('/dashboard', [BacChairmanController::class, 'index'])->name('dashboard');

        // Procurement List Views
        Route::get('/procurements-list', [ProcurementListController::class, 'index'])
            ->name('procurements.index');
        Route::get('/procurements-list/{pr_number}', [ProcurementListController::class, 'show'])
            ->name('procurements.show');
    });

    /*
    |----------------------------------------------------------------------
    | HOPE (Head of Procuring Entity) Routes
    |----------------------------------------------------------------------
    */

    Route::middleware(['role:hope'])->prefix('hope')->name('hope.')->group(function () {
        // Dashboard
        Route::get('/dashboard', [HopeController::class, 'index'])->name('dashboard');

        // Procurement List Views
        Route::get('/procurements-list', [ProcurementListController::class, 'index'])
            ->name('procurements.index');
        Route::get('/procurements-list/{pr_number}', [ProcurementListController::class, 'show'])
            ->name('procurements.show');
    });

    /*
    |----------------------------------------------------------------------
    | Admin Routes
    |----------------------------------------------------------------------
    */

    Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
        // Dashboard
        Route::get('/dashboard', [AdminController::class, 'index'])->name('dashboard');

        // Procurement List Views
        Route::get('/procurements-list', [ProcurementListController::class, 'index'])
            ->name('procurements.index');
        Route::get('/procurements-list/{pr_number}', [ProcurementListController::class, 'show'])
            ->name('procurements.show');

        // User Management
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [UserManagementController::class, 'index'])->name('index');
            Route::post('/', [UserManagementController::class, 'store'])->name('store');
            Route::put('/{user}', [UserManagementController::class, 'update'])->name('update');
            Route::delete('/{user}', [UserManagementController::class, 'destroy'])->name('destroy');
            Route::delete('/', [UserManagementController::class, 'bulkDelete'])->name('bulk-delete');
            Route::post('/{user}/reset-password', [UserManagementController::class, 'resetPassword'])->name('reset-password');
        });

        // Login Tracking & Monitoring
        Route::prefix('login-logs')->name('login-logs.')->group(function () {
            Route::get('/', [LoginLogController::class, 'index'])->name('index');
            Route::get('/recent', [LoginLogController::class, 'recent'])->name('recent');
            Route::get('/statistics', [LoginLogController::class, 'statistics'])->name('statistics');
            Route::get('/suspicious', [LoginLogController::class, 'suspicious'])->name('suspicious');
            Route::post('/block-ip', [LoginLogController::class, 'blockIp'])->name('block-ip');
            Route::post('/unblock-ip', [LoginLogController::class, 'unblockIp'])->name('unblock-ip');
            Route::get('/blocked-ips', [LoginLogController::class, 'blockedIps'])->name('blocked-ips');
        });

        // Account Management & Security
        Route::prefix('accounts')->name('accounts.')->group(function () {
            Route::get('/locked', [AccountLockoutController::class, 'index'])->name('locked');
            Route::post('/{user}/unlock', [AccountLockoutController::class, 'unlock'])->name('unlock');
            Route::post('/{user}/lock', [AccountLockoutController::class, 'lock'])->name('lock');
            Route::post('/{user}/reset-attempts', [AccountLockoutController::class, 'resetAttempts'])->name('reset-attempts');
            Route::post('/bulk-unlock', [AccountLockoutController::class, 'bulkUnlock'])->name('bulk-unlock');
            Route::post('/bulk-reset-attempts', [AccountLockoutController::class, 'bulkResetAttempts'])->name('bulk-reset-attempts');
        });

        // Blockchain Explorer (includes health monitoring)
        Route::prefix('blockchain-explorer')->name('blockchain.explorer.')->group(function () {
            Route::get('/', [BlockchainExplorerController::class, 'index'])->name('index');
            Route::get('/block', [BlockchainExplorerController::class, 'getBlock'])->name('block');
            Route::get('/transaction', [BlockchainExplorerController::class, 'getTransaction'])->name('transaction');
            Route::get('/stream/{streamName}/items', [BlockchainExplorerController::class, 'getStreamItems'])->name('stream.items');
            Route::get('/address/{address}', [BlockchainExplorerController::class, 'getAddress'])->name('address');
            Route::get('/search', [BlockchainExplorerController::class, 'search'])->name('search');
            Route::post('/reset-circuit-breaker', [BlockchainExplorerController::class, 'resetCircuitBreaker'])->name('reset');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Authentication & Settings Routes
|--------------------------------------------------------------------------
*/

require __DIR__.'/auth.php';
require __DIR__.'/settings.php';

/*
|--------------------------------------------------------------------------
| Development Only Routes
|--------------------------------------------------------------------------
*/

if (app()->environment(['local', 'development'])) {
    require __DIR__.'/file-uploads-ui-preview.php';
}
