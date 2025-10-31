<?php

declare(strict_types=1);
/**
 * @phpstan-ignore-file
 *
 * @psalm-suppress TooManyArguments
 *
 * @noinspection Generic.StringHeavyFunctionArguments
 */

namespace App\Http\Controllers;

use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Services\ProcurementDataService;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementListController extends BaseController
{
    use AuthorizesRequests;

    private ProcurementDataService $procurementDataService;

    /**
     * Constructor
     */
    public function __construct(ProcurementDataService $procurementDataService)
    {
        $this->procurementDataService = $procurementDataService;
        $this->setupMiddleware();
    }

    /**
     * Set up controller middleware
     */
    private function setupMiddleware(): void
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of procurements
     */
    public function indexProcurementsList(): Response
    {
        $this->authorize('viewAny', Procurement::class);

        try {
            Log::info('Fetching procurements list');
            $procurements = $this->procurementDataService->fetchAndProcessProcurements();

            Log::info('Successfully retrieved procurements list', [
                'count' => count($procurements),
            ]);

            return Inertia::render('procurements/procurements-list', [
                'procurements' => $procurements,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve procurements list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Inertia::render('procurements/procurements-list', [
                'procurements' => [],
                'error' => 'Unable to connect to the blockchain node right now. Please try again shortly.',
            ]);
        }
    }

    /**
     * Display the specified procurement
     */
    public function showProcurement(string $procurementId): Response
    {
        $this->authorize('viewAny', Procurement::class);

        try {
            $this->validateProcurementId($procurementId);

            Log::info('Fetching procurement details', ['procurement_id' => $procurementId]);

            $statusItems = $this->procurementDataService->fetchStatusItems($procurementId);
            $currentStatus = $statusItems->first();

            if (! $currentStatus) {
                return $this->renderNotFound();
            }

            $documents = $this->procurementDataService->fetchAndProcessAllDocuments($procurementId);
            $events = $this->procurementDataService->fetchAndProcessEvents($procurementId);

            $this->procurementDataService->preloadUserNames(collect($events));

            $procurementData = $this->procurementDataService->buildProcurementData(
                $procurementId,
                $currentStatus,
                $documents,
                $events,
                $statusItems
            );

            if ($procurementData === null) {
                Log::warning('Procurement details not found after cache check', ['procurement_id' => $procurementId]);

                return $this->renderNotFound();
            }

            Log::info('Successfully retrieved procurement details', [
                'procurement_id' => $procurementId,
            ]);

            return Inertia::render('procurements/show-procurement', [
                'procurement' => $procurementData,
                'now' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve procurement details', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Inertia::render('procurements/show-procurement', [
                'error' => 'Failed to retrieve procurement details. Please try again later.',
            ]);
        }
    }

    /**
     * Validate the procurement ID
     *
     * @throws Exception
     */
    private function validateProcurementId(?string $procurementId): void
    {
        if (empty($procurementId)) {
            throw new Exception('Procurement ID is required');
        }
    }

    /**
     * Render not found response
     */
    private function renderNotFound(): Response
    {
        return Inertia::render('procurements/show-procurement', [
            'error' => 'Procurement not found',
        ]);
    }

    /**
     * Get blockchain publication status for procurement documents
     *
     * This endpoint is polled by the blockchain-publishing-status page
     * to provide real-time feedback on document publication progress.
     */
    public function getBlockchainStatus(string $id): JsonResponse
    {
        $documents = ProcurementDocument::where('procurement_id', $id)
            ->latest('created_at')
            ->limit(50) // Recent documents
            ->get(['id', 'file_name', 'blockchain_status', 'blockchain_error', 'blockchain_txid', 'blockchain_status_updated_at', 'created_at']);

        $summary = [
            'pending' => $documents->where('blockchain_status', 'pending')->count(),
            'confirmed' => $documents->where('blockchain_status', 'confirmed')->count(),
            'failed' => $documents->where('blockchain_status', 'failed')->count(),
            'total' => $documents->count(),
        ];

        // Determine overall status
        $allConfirmed = $summary['pending'] === 0 && $summary['failed'] === 0 && $summary['total'] > 0;
        $hasFailed = $summary['failed'] > 0;
        $status = $allConfirmed ? 'confirmed' : ($hasFailed ? 'failed' : 'pending');

        return response()->json([
            'status' => $status,
            'summary' => $summary,
            'documents' => $documents->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'file_name' => $doc->file_name,
                    'blockchain_status' => $doc->blockchain_status,
                    'blockchain_error' => $doc->blockchain_error,
                    'blockchain_txid' => $doc->blockchain_txid,
                    'updated_at' => $doc->blockchain_status_updated_at?->diffForHumans() ?? $doc->created_at->diffForHumans(),
                ];
            }),
        ]);
    }
}
