<?php

namespace App\Http\Controllers;

use App\Enums\DocumentTypeEnums;
use App\Enums\StageEnums;
use App\Models\DocumentView;
use App\Repositories\DocumentRepository;
use App\Services\ProcurementDataService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PdfViewerController extends BaseController
{
    public function __construct(
        private ProcurementDataService $procurementDataService,
        private DocumentRepository $documentRepository
    ) {
    }

    /**
     * Show PDF viewer page with comprehensive statistics
     */
    public function showPdfViewer(Request $request, string $fileKey): Response
    {
        Log::info('PDF Viewer requested', ['file_key' => $fileKey]);

        $documentData = $this->procurementDataService->getDocumentDataByFileKey($fileKey);

        // Map data_txid to blockchain_txid for frontend compatibility
        // But only if the document actually exists in blockchain
        if ($documentData && isset($documentData['data_txid'])) {
            // Validate that the document exists in blockchain before showing correction button
            // Use validateDocumentExistsInBlockchain instead of findByTxid since findByTxid has issues
            $documentExists = $this->procurementDataService->validateDocumentExistsInBlockchain($fileKey);
            if ($documentExists) {
                $documentData['blockchain_txid'] = $documentData['data_txid'];
            }
        }

        // Format the stage and document_type if document data exists
        if ($documentData) {
            if (isset($documentData['stage'])) {
                $documentData['stage_display'] = $this->formatStage($documentData['stage']);
            }
            if (isset($documentData['document_type'])) {
                $documentData['document_type_display'] = $this->formatDocumentType($documentData['document_type']);
            }
        }

        $currentStatus = null;
        if ($documentData && isset($documentData['pr_number']) && $documentData['pr_number'] !== 'Unknown') {
            Log::info('Attempting to get procurement status', ['pr_number' => $documentData['pr_number']]);
            $currentStatus = $this->procurementDataService->getCurrentProcurementStatus($documentData['pr_number']);
            if ($currentStatus) {
                $documentData['current_status'] = $currentStatus['current_status'] ?? null;
                $documentData['status_timestamp'] = $currentStatus['timestamp'] ?? null;
                $documentData['phase'] = $currentStatus['phase'] ?? null;
                $documentData['phase_display_name'] = $currentStatus['phase_display_name'] ?? null;
                Log::info('Procurement status found', [
                    'pr_number' => $documentData['pr_number'],
                    'current_status' => $documentData['current_status'],
                    'status_timestamp' => $documentData['status_timestamp'],
                    'phase' => $documentData['phase'],
                    'phase_display_name' => $documentData['phase_display_name'],
                ]);
            } else {
                Log::info('No procurement status found', ['pr_number' => $documentData['pr_number']]);
            }
        }

        if (! $documentData) {
            Log::info('Blockchain data not found, creating fallback data', ['file_key' => $fileKey]);

            $documentView = DocumentView::where('file_key', $fileKey)->first();

            $parts = explode('/', $fileKey);
            $pr_number = 'Unknown';

            if (! empty($parts[0])) {
                if (preg_match('/^(PROC-\d{4}-\d{3})/', $parts[0], $matches)) {
                    $pr_number = $matches[1];
                } else {
                    $lastHyphenPos = strrpos($parts[0], '-');
                    if ($lastHyphenPos !== false) {
                        $pr_number = substr($parts[0], 0, $lastHyphenPos);
                    }
                }
            }

            $alternativeHash = $this->procurementDataService->getHashBypr_number($pr_number, $fileKey);

            if (! $documentView) {
                $documentView = DocumentView::create([
                    'user_id' => Auth::id(),
                    'file_key' => $fileKey,
                    'pr_number' => $pr_number,
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

            $existingView = DocumentView::where('pr_number', $pr_number)
                ->whereNotNull('procurement_title')
                ->where('procurement_title', '!=', 'Document Viewer (Development Mode)')
                ->where('procurement_title', '!=', 'Document (Development Mode)')
                ->first();

            $procurementTitle = 'Document Viewer (Development Mode)';

            if ($existingView) {
                $procurementTitle = $existingView->procurement_title;
            } else {
                if (! empty($parts[0]) && str_contains($parts[0], '-')) {
                    $titlePart = substr($parts[0], strlen($pr_number) + 1);
                    if (! empty($titlePart)) {
                        $procurementTitle = ucwords(str_replace(['_', '-'], ' ', $titlePart));
                    }
                }
            }

            $documentData = [
                'pr_number' => $pr_number,
                'procurement_title' => $procurementTitle,
                'document_type' => $documentView->document_type ?? pathinfo($fileKey, PATHINFO_FILENAME),
                'document_type_display' => $this->formatDocumentType($documentView->document_type ?? pathinfo($fileKey, PATHINFO_FILENAME)),
                'stage' => $documentView->stage ?? ($parts[1] ?? 'Unknown'),
                'stage_display' => $this->formatStage($documentView->stage ?? ($parts[1] ?? 'Unknown')),
                'file_size' => $this->getFileSize($fileKey),
                'timestamp' => $documentView->created_at->toISOString(),
                'hash' => $alternativeHash ?: '',
                'user_address' => Auth::user()->blockchain_address ?? 'no-blockchain-address',
            ];

            if ($pr_number !== 'Unknown') {
                Log::info('Attempting to get procurement status for fallback data', ['pr_number' => $pr_number]);
                $currentStatus = $this->procurementDataService->getCurrentProcurementStatus($pr_number);
                if ($currentStatus) {
                    $documentData['current_status'] = $currentStatus['current_status'] ?? null;
                    $documentData['status_timestamp'] = $currentStatus['timestamp'] ?? null;
                    $documentData['phase'] = $currentStatus['phase'] ?? null;
                    $documentData['phase_display_name'] = $currentStatus['phase_display_name'] ?? null;
                    Log::info('Procurement status found for fallback data', [
                        'pr_number' => $pr_number,
                        'current_status' => $documentData['current_status'],
                        'status_timestamp' => $documentData['status_timestamp'],
                        'phase' => $documentData['phase'],
                        'phase_display_name' => $documentData['phase_display_name'],
                    ]);
                } else {
                    Log::info('No procurement status found for fallback data', ['pr_number' => $pr_number]);
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
            'pr_number' => $documentData['pr_number'] ?? 'NOT SET',
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
            'pdfUrl' => route('files.download', ['fileKey' => $fileKey]),
            'viewStats' => $viewStats,
            'recentViews' => $recentViews->map(function ($view) {
                return [
                    'id' => $view->id,
                    'user' => [
                        'name' => $view->user->name,
                        'role' => $view->user->roles->first()?->name ?? 'guest',
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
     * Format stage enum to display name
     */
    private function formatStage(?string $stage): string
    {
        if (! $stage || $stage === 'Unknown') {
            return 'Unknown';
        }

        // Try to match the stage with StageEnums
        try {
            $stageEnum = StageEnums::tryFrom($stage);
            if ($stageEnum) {
                return $stageEnum->getDisplayName();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to format stage', ['stage' => $stage, 'error' => $e->getMessage()]);
        }

        // Fallback: return as is if no enum match
        return $stage;
    }

    /**
     * Format document type to display name
     */
    private function formatDocumentType(?string $documentType): string
    {
        if (! $documentType || $documentType === 'Unknown') {
            return 'Unknown Document';
        }

        // Try to match the document type with DocumentTypeEnums
        try {
            $documentTypeEnum = DocumentTypeEnums::fromString($documentType);
            if ($documentTypeEnum) {
                return $documentTypeEnum->getDisplayName();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to format document type', ['document_type' => $documentType, 'error' => $e->getMessage()]);
        }

        // Fallback: Convert snake_case or kebab-case to Title Case if no enum match
        if (! preg_match('/[A-Z\s]/', $documentType)) {
            return ucwords(str_replace(['_', '-'], ' ', $documentType));
        }

        // Already formatted, return as is
        return $documentType;
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
     * Get file size from blockchain metadata
     */
    private function getFileSize(string $fileKey): ?int
    {
        try {
            // Get file size from blockchain metadata using ProcurementDataService
            $documentData = $this->procurementDataService->getDocumentDataByFileKey($fileKey);

            if ($documentData && isset($documentData['file_size'])) {
                Log::info('Got file size from blockchain metadata', [
                    'file_key' => $fileKey,
                    'size' => $documentData['file_size'],
                ]);

                return (int) $documentData['file_size'];
            }

            Log::warning('Could not determine file size from blockchain', [
                'file_key' => $fileKey,
                'has_document_data' => $documentData !== null,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get file size from blockchain', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
