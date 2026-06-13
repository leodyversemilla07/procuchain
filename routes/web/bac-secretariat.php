<?php

use App\Http\Controllers\BacSecretariatDashboardController;
use App\Http\Controllers\Procurement\ProcurementInitiationController;
use App\Http\Controllers\Procurement\ProcurementStageController;
use App\Http\Controllers\ProcurementListController;
use App\Http\Controllers\SharedLedgerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:bac_secretariat', 'throttle:blockchain_writes'])
    ->prefix('bac-secretariat')
    ->name('bac-secretariat.')
    ->where(['pr_number' => 'PR-\d{4}-\d{3}(-\d{4})?', 'stage' => '[a-z_\-]+'])
    ->group(function () {
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

        Route::post('/pre-procurement/{pr_number}/{stage}/upload-document', [ProcurementStageController::class, 'uploadSingleDocument'])
            ->name('procurement.pre-procurement.upload-document');
        Route::post('/pre-procurement/{pr_number}/{stage}/complete', [ProcurementStageController::class, 'markStageComplete'])
            ->name('procurement.pre-procurement.complete');
        Route::post('/pre-procurement/{pr_number}/{stage}/skip', [ProcurementStageController::class, 'skipStage'])
            ->name('procurement.pre-procurement.skip');
        Route::post('/publish-pre-procurement-conference-decision', [ProcurementStageController::class, 'publishDecision'])
            ->name('publish-pre-procurement-conference-decision');
        Route::post('/publish-pre-bid-conference-decision', [ProcurementStageController::class, 'publishPreBidDecision'])
            ->name('publish-pre-bid-conference-decision');
        Route::post('/publish-supplemental-bid-bulletin-decision', [ProcurementStageController::class, 'publishSupplementalBidBulletinDecision'])
            ->name('publish-supplemental-bid-bulletin-decision');

        Route::post('/procurement/{pr_number}/{stage}/upload-document', [ProcurementStageController::class, 'uploadSingleDocument'])
            ->name('procurement.bidding.upload-document');
        Route::post('/procurement/{pr_number}/{stage}/complete', [ProcurementStageController::class, 'markStageComplete'])
            ->name('procurement.bidding.complete');
        Route::post('/procurement/{pr_number}/{stage}/skip', [ProcurementStageController::class, 'skipStage'])
            ->name('procurement.bidding.skip');
        Route::post('/procurement/{pr_number}/{stage}/repeat', [ProcurementStageController::class, 'repeatStage'])
            ->name('procurement.bidding.repeat');

        Route::post('/post-procurement/{pr_number}/{stage}/upload-document', [ProcurementStageController::class, 'uploadSingleDocument'])
            ->name('procurement.post-procurement.upload-document');
        Route::post('/post-procurement/{pr_number}/{stage}/complete', [ProcurementStageController::class, 'markStageComplete'])
            ->name('procurement.post-procurement.complete');
        Route::post('/post-procurement/{pr_number}/{stage}/skip', [ProcurementStageController::class, 'skipStage'])
            ->name('procurement.post-procurement.skip');
        Route::post('/post-procurement/{pr_number}/delivery-details', [ProcurementStageController::class, 'updateDeliveryDetails'])
            ->name('procurement.post-procurement.delivery-details');

        Route::post('/upload-bidding-documents', function () {
            abort(410, 'This route is deprecated. Please use the new phase-based routes.');
        })->name('upload-bidding-documents');
    });

Route::middleware(['auth', 'role:bac_secretariat'])
    ->prefix('bac-secretariat')
    ->name('bac-secretariat.')
    ->where(['pr_number' => 'PR-\d{4}-\d{3}(-\d{4})?', 'stage' => '[a-z_\-]+'])
    ->group(function () {
        Route::get('/dashboard', [BacSecretariatDashboardController::class, 'dashboard'])->name('dashboard');

        Route::get('/procurements-list', [ProcurementListController::class, 'index'])
            ->name('procurements.index');
        Route::get('/procurements-list/{pr_number}', [ProcurementListController::class, 'show'])
            ->name('procurements.show');

        Route::get('/procurement-initiation', [ProcurementInitiationController::class, 'show'])
            ->name('procurement.initiation.index');
        Route::get('/procurement-initiation/{pr_number}', [ProcurementInitiationController::class, 'show'])
            ->name('procurement.initiation.show');

        Route::get('/pre-procurement/{pr_number}/{stage}', [ProcurementStageController::class, 'show'])
            ->name('procurement.pre-procurement.show');
        Route::get('/pre-procurement/{pr_number}/{stage}/document-guide', [ProcurementStageController::class, 'documentGuide'])
            ->name('procurement.pre-procurement.document-guide');
        Route::get('/pre-procurement/{pr_number}/{stage}/check-completion', [ProcurementStageController::class, 'checkCompletion'])
            ->name('procurement.pre-procurement.check-completion');
        Route::post('/pre-procurement/{pr_number}/{stage}/validate-upload', [ProcurementStageController::class, 'validateUpload'])
            ->name('procurement.pre-procurement.validate-upload');

        Route::get('/procurement/{pr_number}/{stage}', [ProcurementStageController::class, 'show'])
            ->name('procurement.bidding.show');
        Route::get('/procurement/{pr_number}/{stage}/document-guide', [ProcurementStageController::class, 'documentGuide'])
            ->name('procurement.bidding.document-guide');
        Route::get('/procurement/{pr_number}/{stage}/check-completion', [ProcurementStageController::class, 'checkCompletion'])
            ->name('procurement.bidding.check-completion');
        Route::post('/procurement/{pr_number}/{stage}/validate-upload', [ProcurementStageController::class, 'validateUpload'])
            ->name('procurement.bidding.validate-upload');

        Route::get('/post-procurement/{pr_number}/{stage}', [ProcurementStageController::class, 'show'])
            ->name('procurement.post-procurement.show');
        Route::get('/post-procurement/{pr_number}/{stage}/document-guide', [ProcurementStageController::class, 'documentGuide'])
            ->name('procurement.post-procurement.document-guide');
        Route::get('/post-procurement/{pr_number}/{stage}/check-completion', [ProcurementStageController::class, 'checkCompletion'])
            ->name('procurement.post-procurement.check-completion');
        Route::post('/post-procurement/{pr_number}/{stage}/validate-upload', [ProcurementStageController::class, 'validateUpload'])
            ->name('procurement.post-procurement.validate-upload');

        // Shared Ledger
        Route::get('/shared-ledger', [SharedLedgerController::class, 'index'])->name('shared-ledger');
    });
