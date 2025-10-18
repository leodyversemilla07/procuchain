<?php

namespace App\Http\Controllers;

use App\Services\BlockchainHealthService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BlockchainHealthController extends Controller
{
    public function __construct(
        private BlockchainHealthService $healthService
    ) {}

    /**
     * Show blockchain health dashboard
     */
    public function index(): Response
    {
        $health = $this->healthService->getHealthStatus();

        return Inertia::render('admin/blockchain-health', [
            'health' => $health,
        ]);
    }

    /**
     * Reset circuit breaker (admin only)
     */
    public function reset(): RedirectResponse
    {
        $this->healthService->resetCircuitBreaker();

        return redirect()->back()->with('success', 'Circuit breaker has been reset successfully.');
    }
}
