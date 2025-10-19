<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccountLockoutService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AccountLockoutController extends Controller
{
    public function __construct(
        private AccountLockoutService $accountLockout
    ) {}

    /**
     * Display locked accounts page
     */
    public function index(): Response
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
    public function unlock(Request $request, User $user)
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
    public function lock(Request $request, User $user)
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
    public function resetAttempts(User $user)
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
