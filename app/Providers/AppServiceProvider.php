<?php

namespace App\Providers;

use Barryvdh\DomPDF\ServiceProvider as DomPDFServiceProvider;
use App\Services\StreamKeyService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use App\Services\BlockchainService;
use App\Services\FileStorageService;
use App\Services\NotificationService;

use App\Handlers\ProcurementInitiation\ProcurementInitiationHandler;
use App\Handlers\PreProcurementConference\PreProcurementConferenceDecisionHandler;
use App\Handlers\PreProcurementConference\PreProcurementConferenceDocumentsHandler;
use App\Handlers\BiddingDocuments\BiddingDocumentsHandler;
use App\Handlers\PreBidConference\PreBidConferenceDecisionHandler;
use App\Handlers\PreBidConference\PreBidConferenceDocumentsHandler;
use App\Handlers\SupplementalBidBulletin\SupplementalBidBulletinDecisionHandler;
use App\Handlers\SupplementalBidBulletin\SupplementalBidBulletinDocumentsHandler;
use App\Handlers\BidOpening\BidOpeningHandler;
use App\Handlers\BidEvaluation\BidEvaluationHandler;
use App\Handlers\PostQualification\PostQualificationHandler;
use App\Handlers\BacResolution\BacResolutionHandler;
use App\Handlers\NoticeOfAward\NoticeOfAwardHandler;
use App\Handlers\PerformanceBondContractAndPo\PerformanceBondContractAndPoHandler;
use App\Handlers\NoticeToProceed\NoticeToProceedHandler;
use App\Handlers\Monitoring\MonitoringHandler;
use App\Handlers\Completion\CompletionProcessHandler;
use App\Handlers\Completion\CompletionDocumentsHandler;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(DomPDFServiceProvider::class);

        $this->app->singleton(StreamKeyService::class);

        $this->app->bind(ProcurementInitiationHandler::class, function ($app) {
            return new ProcurementInitiationHandler(
                $app->make(BlockchainService::class),
                $app->make(FileStorageService::class),
                $app->make(NotificationService::class)
            );
        });

        $this->app->bind(PreProcurementConferenceDecisionHandler::class, function ($app) {
            return new PreProcurementConferenceDecisionHandler(
                $app->make(BlockchainService::class),
                $app->make(FileStorageService::class),
                $app->make(NotificationService::class)
            );
        });

        $this->app->bind(PreProcurementConferenceDocumentsHandler::class, function ($app) {
            return new PreProcurementConferenceDocumentsHandler(
                $app->make(BlockchainService::class),
                $app->make(FileStorageService::class),
                $app->make(NotificationService::class)
            );
        });

        $this->app->bind(BiddingDocumentsHandler::class, function ($app) {
            return new BiddingDocumentsHandler(
                $app->make(BlockchainService::class),
                $app->make(FileStorageService::class),
                $app->make(NotificationService::class)
            );
        });

        $this->app->bind(PreBidConferenceDecisionHandler::class, function ($app) {
            return new PreBidConferenceDecisionHandler(
                $app->make(BlockchainService::class),
                $app->make(FileStorageService::class),
                $app->make(NotificationService::class)
            );
        });

        $this->app->bind(PreBidConferenceDocumentsHandler::class, function ($app) {
            return new PreBidConferenceDocumentsHandler(
                $app->make(BlockchainService::class),
                $app->make(FileStorageService::class),
                $app->make(NotificationService::class)
            );
        });

        $this->app->bind(SupplementalBidBulletinDecisionHandler::class, function ($app) {
            return new SupplementalBidBulletinDecisionHandler(
                $app->make(BlockchainService::class),
                $app->make(FileStorageService::class),
                $app->make(NotificationService::class)
            );
        });

        $this->app->bind(SupplementalBidBulletinDocumentsHandler::class, function ($app) {
            return new SupplementalBidBulletinDocumentsHandler(
                $app->make(BlockchainService::class),
                $app->make(FileStorageService::class),
                $app->make(NotificationService::class)
            );
        });

        $this->app->bind(BidOpeningHandler::class, function ($app) {
            return new BidOpeningHandler(
                $app->make(BlockchainService::class),
                $app->make(FileStorageService::class),
                $app->make(NotificationService::class)
            );
        });

        $this->app->bind(BidEvaluationHandler::class, function ($app) {
            return new BidEvaluationHandler(
                $app->make(BlockchainService::class),
                $app->make(FileStorageService::class),
                $app->make(NotificationService::class)
            );
        });

        $this->app->bind(PostQualificationHandler::class, function ($app) {
            return new PostQualificationHandler(
                $app->make(BlockchainService::class),
                $app->make(FileStorageService::class),
                $app->make(NotificationService::class)
            );
        });

        $this->app->bind(BacResolutionHandler::class, function ($app) {
            return new BacResolutionHandler(
                $app->make(BlockchainService::class),
                $app->make(FileStorageService::class),
                $app->make(NotificationService::class)
            );
        });

        $this->app->bind(NoticeOfAwardHandler::class, function ($app) {
            return new NoticeOfAwardHandler(
                $app->make(BlockchainService::class),
                $app->make(FileStorageService::class),
                $app->make(NotificationService::class)
            );
        });

        $this->app->bind(PerformanceBondContractAndPoHandler::class, function ($app) {
            return new PerformanceBondContractAndPoHandler(
                $app->make(BlockchainService::class),
                $app->make(FileStorageService::class),
                $app->make(NotificationService::class)
            );
        });

        $this->app->bind(NoticeToProceedHandler::class, function ($app) {
            return new NoticeToProceedHandler(
                $app->make(BlockchainService::class),
                $app->make(FileStorageService::class),
                $app->make(NotificationService::class)
            );
        });

        $this->app->bind(MonitoringHandler::class, function ($app) {
            return new MonitoringHandler(
                $app->make(BlockchainService::class),
                $app->make(FileStorageService::class),
                $app->make(NotificationService::class)
            );
        });

        $this->app->bind(CompletionProcessHandler::class, function ($app) {
            return new CompletionProcessHandler(
                $app->make(BlockchainService::class),
                $app->make(FileStorageService::class),
                $app->make(NotificationService::class)
            );
        });

        $this->app->bind(CompletionDocumentsHandler::class, function ($app) {
            return new CompletionDocumentsHandler(
                $app->make(BlockchainService::class),
                $app->make(FileStorageService::class),
                $app->make(NotificationService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}
