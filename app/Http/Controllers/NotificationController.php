<?php

namespace App\Http\Controllers;

use App\Enums\UserRoleEnums;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

class NotificationController extends Controller
{
    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, $id)
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->back()->withErrors(['error' => 'User not authenticated']);
        }

        $notification = $user->notifications()
            ->where('id', $id)
            ->first();

        if ($notification) {
            $notification->markAsRead();

            return redirect()->back()->with('success', 'Notification marked as read');
        }

        return redirect()->back()->withErrors(['error' => 'Notification not found']);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead()
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->back()->withErrors(['error' => 'User not authenticated']);
        }

        $user->unreadNotifications()->update(['read_at' => now()]);

        return redirect()->back()->with('success', 'All notifications marked as read');
    }

    /**
     * Show the notifications page with all data loaded via Inertia props.
     */
    public function page()
    {
        $user = Auth::user();

        if ($user && $user->role === UserRoleEnums::BAC_SECRETARIAT->value) {
            Log::info('User is BAC Secretariat (ID: '.$user->id.'), redirecting from notifications page to dashboard.');

            return Redirect::route('bac-secretariat.dashboard');
        }

        // Get notifications data for Inertia props
        $notifications = $user ? $user->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate(10) : collect([]);

        $unreadCount = $user ? $user->unreadNotifications()->count() : 0;

        return inertia('notifications', [
            'notifications' => $notifications->items(),
            'pagination' => [
                'total' => $notifications->total(),
                'per_page' => $notifications->perPage(),
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
            ],
            'unread_count' => $unreadCount,
        ]);
    }
}
