<?php

use App\Http\Controllers\BlockchainJobStatusController;
use App\Http\Controllers\DocumentCorrectionController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\DocumentVerificationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PdfViewerController;
use App\Http\Controllers\Procurement\ProcurementArchiveController;
use App\Http\Controllers\ProcurementCorrectionController;
use App\Http\Controllers\ProcurementListController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SharedLedgerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::prefix('notifications')->name('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'page']);
        Route::post('/{id}/mark-as-read', [NotificationController::class, 'markAsRead'])->name('.mark-as-read');
        Route::post('/mark-all-as-read', [NotificationController::class, 'markAllAsRead'])->name('.mark-all-as-read');
    });

    Route::get('/files/{fileKey}', [DocumentDownloadController::class, 'downloadFile'])
        ->where('fileKey', '.*')
        ->name('files.download');

    Route::get('/pdf-viewer/{fileKey}', [PdfViewerController::class, 'showPdfViewer'])
        ->where('fileKey', '.*')
        ->name('pdf.viewer');

    Route::get('/procurements/{pr_number}/blockchain-status', [ProcurementListController::class, 'getBlockchainStatus'])
        ->name('procurements.blockchain-status');

    Route::get('/blockchain-job/{jobId}/status', [BlockchainJobStatusController::class, 'status'])
        ->name('blockchain.job.status');

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::post('/generate', [ReportController::class, 'generate'])->name('generate');
        Route::post('/export', [ReportController::class, 'export'])->name('export');
    });

    // Shared Ledger — available to all authenticated users
    Route::get('/shared-ledger', [SharedLedgerController::class, 'index'])->name('shared-ledger');

    Route::post('/search', [ReportController::class, 'search'])->name('search');

    Route::middleware(['role:bac_secretariat'])->group(function () {
        Route::get('/procurements/{pr_number}/corrections', [ProcurementCorrectionController::class, 'showProcurementCorrectionsPage'])
            ->name('procurements.corrections.show');
        Route::post('/procurements/{pr_number}/corrections', [ProcurementCorrectionController::class, 'correctProcurement'])
            ->name('procurements.corrections.submit');
    });

    Route::get('/procurements/{pr_number}/corrections/history', [ProcurementCorrectionController::class, 'getProcurementCorrectionHistory'])
        ->name('procurements.corrections.history');
    Route::get('/procurements/{pr_number}/corrections/check', [ProcurementCorrectionController::class, 'checkProcurementCorrection'])
        ->name('procurements.corrections.check');

    Route::middleware(['role:admin|bac_chairman|bac_secretariat'])->group(function () {
        Route::get('/documents/corrections/{id}', [DocumentCorrectionController::class, 'showCorrectionsPage'])
            ->name('documents.corrections.show');
        Route::post('/documents/{document}/correct', [DocumentCorrectionController::class, 'correctDocument'])
            ->name('documents.correct');
    });

    Route::prefix('procurement/{pr_number}')->name('procurement.')->group(function () {
        Route::post('/verify', [DocumentVerificationController::class, 'verify'])
            ->name('verify');
        Route::post('/verify/integrity', [DocumentVerificationController::class, 'verifyIntegrity'])
            ->name('verify.integrity');
        Route::get('/verification', [DocumentVerificationController::class, 'showVerificationPage'])
            ->name('verification');

        Route::post('/archive', [ProcurementArchiveController::class, 'store'])
            ->middleware('role:admin|bac_secretariat')
            ->name('archive');
        Route::delete('/archive', [ProcurementArchiveController::class, 'destroy'])
            ->middleware('role:admin|bac_secretariat')
            ->name('restore');
    });

    Route::post('/documents/{fileKey}/verify', [DocumentVerificationController::class, 'verifyDocument'])
        ->where('fileKey', '.*')
        ->name('documents.verify');
});
