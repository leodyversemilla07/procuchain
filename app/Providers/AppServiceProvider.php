<?php

namespace App\Providers;

use App\Contracts\CacheStrategyInterface;
use App\Libraries\MultiChain\Contracts\MultiChainManagerInterface;
use App\Libraries\MultiChain\Manager;
use App\Services\CacheStrategyService;
use App\Services\NotificationService;
use App\Services\ProcurementStageTransitionService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register MultiChain Manager interface binding
        $this->app->singleton(MultiChainManagerInterface::class, Manager::class);

        // Register MultiChain Manager as singleton (for backward compatibility)
        $this->app->singleton(Manager::class);

        // Add contextual binding to resolve any Manager reference to the MultiChain Manager
        // This helps Wayfinder and other tools that might try to resolve Manager
        $this->app->when('*')
            ->needs('Manager')
            ->give(Manager::class);

        // Register core services as singletons
        $this->app->singleton(ProcurementStageTransitionService::class);
        $this->app->singleton(FileStorageService::class);
        $this->app->singleton(NotificationService::class);

        // Register CacheStrategyInterface binding
        $this->app->singleton(CacheStrategyInterface::class, CacheStrategyService::class);
    }

    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}
