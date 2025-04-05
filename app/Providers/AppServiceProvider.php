<?php

namespace App\Providers;

use App\Handlers\BacResolution\BacResolutionHandler;
use App\Handlers\BiddingDocuments\BiddingDocumentsHandler;
use App\Handlers\BidEvaluation\BidEvaluationHandler;
use App\Handlers\BidOpening\BidOpeningHandler;
use App\Handlers\Completion\CompletionDocumentsHandler;
use App\Handlers\Completion\CompletionProcessHandler;
use App\Handlers\Monitoring\MonitoringHandler;
use App\Handlers\NoticeOfAward\NoticeOfAwardHandler;
use App\Handlers\NoticeToProceed\NoticeToProceedHandler;
use App\Handlers\PerformanceBondContractAndPo\PerformanceBondContractAndPoHandler;
use App\Handlers\PostQualification\PostQualificationHandler;
use App\Handlers\PreBidConference\PreBidConferenceDecisionHandler;
use App\Handlers\PreBidConference\PreBidConferenceDocumentsHandler;
use App\Handlers\PreProcurementConference\PreProcurementConferenceDecisionHandler;
use App\Handlers\PreProcurementConference\PreProcurementConferenceDocumentsHandler;
use App\Handlers\ProcurementInitiation\ProcurementInitiationHandler;
use App\Handlers\SupplementalBidBulletin\SupplementalBidBulletinDecisionHandler;
use App\Handlers\SupplementalBidBulletin\SupplementalBidBulletinDocumentsHandler;
use App\Services\BlockchainService;
use App\Services\FileStorageService;
use App\Services\NotificationService;
use App\Services\StreamKeyService;
use App\Services\MultichainService;
use App\Services\ProcurementStageTransitionService;
use App\Services\BacSecretariatServices;
use App\Services\EventTypeLabelMapper;
use App\Services\ProcurementDataTransformerService;
use App\Handlers\ProcurementViewHandler;
use Barryvdh\DomPDF\ServiceProvider as DomPDFServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(DomPDFServiceProvider::class);
        $this->registerServices();
        $this->registerHandlers();
    }

    private function registerServices(): void 
    {
        $this->app->singleton(MultichainService::class);
        $this->app->singleton(BlockchainService::class);
        $this->app->singleton(StreamKeyService::class);
        $this->app->singleton(ProcurementStageTransitionService::class);
        $this->app->singleton(EventTypeLabelMapper::class);
        $this->app->singleton(ProcurementDataTransformerService::class);
        
        $this->app->singleton(ProcurementViewHandler::class, function ($app) {
            return new ProcurementViewHandler(
                $app->make(BlockchainService::class),
                $app->make(ProcurementDataTransformerService::class)
            );
        });

        $this->app->singleton(BacSecretariatServices::class, function ($app) {
            $services = new BacSecretariatServices(
                $app->make(MultichainService::class),
                $app->make(StreamKeyService::class),
                $app->make(ProcurementViewHandler::class)
            );

            $services->setStageTransitionService($app->make(ProcurementStageTransitionService::class))
                    ->setEventTypeLabelMapper($app->make(EventTypeLabelMapper::class));

            return $services;
        });
    }

    private function registerHandlers(): void
    {
        $handlers = [
            ProcurementInitiationHandler::class,
            PreProcurementConferenceDecisionHandler::class,
            PreProcurementConferenceDocumentsHandler::class,
            BiddingDocumentsHandler::class,
            PreBidConferenceDecisionHandler::class,
            PreBidConferenceDocumentsHandler::class,
            SupplementalBidBulletinDecisionHandler::class,
            SupplementalBidBulletinDocumentsHandler::class,
            BidOpeningHandler::class,
            BidEvaluationHandler::class,
            PostQualificationHandler::class,
            BacResolutionHandler::class,
            NoticeOfAwardHandler::class,
            PerformanceBondContractAndPoHandler::class,
            NoticeToProceedHandler::class,
            MonitoringHandler::class,
            CompletionProcessHandler::class,
            CompletionDocumentsHandler::class,
        ];

        foreach ($handlers as $handler) {
            $this->registerHandler($handler);
        }
    }

    private function registerHandler(string $handlerClass): void
    {
        $this->app->bind($handlerClass, function ($app) use ($handlerClass) {
            return new $handlerClass(
                $app->make(BlockchainService::class),
                $app->make(FileStorageService::class),
                $app->make(NotificationService::class)
            );
        });
    }

    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}
