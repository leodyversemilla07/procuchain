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
use App\Http\Controllers\Procurement\BacResolutionController;
use App\Http\Controllers\Procurement\BiddingDocumentsController;
use App\Http\Controllers\Procurement\BidEvaluationController;
use App\Http\Controllers\Procurement\BidOpeningController;
use App\Http\Controllers\Procurement\CompletionController;
use App\Http\Controllers\Procurement\MonitoringController;
use App\Http\Controllers\Procurement\NoticeOfAwardController;
use App\Http\Controllers\Procurement\NoticeToProceedController;
use App\Http\Controllers\Procurement\PerformanceBondContractPoController;
use App\Http\Controllers\Procurement\PostQualificationController;
use App\Http\Controllers\Procurement\PreBidConferenceController;
use App\Http\Controllers\Procurement\PreProcurementConferenceController;
use App\Http\Controllers\Procurement\ProcurementInitiationController;
use App\Http\Controllers\Procurement\SupplementalBidBulletinController;
use App\Http\Controllers\ProcurementListController;
use App\Http\Controllers\SearchController;
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

// Public Search
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/search/suggestions', [SearchController::class, 'suggestions'])->name('search.suggestions');

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
    Route::get('/procurements/{id}/blockchain-status', [ProcurementListController::class, 'getBlockchainStatus'])
        ->name('procurements.blockchain-status');

    // Document Corrections - View Only (All Authenticated Users)
    Route::get('/procurements/{id}/corrections', [DocumentCorrectionController::class, 'showCorrectionsPage'])
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
    Route::middleware(['role:bac_secretariat'])->prefix('bac-secretariat')->name('bac-secretariat.')->group(function () {
        Route::post('/publish-procurement-initiation', [ProcurementInitiationController::class, 'publish'])
            ->name('publish-procurement-initiation');
        Route::post('/publish-pre-procurement-conference-decision', [PreProcurementConferenceController::class, 'publishDecision'])
            ->name('publish-pre-procurement-conference-decision');
        Route::post('/upload-pre-procurement-conference-documents', [PreProcurementConferenceController::class, 'uploadDocuments'])
            ->name('upload-pre-procurement-conference-documents');
        Route::post('/publish-pre-bid-conference-decision', [PreBidConferenceController::class, 'publishDecision'])
            ->name('publish-pre-bid-conference-decision');
        Route::post('/upload-pre-bid-conference-documents', [PreBidConferenceController::class, 'uploadDocuments'])
            ->name('upload-pre-bid-conference-documents');
        Route::post('/publish-supplemental-bid-bulletin-decision', [SupplementalBidBulletinController::class, 'publishDecision'])
            ->name('publish-supplemental-bid-bulletin-decision');
        Route::post('/upload-supplemental-bid-bulletin-documents', [SupplementalBidBulletinController::class, 'uploadDocuments'])
            ->name('upload-supplemental-bid-bulletin-documents');
        Route::post('/upload-bidding-documents', [BiddingDocumentsController::class, 'upload'])
            ->name('upload-bidding-documents');
        Route::post('/upload-bid-opening-documents', [BidOpeningController::class, 'uploadDocuments'])
            ->name('upload-bid-opening-documents');
        Route::post('/upload-bid-evaluation-documents', [BidEvaluationController::class, 'uploadDocuments'])
            ->name('upload-bid-evaluation-documents');
        Route::post('/upload-post-qualification-documents', [PostQualificationController::class, 'uploadDocuments'])
            ->name('upload-post-qualification-documents');
        Route::post('/upload-bac-resolution-document', [BacResolutionController::class, 'uploadDocument'])
            ->name('upload-bac-resolution-document');
        Route::post('/upload-noa-document', [NoticeOfAwardController::class, 'uploadDocument'])
            ->name('upload-noa-document');
        Route::post('/upload-performance-bond-contract-po-documents', [PerformanceBondContractPoController::class, 'uploadDocuments'])
            ->name('upload-performance-bond-contract-po-documents');
        Route::post('/upload-ntp-document', [NoticeToProceedController::class, 'uploadDocument'])
            ->name('upload-ntp-document');
        Route::post('/upload-monitoring-document', [MonitoringController::class, 'uploadDocument'])
            ->name('upload-monitoring-document');
        Route::post('/upload-completion-documents', [CompletionController::class, 'uploadDocuments'])
            ->name('upload-completion-documents');
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
        Route::get('/procurements-list', [ProcurementListController::class, 'indexProcurementsList'])
            ->name('procurements.index');
        Route::get('/procurements-list/{id}', [ProcurementListController::class, 'showProcurement'])
            ->name('procurements.show');

        // Procurement Stage Upload Forms
        Route::get('/procurement-initiation', [ProcurementInitiationController::class, 'show'])
            ->name('procurement.initiation');
        Route::get('/pre-procurement-conference-upload/{id}', [PreProcurementConferenceController::class, 'show'])
            ->name('procurement.pre-procurement-conference-upload');
        Route::get('/pre-bid-conference-upload/{id}', [PreBidConferenceController::class, 'show'])
            ->name('procurement.pre-bid-conference-upload');
        Route::get('/bidding-documents-upload/{id}', [BiddingDocumentsController::class, 'show'])
            ->name('procurement.bidding-documents-upload');
        Route::get('/supplemental-bid-bulletin-upload/{id}', [SupplementalBidBulletinController::class, 'show'])
            ->name('procurement.supplemental-bid-bulletin-upload');
        Route::get('/bid-opening-upload/{id}', [BidOpeningController::class, 'show'])
            ->name('procurement.bid-opening-upload');
        Route::get('/bid-evaluation-upload/{id}', [BidEvaluationController::class, 'show'])
            ->name('procurement.bid-evaluation-upload');
        Route::get('/post-qualification-upload/{id}', [PostQualificationController::class, 'show'])
            ->name('procurement.post-qualification-upload');
        Route::get('/bac-resolution-upload/{id}', [BacResolutionController::class, 'show'])
            ->name('procurement.bac-resolution-upload');
        Route::get('/noa-upload/{id}', [NoticeOfAwardController::class, 'show'])
            ->name('procurement.noa-upload');
        Route::get('/performance-bond-contract-po-upload/{id}', [PerformanceBondContractPoController::class, 'show'])
            ->name('procurement.performance-bond-contract-po-upload');
        Route::get('/ntp-upload/{id}', [NoticeToProceedController::class, 'show'])
            ->name('procurement.ntp-upload');
        Route::get('/monitoring-upload/{id}', [MonitoringController::class, 'show'])
            ->name('procurement.monitoring-upload');
        Route::get('/completion-upload/{id}', [CompletionController::class, 'show'])
            ->name('procurement.completion-upload');

        // Blockchain Publishing Status Page
        Route::get('/blockchain/publishing-status/{id}', function (string $id) {
            // Fetch procurement data from blockchain instead of database
            // For now, just accept the procurement ID from the route

            return Inertia::render('blockchain-publishing-status', [
                'procurement' => [
                    'id' => $id,
                    'title' => 'Procurement '.$id, // Will be fetched from blockchain in future
                ],
                'stage' => request('stage', 'Document Upload'),
                'returnUrl' => request('return_url'),
            ]);
        })->name('blockchain.publishing-status');
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
        Route::get('/procurements-list', [ProcurementListController::class, 'indexProcurementsList'])
            ->name('procurements.index');
        Route::get('/procurements-list/{id}', [ProcurementListController::class, 'showProcurement'])
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
        Route::get('/procurements-list', [ProcurementListController::class, 'indexProcurementsList'])
            ->name('procurements.index');
        Route::get('/procurements-list/{id}', [ProcurementListController::class, 'showProcurement'])
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
        Route::get('/procurements-list', [ProcurementListController::class, 'indexProcurementsList'])
            ->name('procurements.index');
        Route::get('/procurements-list/{id}', [ProcurementListController::class, 'showProcurement'])
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
