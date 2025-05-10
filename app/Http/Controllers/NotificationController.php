<?php

namespace App\Http\Controllers;

use App\Enums\UserRoleEnums; // Add this line
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; // Add this line
use Illuminate\Support\Facades\Redirect; // Add this line

class NotificationController extends Controller
{
    /**
     * Get the user's notifications.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return $this->_handleUnauthenticatedUser($request);
        }

        if ($user->role === UserRoleEnums::BAC_SECRETARIAT->value) {
            return $this->_handleBacSecretariat($request, $user);
        }

        return $this->_fetchUserNotifications($request, $user);
    }

    private function _handleUnauthenticatedUser(Request $request)
    {
        Log::warning('No authenticated user found when fetching notifications.');
        return response()->json([
            'notifications' => [],
            'pagination' => [
                'total' => 0,
                'per_page' => $request->input('per_page', 10),
                'current_page' => 1,
                'last_page' => 1,
            ],
            'unread_count' => 0,
            'message' => 'User not authenticated'
        ], 401);
    }

    private function _handleBacSecretariat(Request $request, $user)
    {
        Log::info('User is BAC Secretariat (ID: ' . $user->id . '), returning empty notifications.');
        return response()->json([
            'notifications' => [],
            'pagination' => [
                'total' => 0,
                'per_page' => $request->input('per_page', 10),
                'current_page' => 1,
                'last_page' => 1,
            ],
            'unread_count' => 0,
        ]);
    }

    private function _fetchUserNotifications(Request $request, $user)
    {
        // Original logging for debugging, kept commented out as Notifiable trait should handle this.
        // Log::info('Authenticated user class: ' . get_class($user));
        // Log::info('Fetching notifications for user ID: ' . $user->id);
        // if (!method_exists($user, 'notifications')) {
        //     Log::error('User object (' . get_class($user) . ') does not have the "notifications" method.');
        // }
        // if (!method_exists($user, 'unreadNotifications')) {
        //     Log::error('User object (' . get_class($user) . ') does not have the "unreadNotifications" method.');
        // }

        $perPage = $request->input('per_page', 10);

        try {
            $notifications = $user->notifications()
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $unreadCount = $user->unreadNotifications()->count();

            return response()->json([
                'notifications' => $notifications->items(),
                'pagination' => [
                    'total' => $notifications->total(),
                    'per_page' => $notifications->perPage(),
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                ],
                'unread_count' => $unreadCount,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error fetching notifications for user ID ' . ($user ? $user->id : 'N/A') . ': ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json([
                'notifications' => [],
                'pagination' => [
                    'total' => 0,
                    'per_page' => $perPage,
                    'current_page' => $request->input('page', 1),
                    'last_page' => 1,
                ],
                'unread_count' => 0,
                'error' => 'Could not fetch notifications due to a server error.',
            ], 500);
        }
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, $id)
    {
        $notification = Auth::user()
            ->notifications()
            ->where('id', $id)
            ->first();

        if ($notification) {
            $notification->markAsRead();

            return response()->json(['message' => 'Notification marked as read']);
        }

        return response()->json(['message' => 'Notification not found'], 404);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead()
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    /**
     * Show the notifications page.
     */
    public function page()
    {
        $user = Auth::user();

        if ($user && $user->role === UserRoleEnums::BAC_SECRETARIAT->value) {
            Log::info('User is BAC Secretariat (ID: ' . $user->id . '), redirecting from notifications page to dashboard.');
            return Redirect::route('bac-secretariat.dashboard');
        }

        return inertia('notifications');
    }
}
