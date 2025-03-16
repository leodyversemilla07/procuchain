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

        Route::get('bac-secretariat/procurements-list', [BacSecretariatController::class, 'indexProcurementsList'])
            ->name('bac-secretariat.procurements-list.index');

        Route::get('bac-secretariat/procurements-list/{id}', [BacSecretariatController::class, 'showProcurement'])
            ->name('bac-secretariat.procurements.show');

        Route::get('bac-secretariat/procurement/pr-initiation', [BacSecretariatController::class, 'showPrInitiation'])
            ->name('bac-secretariat.procurement.pr-initiation');

        Route::post('bac-secretariat/publish-pr-initiation', [ProcurementController::class, 'publishPrInitiation'])
            ->name('publish-pr-initiation');

        // Pre-Procurement Upload routes
        Route::get('/bac-secretariat/pre-procurement-upload/{id}', [BacSecretariatController::class, 'showPreProcurementUpload'])
            ->name('bac-secretariat.pre-procurement-upload');

        // Pre-Procurement Conference Decision route
        Route::post('/bac-secretariat/publish-pre-procurement-decision', [ProcurementController::class, 'publishPreProcurementDecision'])
            ->name('bac-secretariat.publish-pre-procurement-decision');

        Route::post('/bac-secretariat/upload-pre-procurement-documents', [ProcurementController::class, 'uploadPreProcurementDocuments'])
            ->name('bac-secretariat.upload-pre-procurement-documents');

        // Bid Invitation Upload routes
        Route::get('/bac-secretariat/bid-invitation-upload/{id}', [BacSecretariatController::class, 'showBidInvitationUpload'])
            ->name('bac-secretariat.bid-invitation-upload');

        // Bid Invitation Publication route
        Route::post('/bac-secretariat/publish-bid-invitation', [ProcurementController::class, 'publishBidInvitation'])
            ->name('bac-secretariat.publish-bid-invitation');

        // Bid Submission and Opening routes
        Route::get('/bac-secretariat/bid-submission-upload/{id}', [BacSecretariatController::class, 'showBidSubmissionUpload'])
            ->name('bac-secretariat.bid-submission-upload');

        Route::post('/bac-secretariat/upload-bid-submission-documents', [ProcurementController::class, 'uploadBidSubmissionDocuments'])
            ->name('bac-secretariat.upload-bid-submission-documents');

        // Bid Evaluation routes
        Route::get('/bac-secretariat/bid-evaluation-upload/{id}', [BacSecretariatController::class, 'showBidEvaluationUpload'])
            ->name('bac-secretariat.bid-evaluation-upload');

        Route::post('/bac-secretariat/upload-bid-evaluation-documents', [ProcurementController::class, 'uploadBidEvaluationDocuments'])
            ->name('bac-secretariat.upload-bid-evaluation-documents');

        // Post-Qualification routes
        Route::get('/bac-secretariat/post-qualification-upload/{id}', [BacSecretariatController::class, 'showPostQualificationUpload'])
            ->name('bac-secretariat.post-qualification-upload');

        Route::post('/bac-secretariat/upload-post-qualification-documents', [ProcurementController::class, 'uploadPostQualificationDocuments'])
            ->name('bac-secretariat.upload-post-qualification-documents');

        // BAC Resolution routes
        Route::get('/bac-secretariat/bac-resolution-upload/{id}', [BacSecretariatController::class, 'showBacResolutionUpload'])
            ->name('bac-secretariat.bac-resolution-upload');

        Route::post('/bac-secretariat/upload-bac-resolution-document', [ProcurementController::class, 'uploadBacResolutionDocument'])
            ->name('bac-secretariat.upload-bac-resolution-document');

        // Notice of Award routes
        Route::get('/bac-secretariat/noa-upload/{id}', [BacSecretariatController::class, 'showNoaUpload'])
            ->name('bac-secretariat.noa-upload');

        Route::post('/bac-secretariat/upload-noa-document', [ProcurementController::class, 'uploadNoaDocument'])
            ->name('bac-secretariat.upload-noa-document');

        // Performance Bond routes
        Route::get('/bac-secretariat/performance-bond-upload/{id}', [BacSecretariatController::class, 'showPerformanceBondUpload'])
            ->name('bac-secretariat.performance-bond-upload');

        Route::post('/bac-secretariat/upload-performance-bond-document', [ProcurementController::class, 'uploadPerformanceBondDocument'])
            ->name('bac-secretariat.upload-performance-bond-document');

        // Contract and PO routes
        Route::get('/bac-secretariat/contract-po-upload/{id}', [BacSecretariatController::class, 'showContractPOUpload'])
            ->name('bac-secretariat.contract-po-upload');

        Route::post('/bac-secretariat/upload-contract-po-documents', [ProcurementController::class, 'uploadContractPODocuments'])
            ->name('bac-secretariat.upload-contract-po-documents');

        // Notice to Proceed routes
        Route::get('/bac-secretariat/ntp-upload/{id}', [BacSecretariatController::class, 'showNTPUpload'])
            ->name('bac-secretariat.ntp-upload');

        Route::post('/bac-secretariat/upload-ntp-document', [ProcurementController::class, 'uploadNTPDocument'])
            ->name('bac-secretariat.upload-ntp-document');

        // Monitoring routes
        Route::get('/bac-secretariat/monitoring-upload/{id}', [BacSecretariatController::class, 'showMonitoringUpload'])
            ->name('bac-secretariat.monitoring-upload');

        Route::post('/bac-secretariat/upload-monitoring-document', [ProcurementController::class, 'uploadMonitoringDocument'])
            ->name('bac-secretariat.upload-monitoring-document');

        Route::get('/bac-secretariat/testing', function() {
            return Inertia::render('testing');
        })->name('bac-secretariat.testing');
    });

    Route::middleware(['role:bac_chairman'])->group(function () {
        Route::get('bac-chairman/dashboard', [BacChairmanController::class, 'index'])
            ->name('bac-chairman.dashboard');

        Route::get('bac-chairman/procurements-list', [BacChairmanController::class, 'indexProcurementsList'])
            ->name('bac-chairman.procurements-list.index');

        Route::get('bac-chairman/procurements-list/{id}', [BacChairmanController::class, 'showProcurement'])
            ->name('bac-chairman.procurements.show');

    });

    Route::middleware(['role:hope'])->group(function () {
        Route::get('hope/dashboard', [HopeController::class, 'index'])
            ->name('hope.dashboard');

        Route::get('hope/procurements-list', [HopeController::class, 'indexProcurementsList'])
            ->name('bac-chairman.procurements-list.index');

        Route::get('hope/procurements-list/{id}', [HopeController::class, 'showProcurement'])
            ->name('hope.procurements.show');
    });

});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
