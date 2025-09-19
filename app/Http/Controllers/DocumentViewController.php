<?php

namespace App\Http\Controllers;

use App\Enums\StreamEnums;
use App\Models\DocumentView;
use App\Services\MultichainService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DocumentViewController extends BaseController
{
    private MultichainService $multichainService;

    public function __construct(MultichainService $multichainService)
    {
        $this->middleware('auth');
        $this->middleware('role:bac_chairman,bac_secretariat,hope,admin');
        $this->multichainService = $multichainService;
    }

    /**
     * Securely download a file with authentication validation
     */
    public function downloadFile(Request $request, string $fileKey)
    {
        try {
            if (empty($fileKey)) {
                abort(400, 'Invalid file key');
            }

            $documentData = $this->validateFileAccess($fileKey);
            if (! $documentData) {
                abort(404, 'File not found or access denied');
            }

            $disk = Storage::disk('spaces');

            if (! $disk->exists($fileKey)) {
                $placeholderPdf = $this->createPlaceholderPdf($fileKey, $documentData);

                $this->recordDocumentView($request, $fileKey, $documentData);

                Log::info('Secure file access (placeholder)', [
                    'file_key' => $fileKey,
                    'user_id' => Auth::id(),
                    'user_role' => Auth::user()->role ?? 'unknown',
                    'ip' => $request->ip(),
                ]);

                return response($placeholderPdf)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'inline; filename="'.basename($fileKey).'"')
                    ->header('Cache-Control', 'private, no-cache, no-store, must-revalidate')
                    ->header('Pragma', 'no-cache')
                    ->header('Expires', '0')
                    ->header('X-Content-Type-Options', 'nosniff')
                    ->header('X-Frame-Options', 'SAMEORIGIN')
                    ->header('Accept-Ranges', 'bytes')
                    ->header('Content-Security-Policy', "default-src 'self'; object-src 'self'; frame-src 'self';");
            }

            $stream = $disk->readStream($fileKey);
            if ($stream === false) {
                abort(404, 'File not readable');
            }

            $mimeType = 'application/pdf';
            $fileName = basename($fileKey);

            $this->recordDocumentView($request, $fileKey, $documentData);

            Log::info('Secure file access', [
                'file_key' => $fileKey,
                'user_id' => Auth::id(),
                'user_role' => Auth::user()->role ?? 'unknown',
                'ip' => $request->ip(),
            ]);

            return response()->stream(function () use ($stream) {
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }, 200, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="'.$fileName.'"',
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'SAMEORIGIN',
                'Accept-Ranges' => 'bytes',
                'Content-Security-Policy' => "default-src 'self'; object-src 'self'; frame-src 'self';",
            ]);
        } catch (Exception $e) {
            Log::error('Secure file download failed', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
                'user_id' => Auth::id() ?? 'guest',
            ]);

            abort(500, 'Unable to retrieve file');
        }
    }

    /**
     * Get views for a specific file
     */
    public function getFileViews(Request $request, string $fileKey): JsonResponse
    {
        $views = DocumentView::getRecentViewsForFile($fileKey, 50);

        return response()->json([
            'success' => true,
            'data' => $views->map(function ($view) {
                return [
                    'id' => $view->id,
                    'user' => [
                        'name' => $view->user->name,
                        'role' => $view->user->role,
                    ],
                    'viewed_at' => $view->viewed_at->format('M j, Y g:i A'),
                    'viewed_at_human' => $view->viewed_at->diffForHumans(),
                    'ip_address' => $view->ip_address,
                    'view_duration' => $view->view_duration,
                ];
            }),
            'total' => $views->count(),
        ]);
    }

    /**
     * Get view statistics for a procurement
     */
    public function getProcurementViewStats(Request $request, string $procurementId): JsonResponse
    {
        $stats = DocumentView::getProcurementViewStats($procurementId);

        return response()->json([
            'success' => true,
            'data' => $stats->map(function ($stat) {
                return [
                    'file_key' => $stat->file_key,
                    'document_type' => $stat->document_type,
                    'stage' => $stat->stage,
                    'total_views' => $stat->total_views,
                    'unique_viewers' => $stat->unique_viewers,
                    'last_viewed_at' => $stat->last_viewed_at ? $stat->last_viewed_at->format('M j, Y g:i A') : null,
                    'last_viewed_human' => $stat->last_viewed_at ? $stat->last_viewed_at->diffForHumans() : null,
                ];
            }),
        ]);
    }

    /**
     * Get user's viewing history
     */
    public function getUserViewHistory(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $perPage = $request->get('per_page', 20);

        $views = DocumentView::with('user:id,name,role')
            ->where('user_id', $userId)
            ->orderBy('viewed_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $views->items(),
            'pagination' => [
                'current_page' => $views->currentPage(),
                'last_page' => $views->lastPage(),
                'per_page' => $views->perPage(),
                'total' => $views->total(),
            ],
        ]);
    }

    /**
     * Get most viewed documents
     */
    public function getMostViewedDocuments(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        $mostViewed = DocumentView::getMostViewedDocuments($limit);

        return response()->json([
            'success' => true,
            'data' => $mostViewed->map(function ($doc) {
                return [
                    'file_key' => $doc->file_key,
                    'document_type' => $doc->document_type,
                    'procurement_title' => $doc->procurement_title,
                    'stage' => $doc->stage,
                    'total_views' => $doc->total_views,
                    'unique_viewers' => $doc->unique_viewers,
                    'last_viewed_at' => $doc->last_viewed_at ? $doc->last_viewed_at->format('M j, Y g:i A') : null,
                    'last_viewed_human' => $doc->last_viewed_at ? $doc->last_viewed_at->diffForHumans() : null,
                ];
            }),
        ]);
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStats(Request $request): JsonResponse
    {
        $totalViews = DocumentView::count();
        $todayViews = DocumentView::whereDate('viewed_at', today())->count();
        $weekViews = DocumentView::where('viewed_at', '>=', now()->subWeek())->count();
        $monthViews = DocumentView::where('viewed_at', '>=', now()->subMonth())->count();

        $uniqueViewersToday = DocumentView::whereDate('viewed_at', today())
            ->distinct('user_id')
            ->count('user_id');

        $mostViewedToday = DocumentView::selectRaw('
                document_type,
                COUNT(*) as views_count
            ')
            ->whereDate('viewed_at', today())
            ->groupBy('document_type')
            ->orderBy('views_count', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_views' => $totalViews,
                'today_views' => $todayViews,
                'week_views' => $weekViews,
                'month_views' => $monthViews,
                'unique_viewers_today' => $uniqueViewersToday,
                'most_viewed_today' => $mostViewedToday,
            ],
        ]);
    }

    /**
     * Show PDF viewer page with comprehensive statistics
     */
    public function showPdfViewer(Request $request, string $fileKey): Response
    {
        Log::info('PDF Viewer requested', ['file_key' => $fileKey]);
        // Get document data and validate access
        $documentData = $this->getDocumentData($fileKey);
        // Get current procurement status if we have a procurement ID
        $currentStatus = null;
        if ($documentData && isset($documentData['procurement_id']) && $documentData['procurement_id'] !== 'Unknown') {
            Log::info('Attempting to get procurement status', ['procurement_id' => $documentData['procurement_id']]);
            $currentStatus = $this->getCurrentProcurementStatus($documentData['procurement_id']);
            if ($currentStatus) {
                $documentData['current_status'] = $currentStatus['current_status'] ?? null;
                $documentData['status_timestamp'] = $currentStatus['timestamp'] ?? null;
                Log::info('Procurement status found', [
                    'procurement_id' => $documentData['procurement_id'],
                    'current_status' => $documentData['current_status'],
                    'status_timestamp' => $documentData['status_timestamp'],
                ]);
            } else {
                Log::info('No procurement status found', ['procurement_id' => $documentData['procurement_id']]);
            }
        }

        // If blockchain data is not available, try alternative approach or create fallback data
        if (! $documentData) {
            Log::info('Blockchain data not found, creating fallback data', ['file_key' => $fileKey]);

            // Check if we have a document view record to create fallback data
            $documentView = DocumentView::where('file_key', $fileKey)->first();

            // Extract metadata from file key for creating records
            $parts = explode('/', $fileKey);
            $procurementId = 'Unknown';

            // Extract procurement ID from the first part (before first slash)
            if (! empty($parts[0])) {
                // The procurement ID should be in format like "PROC-2025-001-SomeTitle"
                // We need to extract "PROC-2025-001" from this
                if (preg_match('/^(PROC-\d{4}-\d{3})/', $parts[0], $matches)) {
                    $procurementId = $matches[1];
                } else {
                    // Fallback: assume procurement ID is everything before the last hyphen in the first part
                    $lastHyphenPos = strrpos($parts[0], '-');
                    if ($lastHyphenPos !== false) {
                        $procurementId = substr($parts[0], 0, $lastHyphenPos);
                    }
                }
            }

            // Try to get blockchain data by procurement ID as alternative approach
            $alternativeHash = $this->getHashByProcurementId($procurementId, $fileKey);

            // If no document view exists, create one to ensure the PDF viewer works
            if (! $documentView) {
                $documentView = DocumentView::create([
                    'user_id' => Auth::id(),
                    'file_key' => $fileKey,
                    'procurement_id' => $procurementId,
                    'procurement_title' => 'Document Viewer (Development Mode)',
                    'document_type' => pathinfo($fileKey, PATHINFO_FILENAME),
                    'stage' => $parts[1] ?? 'Unknown',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata' => [
                        'file_size' => null,
                        'hash' => $alternativeHash,
                        'stage_metadata' => null,
                    ],
                ]);
            }
            // Try to get a better procurement title from existing views with the same procurement_id
            $existingView = DocumentView::where('procurement_id', $procurementId)
                ->whereNotNull('procurement_title')
                ->where('procurement_title', '!=', 'Document Viewer (Development Mode)')
                ->where('procurement_title', '!=', 'Document (Development Mode)')
                ->first();

            $procurementTitle = 'Document Viewer (Development Mode)';

            if ($existingView) {
                $procurementTitle = $existingView->procurement_title;
            } else {
                // Try to extract title from file key format: {procurementId}-{title}/{stage}/{documentType}
                if (! empty($parts[0]) && str_contains($parts[0], '-')) {
                    // Remove the procurement ID part and get the title
                    $titlePart = substr($parts[0], strlen($procurementId) + 1); // +1 for the hyphen
                    if (! empty($titlePart)) {
                        // Convert underscores/hyphens to spaces and capitalize
                        $procurementTitle = ucwords(str_replace(['_', '-'], ' ', $titlePart));
                    }
                }
            }
            // Create document data using extracted information
            // Use alternative hash if found, otherwise empty string
            $documentData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'document_type' => pathinfo($fileKey, PATHINFO_FILENAME),
                'stage' => $parts[1] ?? 'Unknown',
                'file_size' => $this->getFileSize($fileKey), // Try to get actual file size
                'timestamp' => $documentView->created_at->toISOString(),
                'hash' => $alternativeHash ?: '', // Use alternative hash or empty string
                'user_address' => Auth::user()->blockchain_address ?? 'no-blockchain-address', // Use current user's blockchain address
            ];
            // Try to get current procurement status for fallback data too
            if ($procurementId !== 'Unknown') {
                Log::info('Attempting to get procurement status for fallback data', ['procurement_id' => $procurementId]);
                $currentStatus = $this->getCurrentProcurementStatus($procurementId);
                if ($currentStatus) {
                    $documentData['current_status'] = $currentStatus['current_status'] ?? null;
                    $documentData['status_timestamp'] = $currentStatus['timestamp'] ?? null;
                    Log::info('Procurement status found for fallback data', [
                        'procurement_id' => $procurementId,
                        'current_status' => $documentData['current_status'],
                        'status_timestamp' => $documentData['status_timestamp'],
                    ]);
                } else {
                    Log::info('No procurement status found for fallback data', ['procurement_id' => $procurementId]);
                }
            }
        }

        // If we still don't have file size, try to get it from the actual file
        if (! isset($documentData['file_size']) || $documentData['file_size'] === null) {
            $documentData['file_size'] = $this->getFileSize($fileKey);
        }

        // Final logging of what we're sending to the frontend
        Log::info('Final PDF Viewer Document Data', [
            'fileKey' => $fileKey,
            'has_hash' => ! empty($documentData['hash']),
            'hash_value' => $documentData['hash'] ?? 'NOT SET',
            'hash_source' => ! empty($documentData['hash']) ? 'blockchain' : 'none',
            'procurement_id' => $documentData['procurement_id'] ?? 'NOT SET',
        ]);

        // Get viewing statistics
        $viewStats = $this->getFileViewStats($fileKey);
        // Get recent viewers
        $recentViews = DocumentView::getRecentViewsForFile($fileKey, 20);

        // Debug: Log the document data being passed
        Log::info('PDF Viewer Document Data', [
            'fileKey' => $fileKey,
            'documentData' => $documentData,
            'procurement_title' => $documentData['procurement_title'] ?? 'NOT SET',
            'file_size' => $documentData['file_size'] ?? 'NOT SET',
            'file_size_type' => gettype($documentData['file_size'] ?? null),
        ]);

        return Inertia::render('documents/pdf-viewer', [
            'document' => $documentData,
            'fileKey' => $fileKey,
            'pdfUrl' => route('secure.file.download', ['fileKey' => $fileKey]),
            'viewStats' => $viewStats,
            'recentViews' => $recentViews->map(function ($view) {
                return [
                    'id' => $view->id,
                    'user' => [
                        'name' => $view->user->name,
                        'role' => $view->user->role,
                    ],
                    'viewed_at' => $view->viewed_at->format('M j, Y g:i A'),
                    'viewed_at_human' => $view->viewed_at->diffForHumans(),
                    'ip_address' => $view->ip_address,
                    'view_duration' => $view->view_duration,
                    'user_address' => $view->user->blockchain_address ?? 'no-blockchain-address',
                ];
            }),
        ]);
    }

    /**
     * Get document data from blockchain - enhanced to match procurement details logic
     */
    private function getDocumentData(string $fileKey): ?array
    {
        try {
            Log::info('Attempting to get blockchain data for PDF viewer', ['file_key' => $fileKey]);

            $allDocumentItems = $this->multichainService->listStreamItems(
                StreamEnums::DOCUMENTS->value,
                true,
                10000,
                0,
                false
            );

            if ($allDocumentItems === null) {
                Log::warning('Failed to retrieve document stream items for PDF viewer.', ['file_key' => $fileKey]);

                return null;
            }

            Log::info('Retrieved document stream items', [
                'file_key' => $fileKey,
                'total_items' => count($allDocumentItems),
            ]);

            // Use the same filtering approach as ViewProcurementsController
            $documentItem = collect($allDocumentItems)
                ->filter(function ($item) use ($fileKey) {
                    // Check if the necessary keys exist before accessing them
                    $itemFileKey = $item['data']['json']['file_key'] ?? null;

                    // Log each item for debugging
                    Log::debug('Checking document item', [
                        'search_file_key' => $fileKey,
                        'item_file_key' => $itemFileKey,
                        'match' => $itemFileKey === $fileKey,
                    ]);

                    return isset($item['data']['json']['file_key']) &&
                        $item['data']['json']['file_key'] === $fileKey;
                })
                ->first();

            if (! $documentItem) {
                Log::info('No blockchain document found for file key', ['file_key' => $fileKey]);

                return null;
            }

            $data = $documentItem['data']['json'] ?? [];

            Log::info('Found blockchain document data', [
                'file_key' => $fileKey,
                'hash' => $data['hash'] ?? 'NOT SET',
                'procurement_id' => $data['procurement_id'] ?? 'NOT SET',
                'full_data_keys' => array_keys($data),
            ]);

            // Return the data with same structure as procurement details
            return [
                'procurement_id' => $data['procurement_id'] ?? 'Unknown',
                'procurement_title' => $data['procurement_title'] ?? 'Unknown Document',
                'document_type' => $data['document_type'] ?? pathinfo($fileKey, PATHINFO_FILENAME),
                'stage' => $data['stage'] ?? 'Unknown',
                'file_size' => $data['file_size'] ?? null,
                'timestamp' => $data['timestamp'] ?? now()->toISOString(),
                'hash' => $data['hash'] ?? '', // Use empty string like procurement details
                'user_address' => $data['user_address'] ?? 'unknown@example.com',
                'stage_metadata' => $data['stage_metadata'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get document data from blockchain', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Get current procurement status from blockchain
     */
    private function getCurrentProcurementStatus(string $procurementId): ?array
    {
        try {
            $statusItems = $this->multichainService->listStreamItems(
                StreamEnums::STATUS->value,
                true,
                1000,
                0,
                false
            );

            if ($statusItems === null) {
                return null;
            }

            // Filter and sort status items for this procurement
            $procurementStatuses = collect($statusItems)
                ->filter(function ($item) use ($procurementId) {
                    $data = $item['data']['json'] ?? [];

                    return ($data['procurement_id'] ?? '') === $procurementId;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['json']['timestamp'] ?? '';
                });

            $latestStatus = $procurementStatuses->first();

            if ($latestStatus) {
                $data = $latestStatus['data']['json'] ?? [];

                return [
                    'current_status' => $data['current_status'] ?? '',
                    'stage' => $data['stage'] ?? '',
                    'timestamp' => $data['timestamp'] ?? '',
                    'procurement_id' => $data['procurement_id'] ?? '',
                    'procurement_title' => $data['procurement_title'] ?? '',
                    'user_address' => $data['user_address'] ?? '',
                ];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get procurement status', [
                'procurement_id' => $procurementId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get comprehensive view statistics for a file
     */
    private function getFileViewStats(string $fileKey): array
    {
        $totalViews = DocumentView::where('file_key', $fileKey)->count();
        $uniqueViewers = DocumentView::where('file_key', $fileKey)
            ->distinct('user_id')
            ->count('user_id');
        $todayViews = DocumentView::where('file_key', $fileKey)
            ->whereDate('viewed_at', today())
            ->count();
        $weekViews = DocumentView::where('file_key', $fileKey)
            ->where('viewed_at', '>=', now()->subWeek())
            ->count();
        $monthViews = DocumentView::where('file_key', $fileKey)
            ->where('viewed_at', '>=', now()->subMonth())
            ->count();

        $viewsByRole = DocumentView::where('file_key', $fileKey)
            ->join('users', 'document_views.user_id', '=', 'users.id')
            ->selectRaw('users.role, COUNT(*) as view_count')
            ->groupBy('users.role')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->role => $item->view_count];
            });

        $viewsByDay = DocumentView::where('file_key', $fileKey)
            ->where('viewed_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(viewed_at) as date, COUNT(*) as views')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->date => $item->views];
            });

        return [
            'total_views' => $totalViews,
            'unique_viewers' => $uniqueViewers,
            'today_views' => $todayViews,
            'week_views' => $weekViews,
            'month_views' => $monthViews,
            'views_by_role' => $viewsByRole,
            'views_by_day' => $viewsByDay,
            'first_viewed' => DocumentView::where('file_key', $fileKey)
                ->orderBy('viewed_at')
                ->first()?->viewed_at?->format('M j, Y g:i A'),
            'last_viewed' => DocumentView::where('file_key', $fileKey)
                ->orderBy('viewed_at', 'desc')
                ->first()?->viewed_at?->format('M j, Y g:i A'),
        ];
    }

    /**
     * Get quick statistics for a file
     */
    public function getFileStats(Request $request, string $fileKey): JsonResponse
    {
        try {
            $stats = DocumentView::getFileStatistics($fileKey);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get file statistics', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get file statistics',
            ], 500);
        }
    }

    /**
     * Get file size from storage
     */
    private function getFileSize(string $fileKey): ?int
    {
        try {
            // First try to get file size from DigitalOcean Spaces
            if (Storage::disk('spaces')->exists($fileKey)) {
                $size = Storage::disk('spaces')->size($fileKey);
                if ($size > 0) {
                    Log::info('Got file size from DigitalOcean Spaces', [
                        'file_key' => $fileKey,
                        'size' => $size,
                    ]);

                    return $size;
                }
            }

            // Fallback: try to get file size from local storage if it exists
            if (Storage::disk('local')->exists($fileKey)) {
                $size = Storage::disk('local')->size($fileKey);
                if ($size > 0) {
                    Log::info('Got file size from local storage', [
                        'file_key' => $fileKey,
                        'size' => $size,
                    ]);

                    return $size;
                }
            }

            Log::warning('Could not determine file size', [
                'file_key' => $fileKey,
                'spaces_exists' => Storage::disk('spaces')->exists($fileKey),
                'local_exists' => Storage::disk('local')->exists($fileKey),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get file size', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Alternative method to get document hash by procurement ID and file pattern matching
     */
    private function getHashByProcurementId(string $procurementId, string $fileKey): ?string
    {
        try {
            Log::info('Attempting alternative hash lookup', [
                'procurement_id' => $procurementId,
                'file_key' => $fileKey,
            ]);
            $allDocumentItems = $this->multichainService->listStreamItems(
                StreamEnums::DOCUMENTS->value,
                true,
                10000,
                0,
                false
            );

            if ($allDocumentItems === null) {
                return null;
            }

            // Try to find document by procurement ID and file pattern similarity
            $documentItem = collect($allDocumentItems)
                ->filter(function ($item) use ($procurementId, $fileKey) {
                    $data = $item['data']['json'] ?? [];
                    $itemProcurementId = $data['procurement_id'] ?? '';
                    $itemFileKey = $data['file_key'] ?? '';

                    // First try exact procurement ID match
                    if ($itemProcurementId === $procurementId) {
                        return true;
                    }

                    // Then try file key pattern matching (e.g., similar paths)
                    $fileKeyParts = explode('/', $fileKey);
                    $itemFileKeyParts = explode('/', $itemFileKey);

                    // Check if they have similar structure and first part matches
                    if (count($fileKeyParts) >= 1 && count($itemFileKeyParts) >= 1) {
                        return $fileKeyParts[0] === $itemFileKeyParts[0];
                    }

                    return false;
                })
                ->first();

            if ($documentItem) {
                $data = $documentItem['data']['json'] ?? [];
                $hash = $data['hash'] ?? null;

                Log::info('Alternative hash lookup result', [
                    'found_hash' => ! empty($hash),
                    'hash_value' => $hash,
                    'matched_file_key' => $data['file_key'] ?? 'NOT SET',
                ]);

                return $hash;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Alternative hash lookup failed', [
                'procurement_id' => $procurementId,
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Validate that the file exists in our document stream and user has access
     */
    private function validateFileAccess(string $fileKey): ?array
    {
        try {
            $allDocumentItems = $this->multichainService->listStreamItems(
                StreamEnums::DOCUMENTS->value,
                true,
                10000,
                0,
                false
            );

            if ($allDocumentItems === null) {
                $documentView = DocumentView::where('file_key', $fileKey)->first();

                if ($documentView) {
                    $parts = explode('/', $fileKey);

                    return [
                        'procurement_id' => $parts[0] ?? 'Unknown',
                        'procurement_title' => 'Document (Development Mode)',
                        'document_type' => pathinfo($fileKey, PATHINFO_FILENAME),
                        'stage' => $parts[1] ?? 'Unknown',
                        'file_key' => $fileKey,
                    ];
                }

                return null;
            }

            foreach ($allDocumentItems as $item) {
                $data = $item['data']['json'] ?? [];
                if (($data['file_key'] ?? '') === $fileKey) {
                    return $data;
                }
            }

            return null;
        } catch (Exception $e) {
            Log::error('File access validation failed', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            $documentView = DocumentView::where('file_key', $fileKey)->first();

            if ($documentView) {
                $parts = explode('/', $fileKey);

                return [
                    'procurement_id' => $parts[0] ?? 'Unknown',
                    'procurement_title' => 'Document (Development Mode)',
                    'document_type' => pathinfo($fileKey, PATHINFO_FILENAME),
                    'stage' => $parts[1] ?? 'Unknown',
                    'file_key' => $fileKey,
                ];
            }

            return null;
        }
    }

    /**
     * Record a document view in the database
     */
    private function recordDocumentView(Request $request, string $fileKey, array $documentData): void
    {
        try {
            DocumentView::create([
                'user_id' => Auth::id(),
                'file_key' => $fileKey,
                'procurement_id' => $documentData['procurement_id'] ?? '',
                'procurement_title' => $documentData['procurement_title'] ?? null,
                'document_type' => $documentData['document_type'] ?? null,
                'stage' => $documentData['stage'] ?? null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'file_size' => $documentData['file_size'] ?? null,
                    'hash' => $documentData['hash'] ?? null,
                    'stage_metadata' => $documentData['stage_metadata'] ?? null,
                ],
                'viewed_at' => now(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to record document view', [
                'file_key' => $fileKey,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create a simple placeholder PDF for development when actual file is missing
     */
    private function createPlaceholderPdf(string $fileKey, array $documentData): string
    {
        $documentType = $documentData['document_type'] ?? 'Document';
        $procurementId = $documentData['procurement_id'] ?? 'Unknown';
        $stage = $documentData['stage'] ?? 'Unknown';

        $pdf = "%PDF-1.4\n";
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n";
        $pdf .= "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
        $pdf .= "5 0 obj\n<< /Length 200 >>\nstream\n";
        $pdf .= "BT\n/F1 16 Tf\n100 700 Td\n(DEVELOPMENT MODE - PLACEHOLDER PDF) Tj\n";
        $pdf .= "0 -30 Td\n(Document: {$documentType}) Tj\n";
        $pdf .= "0 -20 Td\n(Procurement ID: {$procurementId}) Tj\n";
        $pdf .= "0 -20 Td\n(Stage: {$stage}) Tj\n";
        $pdf .= "0 -40 Td\n(File Key: {$fileKey}) Tj\n";
        $pdf .= "0 -60 Td\n(This is a placeholder PDF for development purposes.) Tj\n";
        $pdf .= "0 -20 Td\n(The actual file is not available in the storage.) Tj\n";
        $pdf .= "ET\n";
        $pdf .= "endstream\nendobj\n";
        $pdf .= "xref\n0 6\n0000000000 65535 f \n0000000010 00000 n \n0000000053 00000 n \n0000000107 00000 n \n0000000225 00000 n \n0000000284 00000 n \n";
        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\n";
        $pdf .= "startxref\n536\n%%EOF";

        return $pdf;
    }
}
