<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance']);

        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\CheckBlockedIp::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

    })
    ->withSchedule(function (Schedule $schedule) {
        // Blockchain reconciliation - verify pending documents against blockchain every hour
        $schedule->command('blockchain:reconcile --age=2 --limit=500')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();

        // Clean up old cache and session data to optimize database storage
        $schedule->command('cache:cleanup --hours=24')
            ->daily();

        // Clean up old cache tags
        $schedule->command('cache:prune-stale-tags')
            ->hourly();

        // Monitor Redis memory usage (important for 30MB free tier)
        $schedule->command('redis:monitor-memory --warn-threshold=80')
            ->everyThirtyMinutes()
            ->onFailure(function () {
                Log::warning('Redis memory usage exceeded 80% threshold');
            });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);
    })->create();
