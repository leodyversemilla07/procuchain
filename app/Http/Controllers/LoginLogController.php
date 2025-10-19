<?php

namespace App\Http\Controllers;

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
        private LoginService $loginLogger
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
}
