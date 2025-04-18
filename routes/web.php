<?php

use App\Http\Controllers\BacChairmanController;
use App\Http\Controllers\BacSecretariatController;
use App\Http\Controllers\HopeController;
use App\Http\Controllers\PrGeneratorController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\ViewProcurementsController;
use App\Services\MultichainService;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('home');
})->name('home');

Route::inertia('/about', 'about')
    ->name('about');

Route::inertia('/procurement', 'procurement')
    ->name('procurement');

Route::inertia('/generate-pr-show', 'generate-pr')
    ->name('generate-pr.index');

Route::post('generate-pr-store', [PrGeneratorController::class, 'store'])
    ->name('generate-pr.store');

Route::get('/test-multichain', function (): \Illuminate\Http\JsonResponse {
    $multichainService = new MultichainService;
    $connectionInfo = $multichainService->listStreams();

    return response()->json($connectionInfo);
});

Route::get('/list-stream-items/{streamName}', function (string $streamName): \Illuminate\Http\JsonResponse {
    $multichainService = new MultichainService;
    $items = $multichainService->listStreamItems($streamName);

    return response()->json($items);
});

Route::middleware(['auth', 'verified'])->group(function () {

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

        Route::get('/bac-secretariat/complete-status/{id}', [ProcurementController::class, 'showCompleteStatus'])
            ->name('bac-secretariat.complete-status');

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

        Route::post('/bac-secretariat/complete-process', [ProcurementController::class, 'publishCompleteProcess'])
            ->name('bac-secretariat.complete-process');

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
            ->name('bac-chairman.procurements-list.index');

        Route::get('hope/procurements-list/{id}', [ViewProcurementsController::class, 'showProcurement'])
            ->name('hope.procurements.show');
    });

});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
