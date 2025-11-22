<?php

namespace App\Http\Controllers\Procurement\Concerns;

use App\Services\Manager;
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

    protected \App\Repositories\DocumentRepository $documentRepository;

    /**
     * Initialize procurement support dependencies
     */
    protected function initializeProcurementSupport(
        Manager $multichain,
        DocumentPublisher $documentPublisher,
        StatusPublisher $statusPublisher,
        EventPublisher $eventPublisher,
        ProcurementDataService $procurementDataService,
        \App\Repositories\DocumentRepository $documentRepository
    ): void {
        $this->multiChain = $multichain;
        $this->documentPublisher = $documentPublisher;
        $this->statusPublisher = $statusPublisher;
        $this->eventPublisher = $eventPublisher;
        $this->procurementDataService = $procurementDataService;
        $this->documentRepository = $documentRepository;
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

    /**
     * Get uploaded document types for a specific procurement and stage from blockchain.
     *
     * @return string[] Array of document type enum values (e.g., ['purchase_request', 'ppmp'])
     */
    protected function getUploadedDocumentTypes(string $pr_number, \App\Enums\StageEnums $stage): array
    {
        try {
            // Fetch all documents for this procurement from blockchain
            $documents = $this->documentRepository->findByProcurement($pr_number);

            // Filter by current stage and extract document types
            $uploadedTypes = [];
            foreach ($documents as $doc) {
                if ($doc->stage === $stage->value) {
                    $uploadedTypes[] = $doc->documentType;
                }
            }

            return array_unique($uploadedTypes);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to fetch uploaded documents', [
                'pr_number' => $pr_number,
                'stage' => $stage->value,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
