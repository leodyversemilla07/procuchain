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
        $this->app->singleton(MultichainService::class);
        $this->app->singleton(StreamKeyService::class);
        $this->app->singleton(ProcurementStageTransitionService::class);
        $this->app->singleton(EventTypeLabelMapper::class);

        $this->app->bind(FileStorageService::class);
        $this->app->bind(NotificationService::class);
    }

    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}
