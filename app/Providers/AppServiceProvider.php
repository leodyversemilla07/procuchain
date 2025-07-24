<?php

namespace App\Providers;

use App\Services\EventTypeLabelMapper;
use App\Services\FileStorageService;
use App\Services\MultichainService;
use App\Services\NotificationService;
use App\Services\ProcurementStageTransitionService;
use App\Services\StreamKeyService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register core services as singletons (one instance per request)
        $this->app->singleton(MultichainService::class);
        $this->app->singleton(StreamKeyService::class);
        $this->app->singleton(ProcurementStageTransitionService::class);
        $this->app->singleton(EventTypeLabelMapper::class);
        $this->app->singleton(FileStorageService::class);
        $this->app->singleton(NotificationService::class);

        // If you need to use MultichainClient directly elsewhere, add a binding here.
        // Otherwise, MultichainService should be used for all blockchain operations.
    }

    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}
