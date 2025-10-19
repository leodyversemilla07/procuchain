<?php

namespace App\Http\Controllers;

use App\Models\DocumentView;
use App\Services\DocumentBlockchainService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PdfViewerController extends BaseController
{
    public function __construct(
        private DocumentBlockchainService $blockchainService
    ) {
        $this->middleware('auth');
        $this->middleware('role:bac_chairman|bac_secretariat|hope|admin');
    }

    /**
     * Show PDF viewer page with comprehensive statistics
     */
    public function showPdfViewer(Request $request, string $fileKey): Response
    {
        Log::info('PDF Viewer requested', ['file_key' => $fileKey]);

        $documentData = $this->blockchainService->getDocumentData($fileKey);

        $currentStatus = null;
        if ($documentData && isset($documentData['procurement_id']) && $documentData['procurement_id'] !== 'Unknown') {
            Log::info('Attempting to get procurement status', ['procurement_id' => $documentData['procurement_id']]);
            $currentStatus = $this->blockchainService->getCurrentProcurementStatus($documentData['procurement_id']);
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

        if (! $documentData) {
            Log::info('Blockchain data not found, creating fallback data', ['file_key' => $fileKey]);

            $documentView = DocumentView::where('file_key', $fileKey)->first();

            $parts = explode('/', $fileKey);
            $procurementId = 'Unknown';

            if (! empty($parts[0])) {
                if (preg_match('/^(PROC-\d{4}-\d{3})/', $parts[0], $matches)) {
                    $procurementId = $matches[1];
                } else {
                    $lastHyphenPos = strrpos($parts[0], '-');
                    if ($lastHyphenPos !== false) {
                        $procurementId = substr($parts[0], 0, $lastHyphenPos);
                    }
                }
            }

            $alternativeHash = $this->blockchainService->getHashByProcurementId($procurementId, $fileKey);

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

            $existingView = DocumentView::where('procurement_id', $procurementId)
                ->whereNotNull('procurement_title')
                ->where('procurement_title', '!=', 'Document Viewer (Development Mode)')
                ->where('procurement_title', '!=', 'Document (Development Mode)')
                ->first();

            $procurementTitle = 'Document Viewer (Development Mode)';

            if ($existingView) {
                $procurementTitle = $existingView->procurement_title;
            } else {
                if (! empty($parts[0]) && str_contains($parts[0], '-')) {
                    $titlePart = substr($parts[0], strlen($procurementId) + 1);
                    if (! empty($titlePart)) {
                        $procurementTitle = ucwords(str_replace(['_', '-'], ' ', $titlePart));
                    }
                }
            }

            $documentData = [
                'procurement_id' => $procurementId,
                'procurement_title' => $procurementTitle,
                'document_type' => pathinfo($fileKey, PATHINFO_FILENAME),
                'stage' => $parts[1] ?? 'Unknown',
                'file_size' => $this->getFileSize($fileKey),
                'timestamp' => $documentView->created_at->toISOString(),
                'hash' => $alternativeHash ?: '',
                'user_address' => Auth::user()->blockchain_address ?? 'no-blockchain-address',
            ];

            if ($procurementId !== 'Unknown') {
                Log::info('Attempting to get procurement status for fallback data', ['procurement_id' => $procurementId]);
                $currentStatus = $this->blockchainService->getCurrentProcurementStatus($procurementId);
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

        if (! isset($documentData['file_size']) || $documentData['file_size'] === null) {
            $documentData['file_size'] = $this->getFileSize($fileKey);
        }

        Log::info('Final PDF Viewer Document Data', [
            'fileKey' => $fileKey,
            'has_hash' => ! empty($documentData['hash']),
            'hash_value' => $documentData['hash'] ?? 'NOT SET',
            'hash_source' => ! empty($documentData['hash']) ? 'blockchain' : 'none',
            'procurement_id' => $documentData['procurement_id'] ?? 'NOT SET',
        ]);

        $viewStats = $this->getFileViewStats($fileKey);
        $recentViews = DocumentView::getRecentViewsForFile($fileKey, 20);

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
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_type', '=', 'App\\Models\\User')
            ->selectRaw('roles.name as role, COUNT(DISTINCT document_views.id) as view_count')
            ->groupBy('roles.name')
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
     * Get file size from storage
     */
    private function getFileSize(string $fileKey): ?int
    {
        try {
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
}
