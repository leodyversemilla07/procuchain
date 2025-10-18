<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccountLockoutService;
use App\Services\AdminAnalyticsService;
use App\Services\DashboardCacheKeys;
use App\Services\DashboardService;
use App\Services\LoginService;
use App\Services\MultichainService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends BaseDashboardController
{
    public function __construct(
        MultichainService $multichainService,
        DashboardService $dashboardService,
        private LoginService $loginLogger,
        private AccountLockoutService $accountLockout,
        private AdminAnalyticsService $analyticsService
    ) {
        parent::__construct($multichainService, $dashboardService);
    }

    /**
     * Get the role name for middleware and cache keys
     */
    protected function getRoleName(): string
    {
        return 'admin';
    }

    /**
     * Get the human-readable role label for logging
     */
    protected function getRoleLabel(): string
    {
        return 'Admin';
    }

    /**
     * Get the Inertia view name
     */
    protected function getViewName(): string
    {
        return 'admin/dashboard';
    }

    /**
     * Get additional dashboard data specific to admin (analytics)
     */
    protected function getAdditionalDashboardData($procurementsByKey, string $roleName): array
    {
        $userActivityAnalytics = Cache::remember(
            DashboardCacheKeys::userActivityAnalytics($roleName),
            now()->addMinutes(config('dashboard.cache_ttl.user_analytics')),
            fn () => $this->analyticsService->getUserActivityAnalytics('30_days', null)
        );

        return [
            'analytics' => [
                'user_activity' => $userActivityAnalytics,
            ],
        ];
    }

    /**
     * Get empty additional data for error responses
     */
    protected function getEmptyAdditionalData(): array
    {
        return [
            'analytics' => [
                'user_activity' => null,
            ],
        ];
    }

    /**
     * Display user management page
     */
    public function users(): Response
    {
        $users = User::select('id', 'name', 'email', 'blockchain_address', 'email_verified_at', 'remember_token', 'created_at', 'updated_at', 'account_locked', 'locked_at', 'lock_expires_at', 'failed_login_attempts', 'last_failed_login_at', 'locked_reason', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at')
            ->where('id', '!=', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()?->name ?? null,
                    'blockchain_address' => $user->blockchain_address,
                    'email_verified_at' => $user->email_verified_at?->format('Y-m-d H:i:s'),
                    'remember_token' => $user->remember_token,
                    'two_factor_secret' => $user->two_factor_secret,
                    'two_factor_recovery_codes' => $user->two_factor_recovery_codes ? json_decode($user->two_factor_recovery_codes, true) : null,
                    'two_factor_confirmed_at' => $user->two_factor_confirmed_at?->format('Y-m-d H:i:s'),
                    'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $user->updated_at?->format('Y-m-d H:i:s'),
                    'account_locked' => $user->account_locked,
                    'locked_at' => $user->locked_at?->format('Y-m-d H:i:s'),
                    'lock_expires_at' => $user->lock_expires_at?->format('Y-m-d H:i:s'),
                    'failed_login_attempts' => $user->failed_login_attempts,
                    'last_failed_login_at' => $user->last_failed_login_at?->format('Y-m-d H:i:s'),
                    'locked_reason' => $user->locked_reason,
                    'is_currently_locked' => $user->isAccountLocked(),
                ];
            });

        return Inertia::render('admin/user-management', [
            'users' => $users,
            'roles' => ['bac_secretariat', 'bac_chairman', 'hope', 'admin'],
        ]);
    }

    /**
     * Store a new user
     */
    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'role' => ['required', Rule::in(['bac_secretariat', 'bac_chairman', 'hope', 'admin'])],
            'password' => 'required|string|min:8|confirmed',
            'blockchain_address' => 'nullable|string|max:255',
        ]);
        try {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'blockchain_address' => ! empty($validated['blockchain_address']) ? $validated['blockchain_address'] : null,
            ]);

            // Assign role using Spatie Permission
            $user->assignRole($validated['role']);

            Log::info('Admin created new user', [
                'admin_id' => Auth::id(),
                'created_user_id' => $user->id,
                'user_email' => $user->email,
                'user_role' => $validated['role'],
            ]);

            return redirect()->back()->with('success', 'User created successfully.');
        } catch (Exception $e) {
            Log::error('Failed to create user', [
                'admin_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['error' => 'Failed to create user. Please try again.']);
        }
    }

    /**
     * Update an existing user
     */
    public function updateUser(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in(['bac_secretariat', 'bac_chairman', 'hope', 'admin'])],
            'blockchain_address' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:8|confirmed',
        ]);
        try {
            $updateData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'blockchain_address' => ! empty($validated['blockchain_address']) ? $validated['blockchain_address'] : null,
            ];

            if (! empty($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
            }

            $user->update($updateData);

            // Sync role using Spatie Permission - remove old roles and assign new one
            $user->syncRoles([$validated['role']]);

            Log::info('Admin updated user', [
                'admin_id' => Auth::id(),
                'updated_user_id' => $user->id,
                'user_email' => $user->email,
                'user_role' => $validated['role'],
            ]);

            return redirect()->back()->with('success', 'User updated successfully.');
        } catch (Exception $e) {
            Log::error('Failed to update user', [
                'admin_id' => Auth::id(),
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['error' => 'Failed to update user. Please try again.']);
        }
    }

    /**
     * Delete a user
     */
    public function destroyUser(User $user)
    {
        try {
            // Prevent admin from deleting their own account
            if ($user->id === Auth::id()) {
                return redirect()->back()->withErrors(['error' => 'You cannot delete your own account.']);
            }

            $userEmail = $user->email;
            $user->delete();
            Log::info('Admin deleted user', [
                'admin_id' => Auth::id(),
                'deleted_user_email' => $userEmail,
            ]);

            return redirect()->back()->with('success', 'User deleted successfully.');
        } catch (Exception $e) {
            Log::error('Failed to delete user', [
                'admin_id' => Auth::id(),
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['error' => 'Failed to delete user. Please try again.']);
        }
    }

    /**
     * Bulk delete users
     */
    public function bulkDeleteUsers(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'required|integer|exists:users,id',
        ]);

        try {
            $userIds = $validated['user_ids'];
            $currentUserId = Auth::id();

            // Remove current user's ID from the list to prevent self-deletion
            $userIds = array_filter($userIds, function ($id) use ($currentUserId) {
                return $id !== $currentUserId;
            });

            if (empty($userIds)) {
                return redirect()->back()->withErrors(['error' => 'Cannot delete your own account or no valid users selected.']);
            }

            // Get user details for logging before deletion
            $usersToDelete = User::whereIn('id', $userIds)->with('roles')->get(['id', 'email', 'name']);

            if ($usersToDelete->isEmpty()) {
                return redirect()->back()->withErrors(['error' => 'No valid users found for deletion.']);
            }

            // Perform bulk deletion within a transaction
            DB::transaction(function () use ($userIds, $usersToDelete, $currentUserId) {
                User::whereIn('id', $userIds)->delete();

                // Log the bulk deletion
                Log::info('Admin performed bulk user deletion', [
                    'admin_id' => $currentUserId,
                    'deleted_users' => $usersToDelete->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'email' => $user->email,
                            'name' => $user->name,
                            'role' => $user->roles->first()?->name ?? null,
                        ];
                    })->toArray(),
                    'total_deleted' => count($userIds),
                ]);
            });

            $deletedCount = count($userIds);
            $message = $deletedCount === 1 ? 'User deleted successfully.' : "{$deletedCount} users deleted successfully.";

            return redirect()->back()->with('success', $message);
        } catch (Exception $e) {
            Log::error('Failed to bulk delete users', [
                'admin_id' => Auth::id(),
                'user_ids' => $validated['user_ids'] ?? [],
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['error' => 'Failed to delete users. Please try again.']);
        }
    }

    /**
     * Display login logs page
     */
    public function loginLogs(): Response
    {
        try {
            $recentLogins = $this->loginLogger->getRecentLogins(100);
            $statistics = $this->loginLogger->getLoginStatistics();
            $suspiciousActivities = $this->loginLogger->getSuspiciousActivities();

            return Inertia::render('admin/login-logs', [
                'recentLogins' => $recentLogins,
                'statistics' => $statistics,
                'suspiciousActivities' => $suspiciousActivities,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to fetch login logs', [
                'admin_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return Inertia::render('admin/login-logs', [
                'recentLogins' => [],
                'statistics' => [],
                'suspiciousActivities' => [],
                'error' => 'Failed to load login logs. Please try again.',
            ]);
        }
    }

    /**
     * Get recent logins (API endpoint)
     */
    public function recentLogins(Request $request)
    {
        try {
            $limit = $request->get('limit', 50);
            $recentLogins = $this->loginLogger->getRecentLogins($limit);

            return response()->json([
                'success' => true,
                'data' => $recentLogins,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to fetch recent logins', [
                'admin_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch recent logins',
            ], 500);
        }
    }

    /**
     * Get login statistics (API endpoint)
     */
    public function loginStatistics()
    {
        try {
            $statistics = $this->loginLogger->getLoginStatistics();

            return response()->json([
                'success' => true,
                'data' => $statistics,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to fetch login statistics', [
                'admin_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch login statistics',
            ], 500);
        }
    }

    /**
     * Get suspicious activities (API endpoint)
     */
    public function suspiciousActivities()
    {
        try {
            $activities = $this->loginLogger->getSuspiciousActivities();

            return response()->json([
                'success' => true,
                'data' => $activities,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to fetch suspicious activities', [
                'admin_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch suspicious activities',
            ], 500);
        }
    }

    /**
     * Display locked accounts page
     */
    public function lockedAccounts(): Response
    {
        try {
            $lockedUsers = $this->accountLockout->getLockedAccounts();

            // Format the data for the frontend - the LoginTrackingService already returns formatted data
            $formattedUsers = $lockedUsers->map(function ($userData) {
                return [
                    'id' => $userData['id'],
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'role' => $userData['role'],
                    'two_factor_enabled' => $userData['two_factor_enabled'],
                    'two_factor_confirmed_at' => $userData['two_factor_confirmed_at']?->format('Y-m-d H:i:s'),
                    'account_locked' => true, // All users from getLockedAccounts are locked
                    'locked_at' => $userData['locked_at']?->format('Y-m-d H:i:s'),
                    'lock_expires_at' => $userData['lock_expires_at']?->format('Y-m-d H:i:s'),
                    'failed_login_attempts' => $userData['failed_attempts'],
                    'last_failed_login_at' => null, // Not provided in the service response
                    'locked_reason' => $userData['locked_reason'],
                    'is_currently_locked' => true, // All users from getLockedAccounts are currently locked
                    'lock_time_remaining' => $userData['time_remaining'],
                    'recent_failed_logins' => $userData['recent_failed_logins'],
                ];
            });

            return Inertia::render('admin/locked-accounts', [
                'lockedAccounts' => $formattedUsers,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to fetch locked accounts', [
                'admin_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return Inertia::render('admin/locked-accounts', [
                'lockedAccounts' => [],
                'error' => 'Failed to fetch locked accounts',
            ]);
        }
    }

    /**
     * Unlock a user account
     */
    public function unlockAccount(Request $request, User $user)
    {
        try {
            $validated = $request->validate([
                'reason' => 'nullable|string|max:255',
            ]);

            $result = $this->accountLockout->unlockAccount($user, $validated['reason'] ?? 'Manually unlocked by admin');

            if ($result) {
                Log::info('Admin unlocked user account', [
                    'admin_id' => Auth::id(),
                    'unlocked_user_id' => $user->id,
                    'unlocked_user_email' => $user->email,
                    'reason' => $validated['reason'] ?? 'Manually unlocked by admin',
                ]);

                // Return JSON response for API calls
                if ($request->expectsJson() || $request->is('admin/accounts/*/unlock')) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Account unlocked successfully',
                    ]);
                }

                return back()->with('success', "Account unlocked successfully for {$user->name}");
            } else {
                // Return JSON response for API calls
                if ($request->expectsJson() || $request->is('admin/accounts/*/unlock')) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Account was not locked or unlock failed',
                    ], 400);
                }

                return back()->with('error', 'Account was not locked or unlock failed');
            }
        } catch (Exception $e) {
            Log::error('Failed to unlock user account', [
                'admin_id' => Auth::id(),
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            // Return JSON response for API calls
            if ($request->expectsJson() || $request->is('admin/accounts/*/unlock')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to unlock account',
                ], 500);
            }

            return back()->with('error', 'Failed to unlock account');
        }
    }

    /**
     * Manually lock a user account
     */
    public function lockAccount(Request $request, User $user)
    {
        try {
            $validated = $request->validate([
                'reason' => 'required|string|max:255',
                'duration_hours' => 'nullable|integer|min:1|max:168', // Max 1 week
            ]);

            // Prevent admin from locking their own account
            if ($user->id === Auth::id()) {
                return response()->json([
                    'success' => false,
                    'error' => 'You cannot lock your own account',
                ], 400);
            }

            $durationHours = $validated['duration_hours'] ?? 24; // Default 24 hours
            $result = $this->accountLockout->lockAccount($user, $validated['reason'], $durationHours);

            if ($result) {
                Log::info('Admin manually locked user account', [
                    'admin_id' => Auth::id(),
                    'locked_user_id' => $user->id,
                    'locked_user_email' => $user->email,
                    'reason' => $validated['reason'],
                    'duration_hours' => $durationHours,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Account locked successfully',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Account is already locked or lock failed',
                ], 400);
            }
        } catch (Exception $e) {
            Log::error('Failed to lock user account', [
                'admin_id' => Auth::id(),
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to lock account',
            ], 500);
        }
    }

    /**
     * Reset failed login attempts for a user
     */
    public function resetFailedAttempts(User $user)
    {
        try {
            $result = $this->accountLockout->resetFailedAttempts($user);

            if ($result) {
                Log::info('Admin reset failed login attempts', [
                    'admin_id' => Auth::id(),
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                ]);

                // Return JSON response for API calls
                if (request()->expectsJson() || request()->is('admin/accounts/*/reset-attempts')) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Failed login attempts reset successfully',
                    ]);
                }

                return back()->with('success', "Failed login attempts reset successfully for {$user->name}");
            } else {
                // Return JSON response for API calls
                if (request()->expectsJson() || request()->is('admin/accounts/*/reset-attempts')) {
                    return response()->json([
                        'success' => false,
                        'error' => 'No failed attempts to reset',
                    ], 400);
                }

                return back()->with('error', 'No failed attempts to reset');
            }
        } catch (Exception $e) {
            Log::error('Failed to reset failed login attempts', [
                'admin_id' => Auth::id(),
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            // Return JSON response for API calls
            if (request()->expectsJson() || request()->is('admin/accounts/*/reset-attempts')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to reset failed attempts',
                ], 500);
            }

            return back()->with('error', 'Failed to reset failed attempts');
        }
    }
}
