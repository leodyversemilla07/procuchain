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

use App\Enums\StageEnums;
use App\Enums\UserRoleEnums;
use App\Models\User;
use App\Repositories\DocumentRepository;
use App\Services\Procurement\ProcurementDetailService;
use App\Services\Procurement\ProcurementListAggregatorService;
use App\Services\ProcurementDataService;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementListController extends BaseController
{
    use AuthorizesRequests;

    private ProcurementDataService $procurementDataService;

    private ProcurementListAggregatorService $listAggregator;

    private ProcurementDetailService $detailService;

    /**
     * Constructor
     */
    public function __construct(
        ProcurementDataService $procurementDataService,
        ProcurementListAggregatorService $listAggregator,
        ProcurementDetailService $detailService
    ) {
        $this->procurementDataService = $procurementDataService;
        $this->listAggregator = $listAggregator;
        $this->detailService = $detailService;
    }

    /**
     * Display a listing of procurements
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-procurement');

        try {
            Log::info('Fetching procurements list');

            // Set a reasonable timeout for blockchain operations
            // Set a reasonable timeout for blockchain operations (skip in testing)
            if (! app()->runningUnitTests()) {
                set_time_limit(28); // Give 2 seconds buffer
            }

            $user = auth()->user();
            $visibilityFilters = $this->getVisibilityFilters($user);
            $showArchived = $request->boolean('archived');

            $procurements = $this->listAggregator->fetchAllProcurements(
                skipActions: false,
                filterByUserId: $visibilityFilters['user_id'],
                filterByUserAddress: $visibilityFilters['user_address'],
                archived: $showArchived,
                authUser: $user
            );

            // Log result count
            Log::info('Procurement List Result', [
                'user_id' => $user->id,
                'filtering' => $visibilityFilters['user_id'] !== null || $visibilityFilters['user_address'] !== null ? 'BAC secretariat scoped' : 'role-wide',
                'count' => count($procurements),
            ]);

            // Get filter parameters from request
            $search = request()->input('search', '');
            $status = request()->input('status', 'all');
            $stage = request()->input('stage', 'all');
            $page = max((int) request()->input('page', 1), 1);
            $perPage = (int) request()->input('per_page', 10);
            if ($perPage <= 0) {
                $perPage = 10;
            }

            // Apply filters if provided
            $filteredProcurements = $this->filterProcurements($procurements, $search, $status, $stage);

            // Simple array-based pagination (no DB) per Laravel + Inertia guidance
            $total = count($filteredProcurements);
            $offset = ($page - 1) * $perPage;
            $pageItems = array_slice($filteredProcurements, $offset, $perPage);

            Log::info('Successfully retrieved procurements list', [
                'total_count' => count($procurements),
                'filtered_count' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'filters' => compact('search', 'status', 'stage'),
            ]);

            return Inertia::render('procurements/procurements-list', [
                'procurements' => $pageItems,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                ],
                'stageOptions' => StageEnums::options(),
                'filters' => compact('search', 'status', 'stage'),
                'is_archived' => $showArchived,
            ]);
        } catch (Exception $e) {
            report($e);
            Log::error('Failed to retrieve procurements list', [
                'error' => 'An error occurred loading procurement data.',
                'trace' => $e->getTraceAsString(),
            ]);

            return Inertia::render('procurements/procurements-list', [
                'procurements' => [],
                'pagination' => [
                    'total' => 0,
                    'page' => (int) request()->input('page', 1),
                    'per_page' => (int) request()->input('per_page', 10),
                ],
                'error' => 'Unable to connect to the blockchain node right now. Please try again shortly.',
                'stageOptions' => StageEnums::options(),
            ]);
        }
    }

    /**
     * Filter procurements based on search, status, and stage
     *
     * @param  array<int, array<string, mixed>>  $procurements
     * @return array<int, array<string, mixed>>
     */
    private function filterProcurements(array $procurements, string $search, string $status, string $stage): array
    {
        return array_values(array_filter($procurements, function ($procurement) use ($search, $status, $stage) {
            // Search filter (title and ID)
            if (! empty($search)) {
                $searchLower = strtolower($search);
                $titleMatch = str_contains(strtolower($procurement['title'] ?? ''), $searchLower);
                $idMatch = str_contains(strtolower($procurement['id'] ?? ''), $searchLower);

                if (! $titleMatch && ! $idMatch) {
                    return false;
                }
            }

            // Status filter
            if ($status !== 'all' && ($procurement['current_status'] ?? '') !== $status) {
                return false;
            }

            // Stage filter
            if ($stage !== 'all' && ($procurement['stage'] ?? '') !== $stage) {
                return false;
            }

            return true;
        }));
    }

    /**
     * Display the specified procurement
     */
    public function show(string $pr_number): Response
    {
        $this->authorize('view-procurement', $pr_number);

        try {
            $this->validatepr_number($pr_number);

            Log::info('Fetching procurement details', ['pr_number' => $pr_number]);

            $result = $this->detailService->getDetail($pr_number);

            if ($result === null) {
                return $this->renderNotFound();
            }

            Log::info('Successfully retrieved procurement details', [
                'pr_number' => $pr_number,
            ]);

            return Inertia::render('procurements/show-procurement', [
                'procurement' => $result['procurement'],
                'workflow' => $result['workflow'],
                'now' => now()->toIso8601String(),
            ]);
        } catch (Exception $e) {
            report($e);
            Log::error('Failed to retrieve procurement details', [
                'pr_number' => $pr_number,
                'error' => 'An error occurred loading procurement data.',
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
    private function validatepr_number(?string $pr_number): void
    {
        if (empty($pr_number)) {
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
     * Queries actual blockchain to retrieve document transaction status
     */
    public function getBlockchainStatus(string $pr_number): JsonResponse
    {
        $this->authorize('view-procurement', $pr_number);

        try {
            // Fetch documents from blockchain for this procurement
            $documentRepository = app(DocumentRepository::class);
            $documentDataArray = $documentRepository->findByProcurement($pr_number);

            // Transform DocumentData objects to status response format
            $documents = array_map(function ($doc, $index) {
                return [
                    'id' => $index + 1,
                    'file_name' => $doc->fileName,
                    'blockchain_status' => 'confirmed', // Documents on blockchain are always confirmed
                    'blockchain_error' => null,
                    'blockchain_txid' => $doc->metadataTxid,
                    'blockchain_status_updated_at' => $doc->timestamp->toISOString(),
                ];
            }, $documentDataArray, array_keys($documentDataArray));

            $total = count($documents);
            $confirmed = $total; // All blockchain documents are confirmed
            $pending = 0;
            $failed = 0;

            // If no documents found, return empty state
            if ($total === 0) {
                return response()->json([
                    'status' => 'confirmed',
                    'summary' => [
                        'pending' => 0,
                        'confirmed' => 0,
                        'failed' => 0,
                        'total' => 0,
                    ],
                    'documents' => [],
                ]);
            }

            return response()->json([
                'status' => 'confirmed',
                'summary' => [
                    'pending' => $pending,
                    'confirmed' => $confirmed,
                    'failed' => $failed,
                    'total' => $total,
                ],
                'documents' => array_values($documents),
            ]);
        } catch (Exception $e) {
            report($e);
            Log::error('Failed to fetch blockchain status', [
                'pr_number' => $pr_number,
                'error' => 'An error occurred loading procurement data.',
                'trace' => $e->getTraceAsString(),
            ]);

            // Return safe default on error
            return response()->json([
                'status' => 'confirmed',
                'summary' => [
                    'pending' => 0,
                    'confirmed' => 0,
                    'failed' => 0,
                    'total' => 0,
                ],
                'documents' => [],
            ]);
        }
    }

    /**
     * @param  User  $user
     * @return array{user_id: ?string, user_address: ?string}
     */
    private function getVisibilityFilters($user): array
    {
        if (! $user->hasRole(UserRoleEnums::BAC_SECRETARIAT->value)) {
            return [
                'user_id' => null,
                'user_address' => null,
            ];
        }

        return [
            'user_id' => (string) $user->id,
            'user_address' => $user->blockchain_address,
        ];
    }
}
