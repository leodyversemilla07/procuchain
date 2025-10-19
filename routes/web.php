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
use App\Http\Controllers\ProcurementController;
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

    // Secure File Downloads (All Authenticated Users)
    Route::get('/secure-file/{fileKey}', [DocumentDownloadController::class, 'downloadFile'])
        ->where('fileKey', '.*')
        ->name('secure.file.download');

    // PDF Viewer (All Authenticated Users)
    Route::get('/pdf-viewer/{fileKey}', [PdfViewerController::class, 'showPdfViewer'])
        ->where('fileKey', '.*')
        ->name('pdf.viewer');

    // Document Corrections - View Only (All Authenticated Users)
    Route::get('/procurements/{id}/corrections', [DocumentCorrectionController::class, 'showCorrectionsPage'])
        ->name('procurements.corrections.page');

    // Document Corrections - Management (Admin, BAC Chairman, BAC Secretariat)
    Route::middleware(['role:admin,bac_chairman,bac_secretariat'])->group(function () {
        Route::post('/documents/{document}/correct', [DocumentCorrectionController::class, 'correctDocument'])
            ->name('documents.correct');
        Route::get('/procurements/{procurement}/corrections', [DocumentCorrectionController::class, 'getCorrectionHistory'])
            ->name('procurements.corrections');
        Route::get('/corrections/check/{txid}', [DocumentCorrectionController::class, 'checkCorrection'])
            ->name('corrections.check');
    });

    // Procurement Publishing & Upload Actions (BAC Secretariat only, but route names have NO prefix)
    Route::middleware(['role:bac_secretariat'])->group(function () {
        Route::post('/bac-secretariat/publish-procurement-initiation', [ProcurementController::class, 'publishProcurementInitiation'])
            ->name('publish-procurement-initiation');
        Route::post('/bac-secretariat/publish-pre-procurement-conference-decision', [ProcurementController::class, 'publishPreProcurementConferenceDecision'])
            ->name('bac-secretariat.publish-pre-procurement-conference-decision');
        Route::post('/bac-secretariat/upload-pre-procurement-conference-documents', [ProcurementController::class, 'uploadPreProcurementConferenceDocuments'])
            ->name('bac-secretariat.upload-pre-procurement-conference-documents');
        Route::post('/bac-secretariat/publish-pre-bid-conference-decision', [ProcurementController::class, 'publishPreBidConferenceDecision'])
            ->name('bac-secretariat.publish-pre-bid-conference-decision');
        Route::post('/bac-secretariat/upload-pre-bid-conference-documents', [ProcurementController::class, 'uploadPreBidConferenceDocuments'])
            ->name('bac-secretariat.upload-pre-bid-conference-documents');
        Route::post('/bac-secretariat/publish-supplemental-bid-bulletin-decision', [ProcurementController::class, 'publishSupplementalBidBulletinDecision'])
            ->name('bac-secretariat.publish-supplemental-bid-bulletin-decision');
        Route::post('/bac-secretariat/upload-supplemental-bid-bulletin-documents', [ProcurementController::class, 'uploadSupplementalBidBulletinDocuments'])
            ->name('bac-secretariat.upload-supplemental-bid-bulletin-documents');
        Route::post('/bac-secretariat/upload-bidding-documents', [ProcurementController::class, 'uploadBiddingDocuments'])
            ->name('bac-secretariat.upload-bidding-documents');
        Route::post('/bac-secretariat/upload-bid-opening-documents', [ProcurementController::class, 'uploadBidOpeningDocuments'])
            ->name('bac-secretariat.upload-bid-opening-documents');
        Route::post('/bac-secretariat/upload-bid-evaluation-documents', [ProcurementController::class, 'uploadBidEvaluationDocuments'])
            ->name('bac-secretariat.upload-bid-evaluation-documents');
        Route::post('/bac-secretariat/upload-post-qualification-documents', [ProcurementController::class, 'uploadPostQualificationDocuments'])
            ->name('bac-secretariat.upload-post-qualification-documents');
        Route::post('/bac-secretariat/upload-bac-resolution-document', [ProcurementController::class, 'uploadBacResolutionDocument'])
            ->name('bac-secretariat.upload-bac-resolution-document');
        Route::post('/bac-secretariat/upload-noa-document', [ProcurementController::class, 'uploadNoaDocument'])
            ->name('bac-secretariat.upload-noa-document');
        Route::post('/bac-secretariat/upload-performance-bond-contract-po-documents', [ProcurementController::class, 'uploadPerformanceBondContractAndPoDocuments'])
            ->name('bac-secretariat.upload-performance-bond-contract-po-documents');
        Route::post('/bac-secretariat/upload-ntp-document', [ProcurementController::class, 'uploadNTPDocument'])
            ->name('bac-secretariat.upload-ntp-document');
        Route::post('/bac-secretariat/upload-monitoring-document', [ProcurementController::class, 'uploadMonitoringDocument'])
            ->name('bac-secretariat.upload-monitoring-document');
        Route::post('/bac-secretariat/upload-completion-documents', [ProcurementController::class, 'uploadCompletionDocuments'])
            ->name('bac-secretariat.upload-completion-documents');
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
            ->name('procurements-list.index');
        Route::get('/procurements-list/{id}', [ProcurementListController::class, 'showProcurement'])
            ->name('procurements.show');

        // Procurement Stage Upload Forms
        // First one uses nested naming: bac-secretariat.procurement.procurement-initiation
        Route::prefix('procurement')->name('procurement.')->group(function () {
            Route::get('/procurement-initiation', [ProcurementController::class, 'showProcurementInitiation'])
                ->name('procurement-initiation');
        });

        // Rest use flat naming: bac-secretariat.pre-procurement-conference-upload
        Route::get('/pre-procurement-conference-upload/{id}', [ProcurementController::class, 'showPreProcurementConferenceUpload'])
            ->name('pre-procurement-conference-upload');
        Route::get('/pre-bid-conference-upload/{id}', [ProcurementController::class, 'showPreBidConferenceUpload'])
            ->name('pre-bid-conference-upload');
        Route::get('/bidding-documents-upload/{id}', [ProcurementController::class, 'showBiddingDocumentsUpload'])
            ->name('bidding-documents-upload');
        Route::get('/supplemental-bid-bulletin-upload/{id}', [ProcurementController::class, 'showSupplementalBidBulletinUpload'])
            ->name('supplemental-bid-bulletin-upload');
        Route::get('/bid-opening-upload/{id}', [ProcurementController::class, 'showBidOpeningUpload'])
            ->name('bid-opening-upload');
        Route::get('/bid-evaluation-upload/{id}', [ProcurementController::class, 'showBidEvaluationUpload'])
            ->name('bid-evaluation-upload');
        Route::get('/post-qualification-upload/{id}', [ProcurementController::class, 'showPostQualificationUpload'])
            ->name('post-qualification-upload');
        Route::get('/bac-resolution-upload/{id}', [ProcurementController::class, 'showBacResolutionUpload'])
            ->name('bac-resolution-upload');
        Route::get('/noa-upload/{id}', [ProcurementController::class, 'showNoaUpload'])
            ->name('noa-upload');
        Route::get('/performance-bond-contract-po-upload/{id}', [ProcurementController::class, 'showPerformanceBondContactAndPoUpload'])
            ->name('performance-bond-contract-po-upload');
        Route::get('/ntp-upload/{id}', [ProcurementController::class, 'showNTPUpload'])
            ->name('ntp-upload');
        Route::get('/monitoring-upload/{id}', [ProcurementController::class, 'showMonitoringUpload'])
            ->name('monitoring-upload');
        Route::get('/completion-upload/{id}', [ProcurementController::class, 'showCompletionUpload'])
            ->name('completion-upload');

        // Blockchain Status Polling
        Route::get('/procurements/{id}/blockchain-status', [ProcurementController::class, 'getBlockchainStatus'])
            ->name('procurements.blockchain-status');

        // Blockchain Publishing Status Page
        Route::get('/blockchain/publishing-status/{id}', function (string $id) {
            $procurement = \App\Models\Procurement::findOrFail($id);

            return Inertia::render('blockchain-publishing-status', [
                'procurement' => [
                    'id' => $procurement->id,
                    'title' => $procurement->title,
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
            ->name('procurements-list.index');
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
            ->name('procurements-list.index');
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
            ->name('procurements-list.index');
        Route::get('/procurements-list/{id}', [ProcurementListController::class, 'showProcurement'])
            ->name('procurements.show');

        // User Management
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [UserManagementController::class, 'index'])->name('index');
            Route::post('/', [UserManagementController::class, 'store'])->name('store');
            Route::put('/{user}', [UserManagementController::class, 'update'])->name('update');
            Route::delete('/{user}', [UserManagementController::class, 'destroy'])->name('destroy');
            Route::delete('/', [UserManagementController::class, 'bulkDelete'])->name('bulk-delete');
        });

        // Login Tracking & Monitoring
        Route::prefix('login-logs')->name('login-logs.')->group(function () {
            Route::get('/', [LoginLogController::class, 'index'])->name('index');
            Route::get('/recent', [LoginLogController::class, 'recent'])->name('recent');
            Route::get('/statistics', [LoginLogController::class, 'statistics'])->name('statistics');
            Route::get('/suspicious', [LoginLogController::class, 'suspicious'])->name('suspicious');
        });

        // Account Management & Security
        Route::prefix('accounts')->name('accounts.')->group(function () {
            Route::get('/locked', [AccountLockoutController::class, 'index'])->name('locked');
            Route::post('/{user}/unlock', [AccountLockoutController::class, 'unlock'])->name('unlock');
            Route::post('/{user}/lock', [AccountLockoutController::class, 'lock'])->name('lock');
            Route::post('/{user}/reset-attempts', [AccountLockoutController::class, 'resetAttempts'])->name('reset-attempts');
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
