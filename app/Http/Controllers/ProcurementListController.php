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
    public function index(): Response
    {
        // Authorization: All authenticated users can view procurements list
        // (removed Procurement model dependency)

        try {
            Log::info('Fetching procurements list');

            // Set a reasonable timeout for blockchain operations
            set_time_limit(28); // Give 2 seconds buffer

            $procurements = $this->procurementDataService->fetchAndProcessProcurements();

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
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve procurements list', [
                'error' => $e->getMessage(),
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
        // Authorization: All authenticated users can view procurement details
        // (removed Procurement model dependency)

        try {
            $this->validatepr_number($pr_number);

            Log::info('Fetching procurement details', ['pr_number' => $pr_number]);

            $statusItems = $this->procurementDataService->fetchStatusItems($pr_number);
            $currentStatus = $statusItems->first();

            if (! $currentStatus) {
                return $this->renderNotFound();
            }

            $documents = $this->procurementDataService->fetchAndProcessAllDocuments($pr_number);
            $events = $this->procurementDataService->fetchAndProcessEvents($pr_number);

            $this->procurementDataService->preloadUserNames(collect($events));

            // Fetch procurement details from blockchain
            $procurementRepository = app(\App\Repositories\ProcurementRepository::class);
            $procurementDetails = $procurementRepository->findByProcurement($pr_number);

            $procurementData = $this->procurementDataService->buildProcurementData(
                $pr_number,
                $currentStatus,
                $documents,
                $events,
                $statusItems
            );

            // Add procurement details to response if available
            if ($procurementDetails) {
                $procurementData['details'] = [
                    'pr_number' => $procurementDetails->prNumber,
                    'app_reference' => $procurementDetails->appReference,
                    'title' => $procurementDetails->title,
                    'description' => $procurementDetails->description,
                    'abc_amount' => $procurementDetails->abcAmount,
                    'abc_amount_formatted' => $procurementDetails->getFormattedAbcAmount(),
                    'funding_source' => $procurementDetails->fundingSource,
                    'category' => $procurementDetails->category->value,
                    'category_label' => $procurementDetails->category->label(),
                    'procurement_mode' => $procurementDetails->procurementMode->value,
                    'procurement_mode_label' => $procurementDetails->procurementMode->label(),
                    'office' => $procurementDetails->office,
                    'end_user' => $procurementDetails->endUser,
                    // Delivery details are optional - populated at Contract Implementation stage per NGPA IRR
                    'delivery_location' => $procurementDetails->deliveryLocation,
                    'delivery_date' => $procurementDetails->deliveryDate?->toIso8601String(),
                    'delivery_date_formatted' => $procurementDetails->getFormattedDeliveryDate(),
                    'delivery_term_days' => $procurementDetails->deliveryTermDays,
                    'prepared_by' => $procurementDetails->preparedBy,
                    'bac_resolution_number' => $procurementDetails->bacResolutionNumber,
                    'bac_resolution_date' => $procurementDetails->bacResolutionDate?->toIso8601String(),
                    'bac_resolution_date_formatted' => $procurementDetails->getFormattedBacResolutionDate(),
                    'philgeps_reference' => $procurementDetails->philgepsReference,
                    'philgeps_posting_date' => $procurementDetails->philgepsPostingDate?->toIso8601String(),
                    'philgeps_posting_date_formatted' => $procurementDetails->getFormattedPhilgepsPostingDate(),
                    'approved_by' => $procurementDetails->approvedBy,
                    'approval_date' => $procurementDetails->approvalDate?->toIso8601String(),
                    'approval_date_formatted' => $procurementDetails->getFormattedApprovalDate(),
                    'created_at' => $procurementDetails->createdAt->toIso8601String(),
                    'created_at_formatted' => $procurementDetails->getFormattedCreatedAt(),
                ];

                // Add correction information
                $procurementCorrectionRepository = app(\App\Repositories\ProcurementCorrectionRepository::class);
                $hasCorrections = $procurementCorrectionRepository->hasCorrections($pr_number);
                $latestCorrection = $hasCorrections ? $procurementCorrectionRepository->getLatest($pr_number) : null;
                $allCorrections = $hasCorrections ? $procurementCorrectionRepository->findByProcurement($pr_number) : [];

                $procurementData['details']['has_corrections'] = $hasCorrections;
                $procurementData['details']['latest_correction'] = $latestCorrection ? [
                    'timestamp' => $latestCorrection->timestamp->toIso8601String(),
                    'corrected_by' => $latestCorrection->correctedBy,
                    'reason' => $latestCorrection->reason,
                    'changed_fields' => $latestCorrection->getChangedFields(),
                ] : null;
                $procurementData['details']['corrections'] = array_map(function ($correction) {
                    return [
                        'pr_number' => $correction->prNumber,
                        'timestamp' => $correction->timestamp->toIso8601String(),
                        'reason' => $correction->reason,
                        'corrected_by' => $correction->correctedBy,
                        'correction_type' => $correction->correctionType,
                        'correction_type_display' => ucwords(str_replace('_', ' ', $correction->correctionType)),
                        'changed_fields' => $correction->getChangedFields(),
                        'metadata' => $correction->toBlockchainArray(),
                    ];
                }, $allCorrections);
            }

            Log::debug('Current status data', [
                'current_status' => $currentStatus,
                'procurement_data_status' => $procurementData['status'] ?? null,
            ]);

            if ($procurementData === null) {
                Log::warning('Procurement details not found after cache check', ['pr_number' => $pr_number]);

                return $this->renderNotFound();
            }

            Log::info('Successfully retrieved procurement details', [
                'pr_number' => $pr_number,
            ]);

            return Inertia::render('procurements/show-procurement', [
                'procurement' => $procurementData,
                'now' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve procurement details', [
                'pr_number' => $pr_number,
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
        try {
            // Fetch documents from blockchain for this procurement
            $documentRepository = app(\App\Repositories\DocumentRepository::class);
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
        } catch (\Exception $e) {
            Log::error('Failed to fetch blockchain status', [
                'pr_number' => $pr_number,
                'error' => $e->getMessage(),
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
}
