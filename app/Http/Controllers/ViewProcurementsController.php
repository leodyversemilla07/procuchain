<?php

namespace App\Http\Controllers;

use App\Enums\StreamEnums;
use App\Models\User;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\ProcurementServices;
use Illuminate\Routing\Controller as BaseController;

class ViewProcurementsController extends BaseController
{
    /**
     * @var ProcurementServices
     */
    private $services;

    /**
     * Constructor
     * 
     * @param ProcurementServices $services
     */
    public function __construct(ProcurementServices $services)
    {
        $this->services = $services;
        $this->setupMiddleware();
    }

    /**
     * Set up controller middleware
     */
    private function setupMiddleware(): void
    {
        $this->middleware('auth');
        $this->middleware('role:bac_chairman,bac_secretariat,hope');
    }

    /**
     * Get username from blockchain address
     * 
     * @param string $address
     * @return string
     */
    private function getUserName(string $address): string
    {
        try {
            return User::where('blockchain_address', $address)->first()?->name ?? 'Unknown';
        } catch (Exception $e) {
            Log::warning("Failed to retrieve user name for address: $address", [
                'error' => $e->getMessage(),
            ]);

            return 'Unknown';
        }
    }

    /**
     * Display a listing of procurements
     * 
     * @return Response
     */
    public function indexProcurementsList(): Response
    {
        try {
            Log::info('Fetching procurements list');

            $procurements = $this->fetchAndProcessProcurements();

            return Inertia::render('procurements/procurements-list', [
                'procurements' => $procurements,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to retrieve procurements list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Inertia::render('procurements/procurements-list', [
                'procurements' => [],
                'error' => 'Failed to retrieve procurements. Please try again later.',
            ]);
        }
    }

    /**
     * Fetch and process procurements data
     * 
     * @return array
     * @throws Exception
     */
    private function fetchAndProcessProcurements(): array
    {
        $streamItems = $this->services->getMultiChain()->listStreamItems(
            StreamEnums::STATUS->value,
            true,
            1000,
            0,
            false
        );

        if ($streamItems === null) {
            throw new Exception('Failed to retrieve stream items');
        }

        $allProcurements = $this->mapProcurementStreamItems($streamItems);

        return $allProcurements
            ->groupBy('id')
            ->map(function ($group) {
                // Ensure we're sorting by actual timestamp, not just date string
                return $group->sortByDesc(function ($item) {
                    // Convert timestamp to comparable value (unix timestamp)
                    return strtotime($item['timestamp'] ?? '0');
                })->first();
            })
            ->values()
            ->all();
    }

    /**
     * Map stream items to procurement data
     * 
     * @param array $streamItems
     * @return Collection
     */
    private function mapProcurementStreamItems(array $streamItems): Collection
    {
        return collect($streamItems)
            ->map(function ($item) {
                $data = $item['data']['json'] ?? [];

                $documentCount = $this->getDocumentCount(
                    $data['procurement_id'] ?? '',
                    $data['procurement_title'] ?? ''
                );

                // Store original timestamp for accurate sorting
                $originalTimestamp = $data['timestamp'] ?? null;
                
                // Format display timestamp
                $displayTimestamp = isset($data['timestamp'])
                    ? date('Y-m-d', strtotime($data['timestamp']))
                    : date('Y-m-d');

                return [
                    'id' => $data['procurement_id'] ?? null,
                    'title' => $data['procurement_title'] ?? null,
                    'stage' => $data['stage'] ?? '',
                    'current_status' => $data['current_status'] ?? '',
                    'timestamp' => $originalTimestamp, // Use original timestamp for sorting
                    'display_date' => $displayTimestamp, // Use formatted date for display
                    'last_updated' => $displayTimestamp,
                    'user_address' => $data['user_address'] ?? '',
                    'user' => $this->getUserName($data['user_address'] ?? ''),
                    'document_count' => $documentCount,
                ];
            });
    }

    /**
     * Get document count for a procurement
     * 
     * @param string $procurementId
     * @param string $procurementTitle
     * @return int
     */
    private function getDocumentCount(string $procurementId, string $procurementTitle): int
    {
        try {
            // Fetch status items to get all possible titles
            $statusItems = $this->services->getMultiChain()->listStreamItems(
                StreamEnums::STATUS->value,
                true
            );

            if (!$statusItems) {
                return 0;
            }

            // Get all possible titles for this procurement
            $allTitles = collect($statusItems)
                ->filter(function ($item) use ($procurementId) {
                    $data = $item['data']['json'] ?? [];
                    return ($data['procurement_id'] ?? '') === $procurementId;
                })
                ->map(function ($item) {
                    $data = $item['data']['json'] ?? [];
                    return $data['procurement_title'] ?? '';
                })
                ->unique()
                ->filter()
                ->values()
                ->toArray();

            // If no titles found, use the provided title
            if (empty($allTitles)) {
                $allTitles = [$procurementTitle];
            }

            $totalDocuments = 0;

            // Count documents for each possible title
            foreach ($allTitles as $title) {
                $streamKey = $this->services->getStreamKeyService()->generate(
                    $procurementId,
                    $title
                );
                
                $documents = $this->services->getMultiChain()->listStreamKeyItems(
                    StreamEnums::DOCUMENTS->value,
                    $streamKey
                );
                
                if ($documents) {
                    // Remove duplicates by hash if any
                    $uniqueDocuments = collect($documents)
                        ->map(function ($item) {
                            return $item['data']['json']['hash'] ?? '';
                        })
                        ->unique()
                        ->filter()
                        ->count();
                    
                    $totalDocuments += $uniqueDocuments;
                }
            }

            return $totalDocuments;
            
        } catch (Exception $e) {
            Log::warning('Failed to get document count', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Display the specified procurement
     * 
     * @param string $procurementId
     * @return Response
     */
    public function showProcurement($procurementId): Response
    {
        try {
            $this->validateProcurementId($procurementId);

            Log::info('Fetching procurement details', ['procurement_id' => $procurementId]);

            $statusItems = $this->fetchStatusItems($procurementId);
            $currentStatus = $statusItems->first();

            if (!$currentStatus) {
                Log::warning('Procurement details not found', ['procurement_id' => $procurementId]);
                return $this->renderNotFound();
            }

            // Get all possible titles from the timeline to ensure we capture all documents
            $allTitles = $statusItems->pluck('procurement_title')->unique()->values()->toArray();
            
            // Fetch all documents using all known titles for this procurement
            $documents = $this->fetchAndProcessAllDocuments($procurementId, $allTitles);
            $events = $this->fetchAndProcessEvents($procurementId);

            $procurement = $this->buildProcurementData(
                $procurementId,
                $currentStatus,
                $documents,
                $events,
                $statusItems
            );

            return Inertia::render('procurements/show-procurement', [
                'procurement' => $procurement,
                'now' => now()->toIso8601String(),
            ]);

        } catch (Exception $e) {
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
     * @param string|null $procurementId
     * @throws Exception
     */
    private function validateProcurementId(?string $procurementId): void
    {
        if (empty($procurementId)) {
            throw new Exception('Procurement ID is required');
        }
    }

    /**
     * Fetch and process status items
     * 
     * @param string $procurementId
     * @return Collection
     * @throws Exception
     */
    private function fetchStatusItems(string $procurementId): Collection
    {
        $statusStreamItems = $this->services->getMultiChain()->listStreamItems(
            StreamEnums::STATUS->value,
            true
        );

        if (!$statusStreamItems) {
            throw new Exception('Status stream items not found');
        }

        return collect($statusStreamItems)
            ->filter(function ($item) use ($procurementId) {
                $data = $item['data']['json'] ?? [];
                return ($data['procurement_id'] ?? '') === $procurementId;
            })
            ->map(function ($item) {
                $data = $item['data']['json'] ?? [];

                return [
                    'stage' => $data['stage'] ?? '',
                    'current_status' => $data['current_status'] ?? '',
                    'status' => $data['current_status'] ?? '', // Added this to match the frontend expectation
                    'timestamp' => $data['timestamp'] ?? '',
                    'procurement_id' => $data['procurement_id'] ?? '',
                    'procurement_title' => $data['procurement_title'] ?? '',
                    'user_address' => $data['user_address'] ?? '',
                ];
            })
            ->sortByDesc('timestamp');
    }

    /**
     * Fetch and process documents
     * 
     * @param string $streamKey
     * @return array
     */
    private function fetchAndProcessDocuments(string $streamKey): array
    {
        $documents = $this->services->getMultiChain()->listStreamKeyItems(
            StreamEnums::DOCUMENTS->value,
            $streamKey
        );

        return collect($documents)->map(function ($item) {
            $data = $item['data']['json'] ?? [];
            $fileKey = $data['file_key'] ?? '';
            $temporaryUrl = $this->generateTemporaryUrl($fileKey);

            return [
                'file_key' => $fileKey,
                'document_type' => $data['document_type'] ?? '',
                'spaces_url' => $temporaryUrl,
                'hash' => $data['hash'] ?? '',
                'file_size' => $data['file_size'] ?? null,
                'stage' => $data['stage'] ?? '',
                'stage_metadata' => $data['stage_metadata'] ?? null,
                'procurement_id' => $data['procurement_id'] ?? '',
                'procurement_title' => $data['procurement_title'] ?? '',
                'user_address' => $data['user_address'] ?? '',
                'timestamp' => $data['timestamp'] ?? '',
            ];
        })->toArray();
    }

    /**
     * Fetch and process documents using all known procurement titles
     * 
     * @param string $procurementId
     * @param array $procurementTitles
     * @return array
     */
    private function fetchAndProcessAllDocuments(string $procurementId, array $procurementTitles): array
    {
        $allDocuments = collect();

        // Use each title to generate a different stream key and fetch documents
        foreach ($procurementTitles as $title) {
            $streamKey = $this->services->getStreamKeyService()->generate(
                $procurementId,
                $title
            );
            
            $documents = $this->fetchAndProcessDocuments($streamKey);
            $allDocuments = $allDocuments->concat($documents);
        }

        // Remove any potential duplicates (by file hash)
        return $allDocuments->unique('hash')->sortByDesc('timestamp')->values()->toArray();
    }

    /**
     * Generate temporary URL for file
     * 
     * @param string $fileKey
     * @return string
     */
    private function generateTemporaryUrl(string $fileKey): string
    {
        if (empty($fileKey)) {
            return '';
        }

        try {            
            return Storage::disk('spaces')->temporaryUrl($fileKey, now()->addHours(1));
        } catch (Exception $e) {
            Log::error('Failed to generate temporary URL', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * Fetch and process events
     * 
     * @param string $procurementId
     * @return array
     */
    private function fetchAndProcessEvents(string $procurementId): array
    {
        $events = $this->services->getMultiChain()->listStreamItems(
            StreamEnums::EVENTS->value
        );

        return collect($events)
            ->filter(function ($item) use ($procurementId) {
                $data = $item['data']['json'] ?? [];
                return ($data['procurement_id'] ?? '') === $procurementId;
            })
            ->map(function ($item) {
                $data = $item['data']['json'] ?? [];

                return [
                    'timestamp' => $data['timestamp'] ?? '',
                    'event_type' => $data['event_type'] ?? '',
                    'details' => $data['details'] ?? '',
                    'stage' => $data['stage_identifier'] ?? '',
                    'document_count' => $data['document_count'] ?? null,
                    'procurement_id' => $data['procurement_id'] ?? '',
                    'procurement_title' => $data['procurement_title'] ?? '',
                    'user_address' => $data['user_address'] ?? '',
                    'category' => $data['category'] ?? '',
                    'severity' => $data['severity'] ?? '',
                ];
            })
            ->sortBy('timestamp')
            ->values()
            ->toArray();
    }

    /**
     * Build procurement data structure
     * 
     * @param string $procurementId
     * @param array $currentStatus
     * @param array $documents
     * @param array $events
     * @param Collection $statusItems
     * @return array
     */
    private function buildProcurementData(
        string $procurementId,
        array $currentStatus,
        array $documents,
        array $events,
        Collection $statusItems
    ): array {
        return [
            'id' => $procurementId,
            'title' => $currentStatus['procurement_title'],
            'status' => $currentStatus,
            'documents' => $documents,
            'events' => $events,
            'timeline' => $statusItems->values()->toArray(),
        ];
    }

    /**
     * Render not found response
     * 
     * @return Response
     */
    private function renderNotFound(): Response
    {
        return Inertia::render('procurements/not-found', [
            'message' => 'Procurement not found',
        ]);
    }
}
