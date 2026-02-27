<?php

namespace App\Http\Controllers;

use App\Models\DocumentView;
use App\Services\PdfViewerService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PdfViewerController extends BaseController
{
    public function __construct(
        private PdfViewerService $pdfViewerService
    ) {}

    /**
     * Show PDF viewer page with comprehensive statistics
     */
    public function showPdfViewer(Request $request, string $fileKey): Response
    {
        Log::info('PDF Viewer requested', ['file_key' => $fileKey]);

        $documentData = $this->pdfViewerService->prepareDocumentData($fileKey, $request);
        $viewStats = $this->pdfViewerService->getFileViewStats($fileKey);
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
}
