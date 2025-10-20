<?php

namespace App\Http\Controllers;

use App\Services\BlockedIpService;
use App\Services\LoginService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class LoginLogController extends Controller
{
    public function __construct(
        private LoginService $loginLogger,
        private BlockedIpService $blockedIpService
    ) {}

    /**
     * Display login logs page
     */
    public function index(): Response
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
    public function recent(Request $request)
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
    public function statistics()
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
    public function suspicious()
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
     * Block an IP address
     */
    public function blockIp(Request $request)
    {
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

            $block = $this->blockedIpService->blockIp(
                $validated['ip_address'],
                $validated['reason'] ?? 'Blocked due to suspicious activity',
                $expiresAt
            );

            Log::warning('IP address blocked via admin panel', [
                'ip_address' => $validated['ip_address'],
                'blocked_by' => Auth::id(),
                'reason' => $validated['reason'] ?? 'Blocked due to suspicious activity',
                'expires_at' => $expiresAt?->toDateTimeString(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'IP address blocked successfully',
                'data' => $block,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to block IP address', [
                'admin_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to block IP address',
            ], 500);
        }
    }

    /**
     * Unblock an IP address
     */
    public function unblockIp(Request $request)
    {
        try {
            $validated = $request->validate([
                'ip_address' => 'required|ip',
            ]);

            $result = $this->blockedIpService->unblockIp($validated['ip_address']);

            if (! $result) {
                return response()->json([
                    'success' => false,
                    'error' => 'IP address not found in blocked list',
                ], 404);
            }

            Log::info('IP address unblocked via admin panel', [
                'ip_address' => $validated['ip_address'],
                'unblocked_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'IP address unblocked successfully',
            ]);
        } catch (Exception $e) {
            Log::error('Failed to unblock IP address', [
                'admin_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to unblock IP address',
            ], 500);
        }
    }

    /**
     * Get list of blocked IPs
     */
    public function blockedIps()
    {
        try {
            $blockedIps = $this->blockedIpService->getBlockedIps();

            return response()->json([
                'success' => true,
                'data' => $blockedIps,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to fetch blocked IPs', [
                'admin_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch blocked IPs',
            ], 500);
        }
    }
}
