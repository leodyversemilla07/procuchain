<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\DocumentViewLog;
use App\Models\ProcurementWorkflowConfig;
use App\Models\StageDocumentConfig;
use App\Models\UserLoginLog;
use App\Observers\AuditLogObserver;
use App\Observers\DocumentViewObserver;
use App\Observers\ProcurementWorkflowConfigObserver;
use App\Observers\StageDocumentConfigObserver;
use App\Observers\UserLoginLogObserver;
use App\Policies\AuditLogPolicy;
use App\Policies\BlockchainPolicy;
use App\Policies\DashboardPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\LoginLogPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\ProcurementPolicy;
use App\Policies\ReportPolicy;
use App\Policies\SettingsPolicy;
use App\Policies\UserPolicy;
use App\Services\AuditLogService;
use App\Services\BlockchainRecordSyncService;
use App\Services\BlockchainRpcClient;
use App\Services\BlockchainStorageService;
use App\Services\IntegrityVerificationService;
use App\Services\NotificationService;
use App\Services\ProcurementStageTransitionService;
use App\Services\WorkflowDefinitionService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register MultiChain BlockchainRpcClient as singleton
        $this->app->singleton(BlockchainRpcClient::class);

        // Register core services as singletons
        $this->app->singleton(ProcurementStageTransitionService::class);
        $this->app->singleton(BlockchainStorageService::class);
        $this->app->singleton(NotificationService::class);
        $this->app->singleton(WorkflowDefinitionService::class);

        // Integrity verification stores per-run counters, so each resolution must be fresh.
        $this->app->bind(IntegrityVerificationService::class);

        // Blockchain mirror sync (repair from chain, full rebuild)
        $this->app->singleton(BlockchainRecordSyncService::class);
    }

    public function boot(): void
    {
        // Register observers for blockchain-backed tables
        AuditLog::observe(AuditLogObserver::class);
        DocumentViewLog::observe(DocumentViewObserver::class);
        UserLoginLog::observe(UserLoginLogObserver::class);
        ProcurementWorkflowConfig::observe(ProcurementWorkflowConfigObserver::class);
        StageDocumentConfig::observe(StageDocumentConfigObserver::class);

        if (config('app.env') === 'production' && config('app.force_https', false)) {
            URL::forceScheme('https');
        }

        // Implicitly grant "admin" role all permissions (Spatie best practice).
        // Must return null (not false) to avoid interfering with normal policy checks.
        // However, certain self-targeting abilities must still go through policy
        // checks (e.g., admin cannot delete themselves, reset own password, etc.).
        Gate::before(function ($user, $ability) {
            if (! $user->hasRole(UserRole::ADMIN->value)) {
                return null;
            }

            // These abilities need to respect policy-level self-action guards
            // (both the Laravel policy method names and our named gate equivalents)
            $selfRestrictedAbilities = [
                'delete', 'delete-user',
                'forceDelete', 'force-delete-user',
                'resetPassword', 'reset-user-password',
                'assignRoles', 'assign-user-roles',
            ];

            if (in_array($ability, $selfRestrictedAbilities)) {
                return null; // Let the policy decide (policy handles self-checks)
            }

            return true;
        });

        // ──────────────────────────────────────────────────────────────
        // Procurement Gates (delegated to ProcurementPolicy)
        // ──────────────────────────────────────────────────────────────
        Gate::define('view-procurement', [ProcurementPolicy::class, 'view']);
        Gate::define('create-procurement', [ProcurementPolicy::class, 'create']);
        Gate::define('initiate-procurement', [ProcurementPolicy::class, 'create']);
        Gate::define('archive-procurement', [ProcurementPolicy::class, 'archive']);
        Gate::define('restore-procurement', [ProcurementPolicy::class, 'restore']);
        Gate::define('correct-procurement', [ProcurementPolicy::class, 'correct']);
        Gate::define('approve-procurement', [ProcurementPolicy::class, 'approve']);
        Gate::define('publish-procurement', [ProcurementPolicy::class, 'publish']);

        // ──────────────────────────────────────────────────────────────
        // Document Gates (delegated to DocumentPolicy)
        // ──────────────────────────────────────────────────────────────
        Gate::define('view-document', [DocumentPolicy::class, 'view']);
        Gate::define('download-document', [DocumentPolicy::class, 'download']);
        Gate::define('upload-document', [DocumentPolicy::class, 'upload']);
        Gate::define('correct-document', [DocumentPolicy::class, 'correct']);

        // ──────────────────────────────────────────────────────────────
        // User Gates (delegated to UserPolicy)
        // ──────────────────────────────────────────────────────────────
        Gate::define('view-any-user', [UserPolicy::class, 'viewAny']);
        Gate::define('view-user', [UserPolicy::class, 'view']);
        Gate::define('create-user', [UserPolicy::class, 'create']);
        Gate::define('update-user', [UserPolicy::class, 'update']);
        Gate::define('delete-user', [UserPolicy::class, 'delete']);
        Gate::define('delete-any-user', [UserPolicy::class, 'deleteAny']);
        Gate::define('restore-user', [UserPolicy::class, 'restore']);
        Gate::define('force-delete-user', [UserPolicy::class, 'forceDelete']);
        Gate::define('reset-user-password', [UserPolicy::class, 'resetPassword']);
        Gate::define('assign-user-roles', [UserPolicy::class, 'assignRoles']);
        Gate::define('unlock-user-account', [UserPolicy::class, 'unlockAccount']);

        // ──────────────────────────────────────────────────────────────
        // Dashboard Gates (delegated to DashboardPolicy)
        // ──────────────────────────────────────────────────────────────
        Gate::define('view-admin-dashboard', [DashboardPolicy::class, 'viewAdmin']);
        Gate::define('view-bac-secretariat-dashboard', [DashboardPolicy::class, 'viewBacSecretariat']);
        Gate::define('view-bac-chairman-dashboard', [DashboardPolicy::class, 'viewBacChairman']);
        Gate::define('view-hope-dashboard', [DashboardPolicy::class, 'viewHope']);

        // ──────────────────────────────────────────────────────────────
        // Blockchain Gates (delegated to BlockchainPolicy)
        // ──────────────────────────────────────────────────────────────
        Gate::define('view-blockchain-explorer', [BlockchainPolicy::class, 'viewExplorer']);
        Gate::define('view-blockchain-transactions', [BlockchainPolicy::class, 'viewTransactions']);
        Gate::define('view-blockchain-network', [BlockchainPolicy::class, 'viewNetwork']);
        Gate::define('reset-blockchain-circuit-breaker', [BlockchainPolicy::class, 'resetCircuitBreaker']);
        Gate::define('view-shared-ledger', [BlockchainPolicy::class, 'viewSharedLedger']);

        // ──────────────────────────────────────────────────────────────
        // Audit Log Gates (delegated to AuditLogPolicy)
        // ──────────────────────────────────────────────────────────────
        Gate::define('view-audit-log', [AuditLogPolicy::class, 'viewAny']);
        Gate::define('update-audit-log', [AuditLogPolicy::class, 'update']);

        // ──────────────────────────────────────────────────────────────
        // Login Log Gates (delegated to LoginLogPolicy)
        // ──────────────────────────────────────────────────────────────
        Gate::define('view-login-logs', [LoginLogPolicy::class, 'viewAny']);
        Gate::define('manage-blocked-ips', [LoginLogPolicy::class, 'manageBlockedIps']);

        // ──────────────────────────────────────────────────────────────
        // Notification Gates (delegated to NotificationPolicy)
        // ──────────────────────────────────────────────────────────────
        Gate::define('view-notifications', [NotificationPolicy::class, 'view']);
        Gate::define('manage-notifications', [NotificationPolicy::class, 'manage']);

        // ──────────────────────────────────────────────────────────────
        // Report Gates (delegated to ReportPolicy)
        // ──────────────────────────────────────────────────────────────
        Gate::define('view-reports', [ReportPolicy::class, 'view']);
        Gate::define('generate-reports', [ReportPolicy::class, 'generate']);
        Gate::define('export-reports', [ReportPolicy::class, 'export']);

        // ──────────────────────────────────────────────────────────────
        // Settings Gates (delegated to SettingsPolicy)
        // ──────────────────────────────────────────────────────────────
        Gate::define('view-settings', [SettingsPolicy::class, 'view']);
        Gate::define('manage-settings', [SettingsPolicy::class, 'manage']);
        Gate::define('manage-workflow-config', [SettingsPolicy::class, 'manageWorkflowConfig']);
        Gate::define('manage-stage-document-config', [SettingsPolicy::class, 'manageStageDocumentConfig']);
        Gate::define('manage-user-invitations', [SettingsPolicy::class, 'manageUserInvitations']);
        Gate::define('view-workflow', [SettingsPolicy::class, 'viewWorkflow']);

        // Register custom rate limiter for blockchain writes (Issue #20: use config)
        RateLimiter::for('blockchain_writes', function ($request) {
            // Load limit from config (Issue #20 fix)
            $limit = config('blockchain.rate_limiting.writes_per_minute', 10);

            // Use unlimited rate limit during testing to prevent test failures
            if ($this->app->environment('testing')) {
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

        // ──────────────────────────────────────────────────────────────
        // 2FA Fortify Event Hooks — Audit Logging (NGPA compliance)
        // ──────────────────────────────────────────────────────────────
        Event::listen(TwoFactorAuthenticationEnabled::class, function ($event) {
            app(AuditLogService::class)->log(
                'settings.two_factor_enabled',
                'user',
                (string) $event->user->id,
            );
        });

        Event::listen(TwoFactorAuthenticationConfirmed::class, function ($event) {
            app(AuditLogService::class)->log(
                'settings.two_factor_confirmed',
                'user',
                (string) $event->user->id,
            );
        });

        Event::listen(TwoFactorAuthenticationDisabled::class, function ($event) {
            app(AuditLogService::class)->log(
                'settings.two_factor_disabled',
                'user',
                (string) $event->user->id,
            );
        });
    }
}
