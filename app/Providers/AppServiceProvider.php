<?php

namespace App\Providers;

use App\Contracts\BlockchainStorageInterface;
use App\Contracts\CacheStrategyInterface;
use App\Contracts\CorrectionRepositoryInterface;
use App\Contracts\DocumentPublisherInterface;
use App\Contracts\DocumentRepositoryInterface;
use App\Contracts\EventPublisherInterface;
use App\Contracts\NotificationServiceInterface;
use App\Contracts\ProcurementCorrectionRepositoryInterface;
use App\Contracts\ProcurementRepositoryInterface;
use App\Contracts\StatusPublisherInterface;
use App\Policies\DocumentPolicy;
use App\Policies\ProcurementPolicy;
use App\Repositories\CorrectionRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\ProcurementCorrectionRepository;
use App\Repositories\ProcurementRepository;
use App\Services\BlockchainStorageService;
use App\Services\CacheStrategyService;
use App\Services\Manager;
use App\Services\NotificationService;
use App\Services\ProcurementStageTransitionService;
use App\Services\Publishers\DocumentPublisher;
use App\Services\Publishers\EventPublisher;
use App\Services\Publishers\StatusPublisher;
use App\Services\WorkflowDefinitionService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register MultiChain Manager as singleton
        $this->app->singleton(Manager::class);

        // Register core services as singletons
        $this->app->singleton(ProcurementStageTransitionService::class);
        $this->app->singleton(BlockchainStorageService::class);
        $this->app->singleton(NotificationService::class);
        $this->app->singleton(WorkflowDefinitionService::class);

        // Register interface bindings - Cache
        $this->app->singleton(CacheStrategyInterface::class, CacheStrategyService::class);

        // Register interface bindings - Repositories
        $this->app->bind(ProcurementRepositoryInterface::class, ProcurementRepository::class);
        $this->app->bind(DocumentRepositoryInterface::class, DocumentRepository::class);
        $this->app->bind(CorrectionRepositoryInterface::class, CorrectionRepository::class);
        $this->app->bind(ProcurementCorrectionRepositoryInterface::class, ProcurementCorrectionRepository::class);

        // Register interface bindings - Publishers
        $this->app->bind(DocumentPublisherInterface::class, DocumentPublisher::class);
        $this->app->bind(StatusPublisherInterface::class, StatusPublisher::class);
        $this->app->bind(EventPublisherInterface::class, EventPublisher::class);

        // Register interface bindings - Services
        $this->app->singleton(BlockchainStorageInterface::class, BlockchainStorageService::class);
        $this->app->singleton(NotificationServiceInterface::class, NotificationService::class);
    }

    public function boot(): void
    {
        if (config('app.env') === 'production' && config('app.force_https', false)) {
            URL::forceScheme('https');
        }

        // Register procurement policy abilities as named Gates (no Eloquent model binding).
        Gate::define('view-procurement', [ProcurementPolicy::class, 'view']);
        Gate::define('create-procurement', [ProcurementPolicy::class, 'create']);
        Gate::define('initiate-procurement', [ProcurementPolicy::class, 'create']);
        Gate::define('archive-procurement', [ProcurementPolicy::class, 'archive']);
        Gate::define('restore-procurement', [ProcurementPolicy::class, 'restore']);
        Gate::define('correct-procurement', [ProcurementPolicy::class, 'correct']);
        Gate::define('approve-procurement', [ProcurementPolicy::class, 'approve']);
        Gate::define('publish-procurement', [ProcurementPolicy::class, 'publish']);

        // Register document policy abilities as named Gates.
        Gate::define('view-document', [DocumentPolicy::class, 'view']);
        Gate::define('download-document', [DocumentPolicy::class, 'download']);
        Gate::define('upload-document', [DocumentPolicy::class, 'upload']);
        Gate::define('correct-document', [DocumentPolicy::class, 'correct']);

        // Register custom rate limiter for blockchain writes (Issue #20: use config)
        RateLimiter::for('blockchain_writes', function ($request) {
            // Load limit from config (Issue #20 fix)
            $limit = config('blockchain.rate_limiting.writes_per_minute', 10);

            // Use unlimited rate limit during testing to prevent test failures
            if (app()->environment('testing')) {
                $limit = 1000; // High limit for tests
            }

            // Per-user rate limiting for blockchain write operations
            // Prevents abuse and protects blockchain node from overload
            // Uses database cache driver to avoid Redis dependency
            return Limit::perMinute($limit)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function ($request, $headers) use ($limit) {
                    // Handle Inertia requests by redirecting back with error message
                    if ($request->header('X-Inertia')) {
                        return back()->with('error', 'Too many blockchain operations. Please wait a moment before trying again.');
                    }

                    return response()->json([
                        'error' => 'Too many blockchain operations. Please wait a moment before trying again.',
                        'retry_after' => 60,
                        'limit' => $limit,
                    ], 429, $headers);
                });
        });
    }
}
