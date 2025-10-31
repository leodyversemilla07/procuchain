<?php

namespace App\Providers;

use App\Contracts\CacheStrategyInterface;
use App\Services\CacheStrategyService;
use App\Services\EventTypeLabelMapper;
use App\Services\FileStorageService;
use App\Services\MultichainConnectionService;
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
        $this->app->singleton(MultichainConnectionService::class);
        $this->app->singleton(MultichainService::class);
        $this->app->singleton(StreamKeyService::class);
        $this->app->singleton(ProcurementStageTransitionService::class);
        $this->app->singleton(EventTypeLabelMapper::class);
        $this->app->singleton(FileStorageService::class);
        $this->app->singleton(NotificationService::class);

        // Register CacheStrategyInterface binding
        $this->app->singleton(CacheStrategyInterface::class, CacheStrategyService::class);

        // MultichainService uses MultichainConnectionService for connection management.
        // Use MultichainService for all blockchain operations.
    }

    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}
