<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(
        private AuditLogService $AuditLogService,
    ) {}

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, string|int $id): RedirectResponse
    {
        $this->authorize('view-notifications');

        $user = $request->user();

        if (! $user) {
            return redirect()->back()->with('error', 'User not authenticated');
        }

        $notification = $user->notifications()
            ->where('id', $id)
            ->first();

        if ($notification) {
            $notification->markAsRead();

            $this->AuditLogService->log(
                'security.notification_read',
                'notification',
                $id,
            );

            return redirect()->back()->with('success', 'Notification marked as read');
        }

        return redirect()->back()->with('error', 'Notification not found');
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): RedirectResponse
    {
        $this->authorize('view-notifications');

        $user = $request->user();

        if (! $user) {
            return redirect()->back()->with('error', 'User not authenticated');
        }

        $user->unreadNotifications()->update(['read_at' => now()]);

        $this->AuditLogService->log(
            'security.notifications_all_read',
            'notification',
            'all',
        );

        return redirect()->back()->with('success', 'All notifications marked as read');
    }

    /**
     * Show the notifications page with infinite scroll pagination.
     */
    public function page(Request $request): Response
    {
        $this->authorize('view-notifications');

        $user = $request->user();

        if ($user && $user->role === UserRole::BAC_SECRETARIAT->value) {
            Log::info('User is BAC Secretariat (ID: '.$user->id.'), redirecting from notifications page to dashboard.');

            return Redirect::route('bac-secretariat.dashboard');
        }

        // Use cursor-based pagination for infinite scroll (more efficient than offset)
        $perPage = 15; // Show 15 notifications per page
        $notifications = $user
            ? $user->notifications()
                ->orderBy('created_at', 'desc')
                ->cursorPaginate($perPage)
            : collect([]);

        $unreadCount = $user ? $user->unreadNotifications()->count() : 0;

        // Use merge() for infinite scroll - append new notifications to existing array
        $props = [
            'next_cursor' => $notifications->nextCursor()?->encode(),
            'has_more' => $notifications->hasMorePages(),
            'unread_count' => $unreadCount,
        ];

        // If cursor is present, we're loading more - use merge to append
        if ($request->has('cursor')) {
            $props['notifications'] = inertia()->merge($notifications->items());
        } else {
            // Initial load - replace notifications
            $props['notifications'] = $notifications->items();
        }

        return inertia('notifications', $props);
    }
}
