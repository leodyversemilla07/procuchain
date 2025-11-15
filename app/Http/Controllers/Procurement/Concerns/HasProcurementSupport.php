<?php

namespace App\Http\Controllers\Procurement\Concerns;

use App\Libraries\MultiChain\Manager;
use App\Services\ProcurementDataService;
use App\Services\Publishers\DocumentPublisher;
use App\Services\Publishers\EventPublisher;
use App\Services\Publishers\StatusPublisher;
use Illuminate\Http\RedirectResponse;

trait HasProcurementSupport
{
    protected Manager $multichain;

    protected DocumentPublisher $documentPublisher;

    protected StatusPublisher $statusPublisher;

    protected EventPublisher $eventPublisher;

    protected ProcurementDataService $procurementDataService;

    /**
     * Initialize procurement support dependencies
     */
    protected function initializeProcurementSupport(
        Manager $multichain,
        DocumentPublisher $documentPublisher,
        StatusPublisher $statusPublisher,
        EventPublisher $eventPublisher,
        ProcurementDataService $procurementDataService
    ): void {
        $this->multiChain = $multichain;
        $this->documentPublisher = $documentPublisher;
        $this->statusPublisher = $statusPublisher;
        $this->eventPublisher = $eventPublisher;
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
