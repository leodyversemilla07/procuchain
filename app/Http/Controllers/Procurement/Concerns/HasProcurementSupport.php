<?php

namespace App\Http\Controllers\Procurement\Concerns;

use App\Services\MultichainService;
use App\Services\ProcurementDataService;
use App\Services\ProcurementPublishingService;
use Illuminate\Http\RedirectResponse;

trait HasProcurementSupport
{
    protected MultichainService $multiChain;

    protected ProcurementPublishingService $publishingService;

    protected ProcurementDataService $procurementDataService;

    /**
     * Initialize procurement support dependencies
     */
    protected function initializeProcurementSupport(
        MultichainService $multiChain,
        ProcurementPublishingService $publishingService,
        ProcurementDataService $procurementDataService
    ): void {
        $this->multiChain = $multiChain;
        $this->publishingService = $publishingService;
        $this->procurementDataService = $procurementDataService;
    }

    /**
     * Apply common middleware for procurement controllers
     */
    protected function applyProcurementMiddleware(): void
    {
        $this->middleware('auth');
        $this->middleware('role:bac_secretariat');

        $this->middleware(function ($request, $next) {
            $response = $next($request);
            if ($response instanceof RedirectResponse) {
                $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0, private, max-age=0');
                $response->headers->set('Pragma', 'no-cache');
                $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time()).' GMT');

                $response->headers->set('X-Frame-Options', 'DENY');
                $response->headers->set('X-Content-Type-Options', 'nosniff');

                $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s').' GMT');
            }

            return $response;
        });
    }

    /**
     * Helper to find procurement by id from the STATUS stream.
     * Optimized to use ProcurementDataService instead of fetching all 1000 status items.
     *
     * @param  string|int  $id
     */
    protected function findProcurementById($id): ?array
    {
        $statusItems = $this->procurementDataService->fetchStatusItems($id);

        // Return the most recent status item
        $latestStatus = $statusItems->first();

        return $latestStatus ?: null;
    }
}
