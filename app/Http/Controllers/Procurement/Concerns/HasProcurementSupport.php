<?php

namespace App\Http\Controllers\Procurement\Concerns;

use App\Enums\StreamEnums;
use App\Services\MultichainService;
use App\Services\ProcurementPublishingService;
use Illuminate\Http\RedirectResponse;

trait HasProcurementSupport
{
    protected MultichainService $multiChain;

    protected ProcurementPublishingService $publishingService;

    /**
     * Initialize procurement support dependencies
     */
    protected function initializeProcurementSupport(
        MultichainService $multiChain,
        ProcurementPublishingService $publishingService
    ): void {
        $this->multiChain = $multiChain;
        $this->publishingService = $publishingService;
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
     *
     * @param  string|int  $id
     */
    protected function findProcurementById($id): ?array
    {
        $statusItems = $this->multiChain->listStreamItems(
            StreamEnums::STATUS->value,
            true,
            1000,
            0
        );

        if (! empty($statusItems)) {
            foreach ($statusItems as $item) {
                if (
                    isset($item['data']['json']) &&
                    isset($item['data']['json']['procurement_id']) &&
                    $item['data']['json']['procurement_id'] === $id
                ) {
                    return $item['data']['json'];
                }
            }
        }

        return null;
    }
}
