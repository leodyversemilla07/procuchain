<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BlockchainHealthService;
use Illuminate\Http\JsonResponse;

class BlockchainHealthController extends Controller
{
    public function __construct(
        private BlockchainHealthService $healthService
    ) {}

    /**
     * Get blockchain health status
     */
    public function index(): JsonResponse
    {
        $health = $this->healthService->getHealthStatus();

        $httpStatus = $health['status'] === 'healthy' ? 200 : 503;

        return response()->json($health, $httpStatus);
    }

    /**
     * Reset circuit breaker (admin only)
     */
    public function reset(): JsonResponse
    {
        $this->healthService->resetCircuitBreaker();

        return response()->json([
            'message' => 'Circuit breaker has been reset',
            'status' => 'success',
        ]);
    }
}
