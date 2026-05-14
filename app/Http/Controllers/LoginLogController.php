<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\BlockedIpService;
use App\Services\LoginAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class LoginLogController extends Controller
{
    public function __construct(
        private LoginAnalyticsService $loginAnalytics,
        private BlockedIpService $blockedIpService,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * Display login logs page
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-login-logs');

        try {
            $recentLogins = $this->loginAnalytics->getRecentLogins(50);
            $statistics = $this->loginAnalytics->getLoginStatistics();

            return Inertia::render('admin/login-logs', [
                'recentLogins' => $recentLogins,
                'statistics' => $statistics,
                // Defer suspicious activities analysis - loads after initial page render
                'suspiciousActivities' => Inertia::defer(fn () => $this->loginAnalytics->getSuspiciousActivities()),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch login logs', [
                'admin_id' => $request->user()->id,
                'error' => 'An error occurred loading login logs. Please try again.',
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
    public function recent(Request $request)
    {
        $this->authorize('view-login-logs');

        try {
            $limit = $request->get('limit', 50);
            $recentLogins = $this->loginAnalytics->getRecentLogins($limit);

            return response()->json([
                'success' => true,
                'data' => $recentLogins,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch recent logins', [
                'admin_id' => $request->user()->id,
                'error' => 'An error occurred loading login logs. Please try again.',
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
    public function statistics(Request $request)
    {
        $this->authorize('view-login-logs');
        try {
            $statistics = $this->loginAnalytics->getLoginStatistics();

            return response()->json([
                'success' => true,
                'data' => $statistics,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch login statistics', [
                'admin_id' => $request->user()->id,
                'error' => 'An error occurred loading login logs. Please try again.',
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
    public function suspicious(Request $request)
    {
        $this->authorize('view-login-logs');
        try {
            $activities = $this->loginAnalytics->getSuspiciousActivities();

            return response()->json([
                'success' => true,
                'data' => $activities,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch suspicious activities', [
                'admin_id' => $request->user()->id,
                'error' => 'An error occurred loading login logs. Please try again.',
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch suspicious activities',
            ], 500);
        }
    }

    /**
     * Block an IP address
     */
    public function blockIp(Request $request)
    {
        $this->authorize('manage-blocked-ips');
        try {
            $validated = $request->validate([
                'ip_address' => 'required|ip',
                'reason' => 'nullable|string|max:255',
                'duration' => 'nullable|string|in:temporary,permanent',
            ]);

            $expiresAt = null;
            if (isset($validated['duration']) && $validated['duration'] === 'temporary') {
                // Block for 30 days by default
                $expiresAt = now()->addDays(30);
            }

            $this->blockedIpService->blockIp(
                $validated['ip_address'],
                $validated['reason'] ?? 'Blocked due to suspicious activity',
                $expiresAt,
                $request->user()
            );

            Log::warning('IP address blocked via admin panel', [
                'ip_address' => $validated['ip_address'],
                'blocked_by' => $request->user()->id,
                'reason' => $validated['reason'] ?? 'Blocked due to suspicious activity',
                'expires_at' => $expiresAt?->toDateTimeString(),
            ]);

            $this->auditLogger->log(
                'security.ip_blocked',
                'blocked_ip',
                $validated['ip_address'],
                [],
                ['reason' => $validated['reason'] ?? 'Blocked due to suspicious activity', 'duration' => $validated['duration'] ?? 'permanent'],
            );

            return back()->with('success', 'IP address blocked successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to block IP address', [
                'admin_id' => $request->user()->id,
                'error' => 'An error occurred loading login logs. Please try again.',
            ]);

            return back()->with('error', 'Failed to block IP address.');
        }
    }

    /**
     * Unblock an IP address
     */
    public function unblockIp(Request $request)
    {
        $this->authorize('manage-blocked-ips');
        try {
            $validated = $request->validate([
                'ip_address' => 'required|ip',
            ]);

            $result = $this->blockedIpService->unblockIp($validated['ip_address'], $request->user());

            if (! $result) {
                return back()->with('error', 'IP address not found in blocked list.');
            }

            Log::info('IP address unblocked via admin panel', [
                'ip_address' => $validated['ip_address'],
                'unblocked_by' => $request->user()->id,
            ]);

            $this->auditLogger->log(
                'security.ip_unblocked',
                'blocked_ip',
                $validated['ip_address'],
            );

            return back()->with('success', 'IP address unblocked successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to unblock IP address', [
                'admin_id' => $request->user()->id,
                'error' => 'An error occurred loading login logs. Please try again.',
            ]);

            return back()->with('error', 'Failed to unblock IP address.');
        }
    }

    /**
     * Get list of blocked IPs
     */
    public function blockedIps(Request $request)
    {
        $this->authorize('manage-blocked-ips');
        try {
            $blockedIps = $this->blockedIpService->getBlockedIps();

            return response()->json([
                'success' => true,
                'data' => $blockedIps,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch blocked IPs', [
                'admin_id' => $request->user()->id,
                'error' => 'An error occurred loading login logs. Please try again.',
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch blocked IPs',
            ], 500);
        }
    }
}
