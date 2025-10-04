<?php

namespace App\Providers;

use App\Http\Controllers\Controller;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\PasswordConfirmedResponse;
use Laravel\Fortify\Contracts\RecoveryCodesGeneratedResponse as RecoveryCodesGeneratedResponseContract;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider as TwoFactorAuthenticationProviderContract;
use Laravel\Fortify\Contracts\TwoFactorConfirmedResponse as TwoFactorConfirmedResponseContract;
use Laravel\Fortify\Contracts\TwoFactorDisabledResponse as TwoFactorDisabledResponseContract;
use Laravel\Fortify\Contracts\TwoFactorEnabledResponse as TwoFactorEnabledResponseContract;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Responses\RecoveryCodesGeneratedResponse;
use Laravel\Fortify\Http\Responses\TwoFactorConfirmedResponse;
use Laravel\Fortify\Http\Responses\TwoFactorDisabledResponse;
use Laravel\Fortify\Http\Responses\TwoFactorEnabledResponse;
use Laravel\Fortify\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind StatefulGuard - required by Fortify
        $this->app->bind(StatefulGuard::class, function () {
            return Auth::guard(config('fortify.guard', null));
        });

        // Bind TwoFactorAuthenticationProvider - required by Fortify
        $this->app->singleton(TwoFactorAuthenticationProviderContract::class, function ($app) {
            return new TwoFactorAuthenticationProvider(
                $app->make(Google2FA::class),
                $app->make(Repository::class)
            );
        });

        // Register Fortify response bindings
        $this->registerResponseBindings();

        // Customize password confirmation response to redirect to role-specific dashboard
        $this->app->singleton(PasswordConfirmedResponse::class, function () {
            return new class extends Controller implements PasswordConfirmedResponse
            {
                public function toResponse($request)
                {
                    return $request->wantsJson()
                        ? new JsonResponse('', 201)
                        : redirect()->intended($this->redirectToDashboard());
                }
            };
        });
    }

    /**
     * Register the Fortify response bindings.
     */
    protected function registerResponseBindings(): void
    {
        $this->app->singleton(TwoFactorEnabledResponseContract::class, TwoFactorEnabledResponse::class);
        $this->app->singleton(TwoFactorConfirmedResponseContract::class, TwoFactorConfirmedResponse::class);
        $this->app->singleton(TwoFactorDisabledResponseContract::class, TwoFactorDisabledResponse::class);
        $this->app->singleton(RecoveryCodesGeneratedResponseContract::class, RecoveryCodesGeneratedResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load Fortify routes
        $this->loadFortifyRoutes();

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }

    /**
     * Load Fortify routes.
     */
    protected function loadFortifyRoutes(): void
    {
        if (Fortify::$registersRoutes) {
            Route::group([
                'namespace' => 'Laravel\Fortify\Http\Controllers',
                'domain' => config('fortify.domain', null),
                'prefix' => config('fortify.prefix'),
            ], function () {
                $routesPath = base_path('vendor/laravel/fortify/routes/routes.php');
                if (file_exists($routesPath)) {
                    require $routesPath;
                }
            });
        }
    }
}
