<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller as BaseController;
use App\Models\DocumentView;
use App\Services\AuditLogger;
use App\Services\BlockchainStorageService;
use App\Services\ProcurementDataService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DocumentDownloadController extends BaseController
{
    public function __construct(
        private ProcurementDataService $procurementDataService,
        private BlockchainStorageService $fileStorageService,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * Securely download a file with authentication validation
     */
    public function downloadFile(Request $request, string $fileKey)
    {
        $this->authorize('download-document', $fileKey);
        abort_if($request->user() === null, 401);

        try {
            if (empty($fileKey)) {
                abort(400, 'Invalid file key');
            }

            $documentData = $this->validateFileAccess($fileKey);
            if (! $documentData) {
                abort(404, 'File not found or access denied');
            }

            // Pure blockchain architecture - get data_txid from blockchain metadata
            $dataTxid = $documentData['data_txid'] ?? null;
            $fileName = $documentData['file_name'] ?? basename($fileKey);

            Log::info('Retrieving file from blockchain', [
                'file_key' => $fileKey,
                'data_txid' => $dataTxid,
                'user_id' => $request->user()?->id,
            ]);

            // Retrieve file from blockchain using data_txid
            try {
                $fileData = $this->fileStorageService->retrieveFile($fileKey, $dataTxid);

                $this->recordDocumentView($request, $fileKey, $documentData);

                $this->auditLogger->log(
                    'document.downloaded',
                    'document',
                    $fileKey,
                    [],
                    ['pr_number' => $documentData['pr_number'] ?? null, 'document_type' => $documentData['document_type'] ?? null],
                );

                Log::info('Secure file access from blockchain', [
                    'file_key' => $fileKey,
                    'data_txid' => $dataTxid ?? 'not_available',
                    'user_id' => $request->user()?->id,
                    'user_role' => $request->user()?->role ?? 'unknown',
                    'ip' => $request->ip(),
                ]);

                return response($fileData['content'])
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'inline; filename="'.$fileName.'"')
                    ->header('Cache-Control', 'private, no-cache, no-store, must-revalidate')
                    ->header('Pragma', 'no-cache')
                    ->header('Expires', '0')
                    ->header('Accept-Ranges', 'bytes');
            } catch (Exception $blockchainError) {
                report($blockchainError);
                Log::error('Failed to retrieve file from blockchain', [
                    'file_key' => $fileKey,
                    'data_txid' => $dataTxid ?? 'not_available',
                    'error' => $blockchainError->getMessage(),
                ]);

                // Return placeholder PDF if blockchain retrieval fails
                $placeholderPdf = $this->createPlaceholderPdf($fileKey, $documentData);

                $this->recordDocumentView($request, $fileKey, $documentData);

                return response($placeholderPdf)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'inline; filename="'.basename($fileKey).'"')
                    ->header('Cache-Control', 'private, no-cache, no-store, must-revalidate')
                    ->header('Pragma', 'no-cache')
                    ->header('Expires', '0')
                    ->header('Accept-Ranges', 'bytes');
            }
        } catch (Exception $e) {
            report($e);
            Log::error('Secure file download failed', [
                'file_key' => $fileKey,
                'error' => 'An error occurred downloading the document.',
                'user_id' => $request->user()?->id ?? 'guest',
            ]);

            abort(500, 'Unable to retrieve file');
        }
    }

    /**
     * Validate that the file exists in our document stream and user has access
     */
    private function validateFileAccess(string $fileKey): ?array
    {
        try {
            $blockchainData = $this->procurementDataService->validateDocumentExistsInBlockchain($fileKey);

            if ($blockchainData) {
                return $blockchainData;
            }

            $documentView = DocumentView::where('file_key', $fileKey)->first();

            if ($documentView) {
                $parts = explode('/', $fileKey);

                return [
                    'pr_number' => $parts[0] ?? 'Unknown',
                    'procurement_title' => 'Document (Development Mode)',
                    'document_type' => pathinfo($fileKey, PATHINFO_FILENAME),
                    'stage' => $parts[1] ?? 'Unknown',
                    'file_key' => $fileKey,
                ];
            }

            return null;
        } catch (Exception $e) {
            report($e);
            Log::error('File access validation failed', [
                'file_key' => $fileKey,
                'error' => 'An error occurred downloading the document.',
            ]);

            $documentView = DocumentView::where('file_key', $fileKey)->first();

            if ($documentView) {
                $parts = explode('/', $fileKey);

                return [
                    'pr_number' => $parts[0] ?? 'Unknown',
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
                'user_id' => $request->user()?->id,
                'file_key' => $fileKey,
                'pr_number' => $documentData['pr_number'] ?? '',
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
            report($e);
            Log::error('Failed to record document view', [
                'file_key' => $fileKey,
                'user_id' => $request->user()?->id,
                'error' => 'An error occurred downloading the document.',
            ]);
        }
    }

    /**
     * Create a simple placeholder PDF for development when actual file is missing
     */
    private function createPlaceholderPdf(string $fileKey, array $documentData): string
    {
        $documentType = $documentData['document_type'] ?? 'Document';
        $pr_number = $documentData['pr_number'] ?? 'Unknown';
        $stage = $documentData['stage'] ?? 'Unknown';

        // Build the stream content first, then calculate its actual length
        // to produce a structurally valid PDF that pdf.js can parse.
        $stream = "BT\n/F1 16 Tf\n100 700 Td\n(DEVELOPMENT MODE - PLACEHOLDER PDF) Tj\n";
        $stream .= "0 -30 Td\n(Document: {$documentType}) Tj\n";
        $stream .= "0 -20 Td\n(Procurement ID: {$pr_number}) Tj\n";
        $stream .= "0 -20 Td\n(Stage: {$stage}) Tj\n";
        $stream .= "0 -40 Td\n(File Key: {$fileKey}) Tj\n";
        $stream .= "0 -60 Td\n(This is a placeholder PDF for development purposes.) Tj\n";
        $stream .= "0 -20 Td\n(The actual file is not available in the storage.) Tj\n";
        $stream .= "ET\n";

        $streamLength = strlen($stream);

        $pdf = "%PDF-1.4\n";
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n";
        $pdf .= "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
        $pdf .= "5 0 obj\n<< /Length {$streamLength} >>\nstream\n";
        $pdf .= $stream;
        $pdf .= "endstream\nendobj\n";

        // Calculate correct byte offsets for xref table
        $offsets = [];
        $offsets[0] = 0;
        $offsets[1] = 10; // "1 0 obj" starts after "%PDF-1.4\n" = 10 bytes
        $offsets[2] = $offsets[1] + strlen("1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n");
        $offsets[3] = $offsets[2] + strlen("2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n");
        $offsets[4] = $offsets[3] + strlen("3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n");
        $offsets[5] = $offsets[4] + strlen("4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n");

        $xrefPos = $offsets[5] + strlen("5 0 obj\n<< /Length {$streamLength} >>\nstream\n") + $streamLength + strlen("endstream\nendobj\n");

        $pdf .= "xref\n0 6\n";
        $pdf .= sprintf("0000000000 65535 f \n");
        $pdf .= sprintf("%010d 00000 n \n", $offsets[1]);
        $pdf .= sprintf("%010d 00000 n \n", $offsets[2]);
        $pdf .= sprintf("%010d 00000 n \n", $offsets[3]);
        $pdf .= sprintf("%010d 00000 n \n", $offsets[4]);
        $pdf .= sprintf("%010d 00000 n \n", $offsets[5]);
        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefPos}\n%%EOF";

        return $pdf;
    }
}
