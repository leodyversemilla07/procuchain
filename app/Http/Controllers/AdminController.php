<?php

namespace App\Http\Controllers;

use App\Enums\StreamEnums;
use App\Models\User;
use App\Services\LoginTrackingService;
use App\Services\ProcurementServices;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminController extends BaseController
{
    private $services;

    private LoginTrackingService $loginTracker;

    private array $userNameCache = [];

    public function __construct(ProcurementServices $services, LoginTrackingService $loginTracker)
    {
        $this->services = $services;
        $this->loginTracker = $loginTracker;
        $this->middleware('auth');
        $this->middleware('role:admin');
    }

    private function getUserName(string $address): string
    {
        if (isset($this->userNameCache[$address])) {
            return $this->userNameCache[$address];
        }

        try {
            $name = User::where('blockchain_address', $address)->first()?->name ?? 'Unknown';
        } catch (Exception $e) {
            Log::warning("Failed to retrieve user name for address: $address", ['error' => $e->getMessage()]);
            $name = 'Unknown';
        }

        return $this->userNameCache[$address] = $name;
    }

    public function index()
    {
        try {
            Log::info('Fetching Admin dashboard data');

            $procurementsByKey = Cache::remember('admin_dashboard_procurements_by_key', now()->addMinutes(5), function () {
                Log::info('Cache miss: Recalculating procurementsByKey for Admin dashboard');
                $states = $this->services->getMultiChain()->listStreamItems(
                    StreamEnums::STATUS->value,
                    true,
                    10000,
                    0,
                    false
                );
                if ($states === null) {
                    throw new Exception('Failed to retrieve status stream items for Admin procurementsByKey cache');
                }

                return $this->getProcurementsByKey($states);
            });

            if ($procurementsByKey === null || $procurementsByKey->isEmpty()) {
                Log::warning('Admin ProcurementsByKey is null or empty after cache check.');
                $procurementsByKey = collect();
            }

            $recentActivities = Cache::remember('admin_dashboard_recent_activities', now()->addMinutes(2), function () {
                return $this->getRecentActivities();
            });

            $stats = Cache::remember('admin_dashboard_stats', now()->addMinutes(5), function () use ($procurementsByKey) {
                Log::info('Cache miss: Recalculating Admin dashboard stats');

                return $this->getDashboardStats($procurementsByKey, 0);
            });

            $dashboardData = [
                'recentProcurements' => $this->getRecentProcurements($procurementsByKey),
                'recentActivities' => $recentActivities,
                'stats' => $stats,
            ];

            Log::info('Successfully retrieved Admin dashboard data');

            return Inertia::render('admin/dashboard', $dashboardData);
        } catch (Exception $e) {
            Log::error('Failed to retrieve Admin dashboard data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Cache::forget('admin_dashboard_procurements_by_key');
            Cache::forget('admin_dashboard_recent_activities');
            Cache::forget('admin_dashboard_stats');
            Cache::forget('admin_dashboard_total_documents');

            return Inertia::render('admin/dashboard', [
                'recentProcurements' => [],
                'recentActivities' => [],
                'stats' => $this->getEmptyStats(),
                'error' => 'Failed to retrieve dashboard data. Please try again later.',
            ]);
        }
    }

    private function getDashboardStats($procurementsByKey, int $pendingActions): array
    {
        try {
            $totalDocuments = Cache::remember('admin_dashboard_total_documents', now()->addMinutes(5), function () use ($procurementsByKey) {
                Log::info('Cache miss: Recalculating total documents for Admin dashboard stats');

                return $this->getTotalDocuments($procurementsByKey);
            });

            return [
                'ongoingProjects' => $this->countOngoingProjects($procurementsByKey),
                'pendingActions' => $pendingActions,
                'completedBiddings' => $this->countCompletedBiddings($procurementsByKey),
                'totalDocuments' => $totalDocuments,
            ];
        } catch (Exception $e) {
            Log::error('Failed to calculate Admin dashboard stats', ['error' => $e->getMessage()]);
            Cache::forget('admin_dashboard_total_documents');

            return $this->getEmptyStats();
        }
    }

    private function getEmptyStats(): array
    {
        return [
            'ongoingProjects' => 0,
            'pendingActions' => 0,
            'completedBiddings' => 0,
            'totalDocuments' => 0,
        ];
    }

    private function countOngoingProjects($procurementsByKey): int
    {
        return $procurementsByKey->filter(function ($item) {
            return $item['stage'] !== 'Monitoring' ||
                ($item['stage'] === 'Monitoring' && $item['status'] !== 'Completed');
        })->count();
    }

    private function getProcurementsByKey($allStates)
    {
        try {
            return collect($allStates)
                ->map(function ($item) {
                    $data = $item['data']['json'] ?? [];
                    if (! isset($data['procurement_id'], $data['procurement_title'])) {
                        Log::warning('Invalid procurement data structure in Admin context', ['data' => $data]);

                        return null;
                    }

                    return [
                        'id' => $data['procurement_id'],
                        'title' => $data['procurement_title'],
                        'stage' => $data['stage'] ?? '',
                        'status' => $data['current_status'] ?? $data['stage'] ?? '',
                        'user_address' => $data['user_address'] ?? '',
                        'user' => $this->getUserName($data['user_address'] ?? ''),
                        'timestamp' => $data['timestamp'] ?? '',
                    ];
                })
                ->filter()
                ->groupBy('id')
                ->map(function ($group) {
                    return $group->sortByDesc('timestamp')->first();
                });
        } catch (Exception $e) {
            Log::error('Error processing procurement data for Admin', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function getRecentProcurements($procurementsByKey)
    {
        return $procurementsByKey->sortByDesc('timestamp')
            ->take(5)
            ->values()
            ->map(function ($item) {
                return [
                    'id' => $item['id'],
                    'title' => $item['title'],
                    'stage' => $item['stage'],
                    'status' => $item['status'],
                ];
            })
            ->toArray();
    }

    private function getRecentActivities()
    {
        try {
            $allEvents = $this->services->getMultiChain()->listStreamItems(
                StreamEnums::EVENTS->value,
                true,
                20,
                -20,
                true
            );

            if (! $allEvents) {
                Log::warning('No events found in stream for Admin dashboard');

                return [];
            }

            return collect($allEvents)
                ->map(function ($item) {
                    $data = $item['data']['json'] ?? [];
                    if (! isset($data['procurement_id'], $data['procurement_title'])) {
                        return null;
                    }
                    $actionLabel = $this->services->getEventTypeLabelMapper()->getLabel(
                        $data['event_type'] ?? '',
                        $data['details'] ?? ''
                    );

                    return [
                        'id' => $data['procurement_id'],
                        'title' => $data['procurement_title'],
                        'action' => $actionLabel,
                        'details' => $data['details'] ?? '',
                        'raw_event_type' => $data['event_type'] ?? '',
                        'stage' => $data['stage_identifier'] ?? '',
                        'date' => $data['timestamp'] ?? now()->toIso8601String(),
                        'user' => $this->getUserName($data['user_address'] ?? ''),
                        'timestamp' => strtotime($data['timestamp'] ?? 'now'),
                    ];
                })
                ->filter()
                ->sortByDesc('timestamp')
                ->take(8)
                ->values()
                ->toArray();
        } catch (Exception $e) {
            Log::error('Failed to retrieve recent activities for Admin', [
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    private function getTotalDocuments($procurementsByKey)
    {
        try {
            $client = $this->services->getMultiChain();
            $documentItems = $client->listStreamItems(
                StreamEnums::DOCUMENTS->value,
                true,
                2000,
                0,
                false
            );

            if ($documentItems === null) {
                Log::warning('Failed to retrieve document stream items for Admin dashboard stats.');

                return 0;
            }

            $documentCountMap = collect($documentItems)
                ->filter(fn ($item) => isset($item['data']['json']['procurement_id']) && isset($item['data']['json']['hash']))
                ->groupBy(fn ($item) => $item['data']['json']['procurement_id'])
                ->map(function ($items) {
                    return collect($items)->map(fn ($item) => $item['data']['json']['hash'])->unique()->count();
                });

            $dashboardProcurementIds = $procurementsByKey->keys();
            $totalDocuments = $documentCountMap
                ->filter(fn ($count, $procurementId) => $dashboardProcurementIds->contains($procurementId))
                ->sum();

            Log::info('Admin dashboard document count calculated', ['total_documents' => $totalDocuments]);

            return $totalDocuments;
        } catch (Exception $e) {
            Log::error('Failed to calculate total documents for Admin dashboard', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 0;
        }
    }

    private function countCompletedBiddings($procurementsByKey)
    {
        return $procurementsByKey->filter(function ($item) {
            return in_array($item['stage'], [
                'Notice Of Award',
                'Performance Bond',
                'Contract And PO',
                'Notice To Proceed',
                'Monitoring',
                'Completed',
            ]);
        })->count();
    }

    /**
     * Display user management page
     */
    public function users()
    {
        $users = User::select('id', 'name', 'email', 'role', 'blockchain_address', 'email_verified_at', 'remember_token', 'created_at', 'updated_at', 'account_locked', 'locked_at', 'lock_expires_at', 'failed_login_attempts', 'last_failed_login_at', 'locked_reason', 'mfa_enabled', 'mfa_enabled_at', 'backup_codes', 'backup_codes_generated_at')
            ->where('id', '!=', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'blockchain_address' => $user->blockchain_address,
                    'email_verified_at' => $user->email_verified_at?->format('Y-m-d H:i:s'),
                    'remember_token' => $user->remember_token,
                    'mfa_enabled' => $user->mfa_enabled,
                    'mfa_enabled_at' => $user->mfa_enabled_at?->format('Y-m-d H:i:s'),
                    'backup_codes' => $user->backup_codes,
                    'backup_codes_generated_at' => $user->backup_codes_generated_at?->format('Y-m-d H:i:s'),
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

        return Inertia::render('admin/users', [
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
                'role' => $validated['role'],
                'password' => Hash::make($validated['password']),
                'blockchain_address' => ! empty($validated['blockchain_address']) ? $validated['blockchain_address'] : null,
            ]);
            Log::info('Admin created new user', [
                'admin_id' => Auth::id(),
                'created_user_id' => $user->id,
                'user_email' => $user->email,
                'user_role' => $user->role,
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
                'role' => $validated['role'],
                'blockchain_address' => ! empty($validated['blockchain_address']) ? $validated['blockchain_address'] : null,
            ];

            if (! empty($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
            }

            $user->update($updateData);
            Log::info('Admin updated user', [
                'admin_id' => Auth::id(),
                'updated_user_id' => $user->id,
                'user_email' => $user->email,
                'user_role' => $user->role,
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
            $usersToDelete = User::whereIn('id', $userIds)->get(['id', 'email', 'name', 'role']);

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
                            'role' => $user->role,
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
    public function loginLogs()
    {
        try {
            $recentLogins = $this->loginTracker->getRecentLogins(100);
            $statistics = $this->loginTracker->getLoginStatistics();
            $suspiciousActivities = $this->loginTracker->getSuspiciousActivities();

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

            return redirect()->back()->withErrors(['error' => 'Failed to load login logs. Please try again.']);
        }
    }

    /**
     * Get recent logins (API endpoint)
     */
    public function recentLogins(Request $request)
    {
        try {
            $limit = $request->get('limit', 50);
            $recentLogins = $this->loginTracker->getRecentLogins($limit);

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
            $statistics = $this->loginTracker->getLoginStatistics();

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
            $activities = $this->loginTracker->getSuspiciousActivities();

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
    public function lockedAccounts()
    {
        try {
            $lockedUsers = $this->loginTracker->getLockedAccounts();

            // Format the data for the frontend - the LoginTrackingService already returns formatted data
            $formattedUsers = $lockedUsers->map(function ($userData) {
                return [
                    'id' => $userData['id'],
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'role' => $userData['role'],
                    'mfa_enabled' => $userData['mfa_enabled'],
                    'mfa_enabled_at' => $userData['mfa_enabled_at']?->format('Y-m-d H:i:s'),
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

            $result = $this->loginTracker->unlockAccount($user, $validated['reason'] ?? 'Manually unlocked by admin');

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
            $result = $this->loginTracker->lockAccount($user, $validated['reason'], $durationHours);

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
            $result = $this->loginTracker->resetFailedAttempts($user);

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
