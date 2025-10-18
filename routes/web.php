<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\BacChairmanController;
use App\Http\Controllers\BacSecretariatController;
use App\Http\Controllers\DocumentCorrectionController;
use App\Http\Controllers\DocumentViewController;
use App\Http\Controllers\HopeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ViewProcurementsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

Route::middleware(['auth'])->group(function () {

    Route::middleware(['role:bac_secretariat'])->group(function () {
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

        Route::post('/bac-secretariat/publish-pre-procurement-conference-decision', [ProcurementController::class, 'publishPreProcurementConferenceDecision'])
            ->name('bac-secretariat.publish-pre-procurement-conference-decision');

        Route::post('/bac-secretariat/upload-pre-procurement-conference-documents', [ProcurementController::class, 'uploadPreProcurementConferenceDocuments'])
            ->name('bac-secretariat.upload-pre-procurement-conference-documents');

        Route::post('/bac-secretariat/publish-pre-bid-conference-decision', [ProcurementController::class, 'publishPreBidConferenceDecision'])
            ->name('bac-secretariat.publish-pre-bid-conference-decision');

        Route::post('/bac-secretariat/upload-pre-bid-conference-documents', [ProcurementController::class, 'uploadPreBidConferenceDocuments'])
            ->name('bac-secretariat.upload-pre-bid-conference-documents');

        // Blockchain status polling endpoint
        Route::get('/procurements/{id}/blockchain-status', [ProcurementController::class, 'getBlockchainStatus'])
            ->name('procurements.blockchain-status');

        // Blockchain publishing status page (blocking UI)
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

    Route::middleware(['role:bac_chairman'])->group(function () {
        Route::get('bac-chairman/dashboard', [BacChairmanController::class, 'index'])
            ->name('bac-chairman.dashboard');

        Route::get('bac-chairman/procurements-list', [ViewProcurementsController::class, 'indexProcurementsList'])
            ->name('bac-chairman.procurements-list.index');

        Route::get('bac-chairman/procurements-list/{id}', [ViewProcurementsController::class, 'showProcurement'])
            ->name('bac-chairman.procurements.show');
    });

    Route::middleware(['role:hope'])->group(function () {
        Route::get('hope/dashboard', [HopeController::class, 'index'])
            ->name('hope.dashboard');

        Route::get('hope/procurements-list', [ViewProcurementsController::class, 'indexProcurementsList'])
            ->name('hope.procurements-list.index');

        Route::get('hope/procurements-list/{id}', [ViewProcurementsController::class, 'showProcurement'])
            ->name('hope.procurements.show');
    });

    Route::middleware(['role:admin'])->group(function () {
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

    Route::post('/notifications/{id}/mark-as-read', [NotificationController::class, 'markAsRead'])
        ->name('notifications.mark-as-read');
    Route::post('/notifications/mark-all-as-read', [NotificationController::class, 'markAllAsRead'])
        ->name('notifications.mark-all-as-read');

    // Secure file download route (requires authentication)
    Route::get('/secure-file/{fileKey}', [DocumentViewController::class, 'downloadFile'])
        ->name('secure.file.download')
        ->where('fileKey', '.*'); // Allow forward slashes in file keys

    // PDF Viewer with Statistics (includes all necessary data via Inertia props)
    Route::get('/pdf-viewer/{fileKey}', [DocumentViewController::class, 'showPdfViewer'])
        ->where('fileKey', '.*')
        ->name('pdf.viewer');

    // Document Correction routes (admin/BAC only)
    Route::middleware(['role:admin,bac_chairman,bac_secretariat'])->group(function () {
        Route::post('/documents/{document}/correct', [DocumentCorrectionController::class, 'correctDocument'])
            ->name('documents.correct');
        Route::get('/procurements/{procurement}/corrections', [DocumentCorrectionController::class, 'getCorrectionHistory'])
            ->name('procurements.corrections');
        Route::get('/corrections/check/{txid}', [DocumentCorrectionController::class, 'checkCorrection'])
            ->name('corrections.check');
    });

    // Document Corrections Page (view for all authenticated users)
    Route::get('/procurements/{id}/corrections', [DocumentCorrectionController::class, 'showCorrectionsPage'])
        ->name('procurements.corrections.page');

    // Blockchain Health Dashboard (admin only)
    Route::get('/admin/blockchain-health', [\App\Http\Controllers\BlockchainHealthController::class, 'index'])
        ->middleware('role:Admin')
        ->name('admin.blockchain.health');

    Route::post('/admin/blockchain-health/reset', [\App\Http\Controllers\BlockchainHealthController::class, 'reset'])
        ->middleware('role:Admin')
        ->name('admin.blockchain.health.reset');
});

Route::get('/privacy.pdf', function () {
    return response()->file(public_path('docs/privacy.pdf'));
})->name('privacy.policy');

Route::get('/terms.pdf', function () {
    return response()->file(public_path('docs/terms.pdf'));
})->name('terms.service');

require __DIR__.'/auth.php';
require __DIR__.'/settings.php';

// Load file upload UI preview routes only in local/development environments
if (app()->environment(['local', 'development'])) {
    require __DIR__.'/file-uploads-ui-preview.php';
}
