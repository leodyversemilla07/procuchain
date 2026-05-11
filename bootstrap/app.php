<?php

use App\Enums\StageEnums;
use App\Http\Middleware\CheckBlockedIp;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Sentry\Laravel\Integration;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Custom route model binding for StageEnums to handle kebab-case URLs
            Route::bind('stage', function (string $value) {
                return StageEnums::fromSlug($value)
                    ?? throw new NotFoundHttpException("Stage not found: {$value}");
            });
        },
    )
 ->withMiddleware(function (Middleware $middleware) {
 $middleware->encryptCookies(except: ['appearance']);

 // Trust the Elastic Beanstalk ALB proxy
 $middleware->trustProxies(at: '*');

 $middleware->web(append: [
            SecurityHeaders::class,
            CheckBlockedIp::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Configure rate limiters for blockchain operations (Issue #18 fix)
        // Use database-based throttling (matches cache driver configuration)
        $middleware->throttleApi();

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
        // Disabled: Only needed if using Redis for rate limiting
        // $schedule->command('redis:monitor-memory --warn-threshold=80')
        //     ->everyThirtyMinutes()
        //     ->onFailure(function () {
        //         Log::warning('Redis memory usage exceeded 80% threshold');
        //     });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            // In local environment, we want to see the actual error for 500s (Server Errors)
            // but we can show the custom page for 404s, 403s, etc.
            if (app()->environment('local') && $status === 500) {
                return $response;
            }

            if (in_array($status, [500, 503, 404, 403, 401, 419, 429])) {
                return Inertia::render('error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
