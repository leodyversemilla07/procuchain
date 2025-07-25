<?php

namespace App\Http\Controllers;

use App\Notifications\ProcurementStageNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TestPushNotificationController extends Controller
{
    /**
     * Send a test push notification to the authenticated user
     */
    public function sendTestNotification(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return Inertia::render('settings/push-notification', [
                'error' => 'User not authenticated'
            ]);
        }

        // Create test notification data
        $testData = [
            'procurement_id' => 'TEST-001',
            'procurement_title' => 'Test Procurement for Push Notifications',
            'stage_identifier' => 'Test Stage',
            'current_status' => 'testing',
            'timestamp' => now()->toDateTimeString(),
            'action_type' => 'tested',
            'document_count' => 1,
        ];

        try {
            // Send the test notification
            $user->notify(new ProcurementStageNotification($testData));

            return Inertia::render('settings/push-notification', [
                'flash' => [
                    'message' => 'Test push notification sent successfully!',
                    'type' => 'success'
                ]
            ]);
        } catch (\Exception $e) {
            return Inertia::render('settings/push-notification', [
                'flash' => [
                    'message' => 'Failed to send test notification: ' . $e->getMessage(),
                    'type' => 'error'
                ]
            ]);
        }
    }
}
