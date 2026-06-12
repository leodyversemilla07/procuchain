<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SharedLedgerService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shared Ledger Controller
 *
 * Thin controller — delegates all blockchain data retrieval,
 * filtering, pagination, and purge detection to SharedLedgerService.
 */
class SharedLedgerController extends Controller
{
    public function __construct(
        private SharedLedgerService $ledgerService,
    ) {}

    /**
     * Display the shared ledger page.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-shared-ledger');

        $filters = $request->only(['pr_number', 'stream', 'date_from', 'date_to', 'search', 'node', 'page']);

        // Auto-detect the node from the route prefix (e.g. /bac-secretariat/shared-ledger -> node=bac-secretariat)
        // Only apply when no explicit ?node= query param is provided.
        if (! isset($filters['node'])) {
            $prefix = $request->segment(1);
            $validNodeIds = collect(config('multichain.nodes', []))->pluck('id')->toArray();

            if ($prefix && in_array($prefix, $validNodeIds, true)) {
                $filters['node'] = $prefix;
            }
        }

        try {
            $data = $this->ledgerService->getLedgerPage($filters);
            $data['selected_node'] = $filters['node'] ?? 'all';
            $data['filters'] = $filters;

            return Inertia::render('shared-ledger', $data);
        } catch (Exception $e) {
            report($e);
            Log::error('SharedLedger: Failed to fetch ledger entries', [
                'trace' => sprintf('%s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()),
            ]);

            $data = $this->ledgerService->getEmptyLedgerPage($filters);
            $data['error'] = 'Failed to load the shared ledger. The blockchain node may be unavailable. Please try again.';

            return Inertia::render('shared-ledger', $data);
        }
    }
}
