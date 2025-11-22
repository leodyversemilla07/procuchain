<?php

namespace App\Providers;

use App\Contracts\CacheStrategyInterface;
use App\Services\BlockchainStorageService;
use App\Services\CacheStrategyService;
use App\Services\Manager;
use App\Services\NotificationService;
use App\Services\ProcurementStageTransitionService;
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

        // Register CacheStrategyInterface binding
        $this->app->singleton(CacheStrategyInterface::class, CacheStrategyService::class);
    }

    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

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
            return \Illuminate\Cache\RateLimiting\Limit::perMinute($limit)
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
