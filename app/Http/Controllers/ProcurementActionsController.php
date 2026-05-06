<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ProcurementModeEnums;
use App\Services\Procurement\ProcurementActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Controller for fetching procurement actions on-demand
 *
 * This endpoint allows the frontend to lazy-load action buttons
 * when the user opens a dropdown, improving initial page load performance.
 */
class ProcurementActionsController extends Controller
{
    public function __construct(
        private readonly ProcurementActionService $actionService
    ) {}

    /**
     * Get available actions for a procurement
     */
    public function getActions(Request $request, string $pr_number): JsonResponse
    {
        $stage = $request->input('stage');
        $status = $request->input('status');
        $mode = $request->input('mode');

        if (! $stage || ! $status) {
            return response()->json([
                'error' => 'Missing required parameters: stage and status',
            ], 400);
        }

        // Get user role
        $user = Auth::user();
        $userRole = $user?->getRoleNames()->first() ?? 'guest';

        // Parse mode enum if provided
        $modeEnum = $mode ? ProcurementModeEnums::tryFrom($mode) : null;

        try {
            $workflowActions = $this->actionService->getAvailableActions(
                $pr_number,
                $stage,
                $status,
                $userRole,
                $modeEnum
            );

            $staticActions = $this->actionService->getStaticActions($pr_number, $userRole);

            return response()->json([
                'workflow_actions' => $workflowActions,
                'static_actions' => $staticActions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch actions',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
