<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\BacChairmanController;
use App\Http\Controllers\BacSecretariatController;
use App\Http\Controllers\DocumentViewController;
use App\Http\Controllers\HopeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SecureFileController;
use App\Http\Controllers\ViewProcurementsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

Route::get('/bac-secretariat/preprocurement', function () {
    return Inertia::render('bac-secretariat/procurement-stage/pre-procurement-conference-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.preprocurement');

Route::get('/bac-secretariat/pre-bid-conference-upload', function () {
    return Inertia::render('bac-secretariat/procurement-stage/pre-bid-conference-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.pre-bid-conference-upload.simple');

Route::get('/bac-secretariat/supplemental-bid-bulletin-upload', function () {
    return Inertia::render('bac-secretariat/procurement-stage/supplemental-bid-bulletin-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.supplemental-bid-bulletin-upload.simple');

Route::get('/bac-secretariat/bidding-documents-upload', function () {
    return Inertia::render('bac-secretariat/procurement-stage/bidding-documents-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.bidding-documents-upload.simple');

Route::get('/bac-secretariat/bid-opening-upload', function () {
    return Inertia::render('bac-secretariat/procurement-stage/bid-opening-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.bid-opening-upload.simple');

Route::get('/bac-secretariat/bid-evaluation-upload', function () {
    return Inertia::render('bac-secretariat/procurement-stage/bid-evaluation-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.bid-evaluation-upload.simple');

Route::get('/bac-secretariat/bac-resolution-upload', function () {
    return Inertia::render('bac-secretariat/procurement-stage/bac-resolution-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.bac-resolution-upload.simple');

Route::get('/bac-secretariat/post-qualification-upload', function () {
    return Inertia::render('bac-secretariat/procurement-stage/post-qualification-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.post-qualification-upload.simple');

Route::get('/bac-secretariat/noa-upload', function () {
    return Inertia::render('bac-secretariat/procurement-stage/noa-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.noa-upload.simple');

Route::get('/bac-secretariat/performance-bond-contract-po-upload', function () {
    return Inertia::render('bac-secretariat/procurement-stage/performance-bond-contract-po-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.performance-bond-contract-po-upload.simple');

Route::get('/bac-secretariat/ntp-upload', function () {
    return Inertia::render('bac-secretariat/procurement-stage/ntp-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.ntp-upload.simple');

Route::get('/bac-secretariat/monitoring-upload', function () {
    return Inertia::render('bac-secretariat/procurement-stage/monitoring-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.monitoring-upload.simple');

Route::get('/bac-secretariat/completion-upload', function () {
    return Inertia::render('bac-secretariat/procurement-stage/completion-upload', [
        'user' => Auth::user(),
    ]);
})->name('bac-secretariat.completion-upload.simple');

Route::get('/', function () {
    return Inertia::render('home');
})->name('home');

Route::inertia('/about', 'about')
    ->name('about');

Route::inertia('/team', 'team')
    ->name('team');

Route::inertia('/contact', 'contact')
    ->name('contact');

Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/search/suggestions', [SearchController::class, 'suggestions'])->name('search.suggestions');

Route::middleware(['auth', 'verified'])->group(function () {

    // Smart Contract Demo Route (accessible to BAC roles)
    Route::get('/smart-contract-demo/{procurement_id}', function ($procurement_id) {
        return Inertia::render('smart-contract-demo/document-upload', [
            'user' => Auth::user(),
            'procurement_id' => $procurement_id,
        ]);
    })->middleware('role:bac_secretariat,bac_chairman,admin')->name('smart-contract.demo');

    Route::middleware(['role:bac_secretariat', 'mfa'])->group(function () {
        // Job status polling endpoint (for frontend polling job status)
        Route::get('/api/job-status/{id}', [App\Http\Controllers\ProcurementController::class, 'getJobStatus'])->name('api.job-status.show');

        Route::get('/bac-secretariat/dashboard', [BacSecretariatController::class, 'dashboard'])
            ->name('bac-secretariat.dashboard');

        Route::get('/bac-secretariat/procurements-list', [ViewProcurementsController::class, 'indexProcurementsList'])
            ->name('bac-secretariat.procurements-list.index');

        Route::get('/bac-secretariat/procurements-list/{id}', [ViewProcurementsController::class, 'showProcurement'])
            ->name('bac-secretariat.procurements.show');

        Route::get('/bac-secretariat/procurement/procurement-initiation', [ProcurementController::class, 'showProcurementInitiation'])
            ->name('bac-secretariat.procurement.procurement-initiation');

        Route::get('/bac-secretariat/pre-procurement-conference-upload/{id}', [ProcurementController::class, 'showPreProcurementConferenceUpload'])
            ->name('bac-secretariat.pre-procurement-conference-upload');

        Route::get('/bac-secretariat/pre-bid-conference-upload/{id}', [ProcurementController::class, 'showPreBidConferenceUpload'])
            ->name('bac-secretariat.pre-bid-conference-upload');

        Route::get('/bac-secretariat/bidding-documents-upload/{id}', [ProcurementController::class, 'showBiddingDocumentsUpload'])
            ->name('bac-secretariat.bidding-documents-upload');

        Route::get('/bac-secretariat/supplemental-bid-bulletin-upload/{id}', [ProcurementController::class, 'showSupplementalBidBulletinUpload'])
            ->name('bac-secretariat.supplemental-bid-bulletin-upload');

        Route::get('/bac-secretariat/bid-opening-upload/{id}', [ProcurementController::class, 'showBidOpeningUpload'])
            ->name('bac-secretariat.bid-opening-upload');

        Route::get('/bac-secretariat/bid-evaluation-upload/{id}', [ProcurementController::class, 'showBidEvaluationUpload'])
            ->name('bac-secretariat.bid-evaluation-upload');

        Route::get('/bac-secretariat/post-qualification-upload/{id}', [ProcurementController::class, 'showPostQualificationUpload'])
            ->name('bac-secretariat.post-qualification-upload');

        Route::get('/bac-secretariat/bac-resolution-upload/{id}', [ProcurementController::class, 'showBacResolutionUpload'])
            ->name('bac-secretariat.bac-resolution-upload');

        Route::get('/bac-secretariat/noa-upload/{id}', [ProcurementController::class, 'showNoaUpload'])
            ->name('bac-secretariat.noa-upload');

        Route::get('/bac-secretariat/performance-bond-contract-po-upload/{id}', [ProcurementController::class, 'showPerformanceBondContactAndPoUpload'])
            ->name('bac-secretariat.performance-bond-contract-po-upload');

        Route::get('/bac-secretariat/ntp-upload/{id}', [ProcurementController::class, 'showNTPUpload'])
            ->name('bac-secretariat.ntp-upload');

        Route::get('/bac-secretariat/monitoring-upload/{id}', [ProcurementController::class, 'showMonitoringUpload'])
            ->name('bac-secretariat.monitoring-upload');

        Route::get('/bac-secretariat/completion-upload/{id}', [ProcurementController::class, 'showCompletionUpload']) // Updated route path
            ->name('bac-secretariat.completion-upload'); // Updated route name

        Route::post('/bac-secretariat/publish-procurement-initiation', [ProcurementController::class, 'publishProcurementInitiation'])
            ->name('publish-procurement-initiation');

        Route::post('/bac-secretariat/save-procurement-draft', [ProcurementController::class, 'saveProcurementDraft'])
            ->name('bac-secretariat.save-procurement-draft');

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

    Route::middleware(['role:bac_chairman', 'mfa'])->group(function () {
        Route::get('bac-chairman/dashboard', [BacChairmanController::class, 'index'])
            ->name('bac-chairman.dashboard');

        Route::get('bac-chairman/procurements-list', [ViewProcurementsController::class, 'indexProcurementsList'])
            ->name('bac-chairman.procurements-list.index');

        Route::get('bac-chairman/procurements-list/{id}', [ViewProcurementsController::class, 'showProcurement'])
            ->name('bac-chairman.procurements.show');
    });

    Route::middleware(['role:hope', 'mfa'])->group(function () {
        Route::get('hope/dashboard', [HopeController::class, 'index'])
            ->name('hope.dashboard');

        Route::get('hope/procurements-list', [ViewProcurementsController::class, 'indexProcurementsList'])
            ->name('hope.procurements-list.index');

        Route::get('hope/procurements-list/{id}', [ViewProcurementsController::class, 'showProcurement'])
            ->name('hope.procurements.show');
    });

    Route::middleware(['role:admin', 'mfa'])->group(function () {
        Route::get('admin/dashboard', [AdminController::class, 'index'])
            ->name('admin.dashboard');

        Route::get('admin/procurements-list', [ViewProcurementsController::class, 'indexProcurementsList'])
            ->name('admin.procurements-list.index');

        Route::get('admin/procurements-list/{id}', [ViewProcurementsController::class, 'showProcurement'])
            ->name('admin.procurements.show');

        // User Management Routes
        Route::get('admin/users', [AdminController::class, 'users'])
            ->name('admin.users');
        Route::post('admin/users', [AdminController::class, 'storeUser'])
            ->name('admin.users.store');
        Route::put('admin/users/{user}', [AdminController::class, 'updateUser'])
            ->name('admin.users.update');
        Route::delete('admin/users/{user}', [AdminController::class, 'destroyUser'])
            ->name('admin.users.destroy');
        Route::delete('admin/users', [AdminController::class, 'bulkDeleteUsers'])
            ->name('admin.users.bulk-delete');

        // Login Tracking Routes
        Route::get('admin/login-logs', [AdminController::class, 'loginLogs'])
            ->name('admin.login-logs');
        Route::get('admin/login-logs/recent', [AdminController::class, 'recentLogins'])
            ->name('admin.login-logs.recent');
        Route::get('admin/login-logs/statistics', [AdminController::class, 'loginStatistics'])
            ->name('admin.login-logs.statistics');
        Route::get('admin/login-logs/suspicious', [AdminController::class, 'suspiciousActivities'])
            ->name('admin.login-logs.suspicious');

        // Account Locking Routes
        Route::get('admin/accounts/locked', [AdminController::class, 'lockedAccounts'])
            ->name('admin.accounts.locked');
        Route::post('admin/accounts/{user}/unlock', [AdminController::class, 'unlockAccount'])
            ->name('admin.accounts.unlock');
        Route::post('admin/accounts/{user}/lock', [AdminController::class, 'lockAccount'])
            ->name('admin.accounts.lock');
        Route::post('admin/accounts/{user}/reset-attempts', [AdminController::class, 'resetFailedAttempts'])
            ->name('admin.accounts.reset-attempts');
    });

    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'page'])
        ->name('notifications');

    // Notification API routes
    Route::get('/notifications/list', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/mark-as-read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-as-read', [NotificationController::class, 'markAllAsRead']);

    // Secure file download route (requires authentication)
    Route::get('/secure-file/{fileKey}', [SecureFileController::class, 'downloadFile'])
        ->name('secure.file.download')
        ->where('fileKey', '.*'); // Allow forward slashes in file keys

    // Document view tracking routes
    Route::get('/api/document-views/file/{fileKey}', [DocumentViewController::class, 'getFileViews'])
        ->where('fileKey', '.*')
        ->name('document.views.file');
    Route::get('/api/document-views/file/{fileKey}/stats', [DocumentViewController::class, 'getFileStats'])
        ->where('fileKey', '.*')
        ->name('document.views.file.stats');
    Route::get('/api/document-views/procurement/{procurementId}/stats', [DocumentViewController::class, 'getProcurementViewStats'])
        ->name('document.views.procurement.stats');
    Route::get('/api/document-views/history', [DocumentViewController::class, 'getUserViewHistory'])
        ->name('document.views.history');
    Route::get('/api/document-views/most-viewed', [DocumentViewController::class, 'getMostViewedDocuments'])
        ->name('document.views.most-viewed');
    Route::get('/api/document-views/dashboard-stats', [DocumentViewController::class, 'getDashboardStats'])
        ->name('document.views.dashboard.stats');
    Route::post('/api/document-views/update-duration', [DocumentViewController::class, 'updateViewDuration'])
        ->name('document.views.update-duration');

    // PDF Viewer with Statistics
    Route::get('/pdf-viewer/{fileKey}', [DocumentViewController::class, 'showPdfViewer'])
        ->where('fileKey', '.*')
        ->name('pdf.viewer');
});

Route::get('/privacy.pdf', function () {
    return response()->file(public_path('docs/privacy.pdf'));
})->name('privacy.policy');

Route::get('/terms.pdf', function () {
    return response()->file(public_path('docs/terms.pdf'));
})->name('terms.service');

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/smart-contracts.php';